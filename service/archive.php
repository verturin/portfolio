<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Export ZIP pour les membres, sauvegarde complète et import / restauration (v1.4.0).
 *
 * Format d'archive commun (« verturin-portfolio », version 1) :
 *   manifest.json                     type (full | user), versions, date, forum d'origine
 *   data/<table>.json                 lignes des tables de l'extension (+ users.json, groups.json)
 *   files/source/<xx>/<hash>.<ext>    originaux (sauvegarde complète)
 *   <Album>/<Sous-album>/NNN - titre  originaux lisibles (export membre)
 *
 * Les vignettes et tailles moyennes ne sont pas archivées : elles sont recréées à l'import.
 */
class archive
{
	const FORMAT = 'verturin-portfolio';
	const FORMAT_VERSION = 1;
	const TABLES = ['albums', 'members', 'album_groups', 'photos', 'ratings', 'comments', 'reactions'];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var settings */
	protected $settings;

	/** @var \phpbb\user */
	protected $user;

	/** @var album_manager */
	protected $albums;

	/** @var image_processor */
	protected $image;

	/** @var array table logique => nom réel */
	protected $tables;

	/** @var string */
	protected $storage;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	public function __construct(\phpbb\db\driver\driver_interface $db, settings $settings, \phpbb\user $user, album_manager $albums, image_processor $image, array $tables, $storage, $root_path, $php_ext)
	{
		$this->db = $db;
		$this->settings = $settings;
		$this->user = $user;
		$this->albums = $albums;
		$this->image = $image;
		$this->tables = $tables;
		$this->storage = rtrim($storage, '/') . '/';
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public static function zip_available()
	{
		return class_exists('ZipArchive');
	}

	public function dir($type)
	{
		$dir = $this->storage . ($type === 'backup' ? 'backup' : 'export') . '/';
		if (!is_dir($dir))
		{
			@mkdir($dir, 0755, true);
		}

		return $dir;
	}

	/* ==================================================================
	 * EXPORT D'UN MEMBRE
	 * ================================================================== */

	/**
	 * Crée l'archive d'un album (avec ses sous-albums) ou de tout le portfolio d'un membre,
	 * et enregistre un lien de téléchargement unique.
	 *
	 * @param int[] $root_ids Albums de départ (leurs sous-albums sont inclus)
	 * @return array ligne portfolio_exports
	 */
	public function export_user(array $root_ids, $owner_label)
	{
		$this->long_run();

		// Albums concernés + chemin lisible de chaque album dans l'archive
		$paths = [];
		foreach ($root_ids as $root_id)
		{
			$root = $this->albums->get($root_id);
			if (!$root)
			{
				continue;
			}
			$paths[(int) $root_id] = $this->unique_name($this->clean_name($root['album_name']), $paths, '');
			foreach ($this->albums->flat_tree(null, 0, (int) $root_id, 1) as $id => $node)
			{
				$parent_path = $paths[(int) $node['album']['parent_id']];
				$paths[$id] = $parent_path . '/' . $this->unique_name($this->clean_name($node['album']['album_name']), $paths, $parent_path . '/');
			}
		}

		$album_ids = array_keys($paths);
		$data = $this->collect($album_ids, false);

		$token = bin2hex(random_bytes(16));
		$file = $this->dir('export') . 'export_' . $token . '.zip';
		$zip = $this->zip_open($file);

		$counters = [];
		foreach ($data['photos'] as &$photo)
		{
			$album_id = (int) $photo['album_id'];
			$counters[$album_id] = isset($counters[$album_id]) ? $counters[$album_id] + 1 : 1;
			$name = sprintf('%03d - %s.%s', $counters[$album_id], $this->clean_name(htmlspecialchars_decode($photo['photo_title'], ENT_QUOTES)) ?: 'photo', $photo['photo_ext']);
			$photo['zip_path'] = $paths[$album_id] . '/' . $name;

			$source = $this->image->path($photo['photo_file'], 'source');
			if (is_file($source))
			{
				$zip->addFile($source, $photo['zip_path']);
			}
		}
		unset($photo);

		foreach ($data['albums'] as &$album)
		{
			$album['zip_path'] = $paths[(int) $album['album_id']];
		}
		unset($album);

		$this->write_data($zip, $data, 'user');
		$zip->addFromString('LISEZ-MOI.txt', $this->readme_user($owner_label, count($data['photos'])));
		$zip->close();

		$ttl = max(1, (int) $this->settings['portfolio_export_ttl']);
		$row = [
			'user_id'			=> (int) $this->user->data['user_id'],
			'album_id'			=> count($root_ids) === 1 ? (int) reset($root_ids) : 0,
			'export_token'		=> $token,
			'export_file'		=> basename($file),
			'export_size'		=> (int) @filesize($file),
			'export_photos'		=> count($data['photos']),
			'export_time'		=> time(),
			'export_expire'		=> time() + $ttl * 3600,
			'export_downloads'	=> 0,
		];
		$this->db->sql_query('INSERT INTO ' . $this->tables['exports'] . ' ' . $this->db->sql_build_array('INSERT', $row));
		$row['export_id'] = (int) $this->db->sql_nextid();

		return $row;
	}

	public function get_export($token)
	{
		if (!preg_match('/^[a-f0-9]{32}$/', (string) $token))
		{
			return null;
		}

		$sql = 'SELECT * FROM ' . $this->tables['exports'] . " WHERE export_token = '" . $this->db->sql_escape($token) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	public function list_exports($user_id)
	{
		$rows = [];
		$sql = 'SELECT * FROM ' . $this->tables['exports'] . '
			WHERE user_id = ' . (int) $user_id . '
				AND export_expire > ' . time() . '
			ORDER BY export_time DESC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function count_recent_exports($user_id, $seconds)
	{
		$sql = 'SELECT COUNT(export_id) AS total FROM ' . $this->tables['exports'] . '
			WHERE user_id = ' . (int) $user_id . '
				AND export_time > ' . (time() - (int) $seconds);
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	public function export_path(array $export)
	{
		return $this->dir('export') . basename($export['export_file']);
	}

	public function mark_downloaded(array $export)
	{
		$this->db->sql_query('UPDATE ' . $this->tables['exports'] . ' SET export_downloads = export_downloads + 1 WHERE export_id = ' . (int) $export['export_id']);
	}

	public function delete_export(array $export)
	{
		@unlink($this->export_path($export));
		$this->db->sql_query('DELETE FROM ' . $this->tables['exports'] . ' WHERE export_id = ' . (int) $export['export_id']);
	}

	/**
	 * Supprime les exports expirés (tâche cron).
	 */
	public function purge_expired_exports()
	{
		$count = 0;
		$sql = 'SELECT * FROM ' . $this->tables['exports'] . ' WHERE export_expire < ' . time();
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->delete_export($row);
			$count++;
		}
		$this->db->sql_freeresult($result);

		return $count;
	}

	/* ==================================================================
	 * SAUVEGARDE COMPLÈTE
	 * ================================================================== */

	/**
	 * @return string Nom du fichier de sauvegarde créé dans files/portfolio/backup/
	 */
	public function backup_full()
	{
		$this->long_run();

		$album_ids = array_keys($this->albums->get_all());
		$data = $this->collect($album_ids, true);

		// Réglages de l'extension
		$data['config'] = [];
		$result = $this->db->sql_query('SELECT * FROM ' . $this->tables['config']);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$data['config'][] = $row;
		}
		$this->db->sql_freeresult($result);

		$name = 'portfolio_backup_' . gmdate('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.zip';
		$zip = $this->zip_open($this->dir('backup') . $name);

		foreach ($data['photos'] as $photo)
		{
			$source = $this->image->path($photo['photo_file'], 'source');
			if (is_file($source))
			{
				$zip->addFile($source, 'files/source/' . $photo['photo_file']);
			}
		}

		$logo = $this->storage . 'wm/logo.png';
		if (is_file($logo))
		{
			$zip->addFile($logo, 'files/wm/logo.png');
		}

		$this->write_data($zip, $data, 'full');
		$zip->close();

		return $name;
	}

	public function list_backups()
	{
		$list = [];
		foreach (glob($this->dir('backup') . '*.zip') ?: [] as $file)
		{
			$list[] = [
				'name'	=> basename($file),
				'size'	=> (int) filesize($file),
				'time'	=> (int) filemtime($file),
			];
		}
		usort($list, function ($a, $b) {
			return $b['time'] - $a['time'];
		});

		return $list;
	}

	public function backup_path($name)
	{
		$name = basename((string) $name);
		if (!preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name))
		{
			return '';
		}
		$path = $this->dir('backup') . $name;

		return is_file($path) ? $path : '';
	}

	/* ==================================================================
	 * IMPORT / RESTAURATION
	 * ================================================================== */

	/**
	 * Lit le manifeste d'une archive (sans rien importer).
	 *
	 * @return array|string manifeste ou clé d'erreur
	 */
	public function inspect($file)
	{
		$zip = new \ZipArchive();
		if ($zip->open($file) !== true)
		{
			return 'PORTFOLIO_ARCHIVE_INVALID';
		}

		$manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
		$zip->close();

		if (!is_array($manifest) || !isset($manifest['format']) || $manifest['format'] !== self::FORMAT || (int) $manifest['format_version'] > self::FORMAT_VERSION)
		{
			return 'PORTFOLIO_ARCHIVE_INVALID';
		}

		return $manifest;
	}

	/**
	 * Importe une archive.
	 *
	 * @param string $file
	 * @param string $mode           merge (ajout, nouveaux identifiants) | replace (remplace TOUT, sauvegarde complète uniquement)
	 * @param int    $default_owner  Membre qui reçoit les albums dont le propriétaire n'existe pas sur ce forum
	 * @param int    $force_owner    Si > 0, tous les albums personnels importés appartiennent à ce membre (import d'un export de membre)
	 * @param bool   $restore_config Restaurer aussi les réglages (mode replace)
	 * @return array statistiques ou ['error' => clé]
	 */
	public function import($file, $mode, $default_owner, $force_owner = 0, $restore_config = false)
	{
		$this->long_run();

		$manifest = $this->inspect($file);
		if (!is_array($manifest))
		{
			return ['error' => $manifest];
		}

		if ($mode === 'replace' && $manifest['type'] !== 'full')
		{
			return ['error' => 'PORTFOLIO_ARCHIVE_REPLACE_FULL_ONLY'];
		}

		$zip = new \ZipArchive();
		$zip->open($file);

		$data = [];
		foreach (array_merge(self::TABLES, ['users', 'groups', 'config']) as $name)
		{
			$json = $zip->getFromName('data/' . $name . '.json');
			$data[$name] = $json !== false ? (array) json_decode($json, true) : [];
		}

		$user_map = $this->map_users($data['users']);
		$group_map = $this->map_groups($data['groups']);
		$default_owner = (int) $default_owner ?: (int) $this->user->data['user_id'];
		if ($default_owner <= ANONYMOUS)
		{
			// Ligne de commande : le premier fondateur du forum reçoit les albums orphelins
			$founders = $this->fetch('SELECT user_id FROM ' . USERS_TABLE . ' WHERE user_type = ' . USER_FOUNDER . ' ORDER BY user_id ASC');
			$default_owner = $founders ? (int) $founders[0]['user_id'] : 2;
		}

		$map_user = function ($old_id, $fallback) use ($user_map, $force_owner) {
			if ($force_owner)
			{
				return (int) $force_owner;
			}

			return isset($user_map[(int) $old_id]) ? $user_map[(int) $old_id] : $fallback;
		};

		$stats = ['albums' => 0, 'photos' => 0, 'comments' => 0, 'ratings' => 0, 'members' => 0, 'missing_files' => 0, 'unmapped_users' => count(array_diff(array_column($data['users'], 'user_id'), array_keys($user_map)))];
		$tmp_dir = $this->dir('backup') . 'tmp_' . bin2hex(random_bytes(4)) . '/';
		@mkdir($tmp_dir, 0755, true);

		if ($mode === 'replace')
		{
			$this->wipe_everything();
		}

		$keep_ids = $mode === 'replace';

		// --- Albums (parents d'abord)
		$albums = $data['albums'];
		$album_map = [];
		$pending = $albums;
		$guard = 0;
		while ($pending && $guard++ < 100)
		{
			foreach ($pending as $key => $album)
			{
				$old_parent = (int) $album['parent_id'];
				if ($old_parent && !isset($album_map[$old_parent]))
				{
					// Parent absent de l'archive : rattaché à la racine
					if (!in_array($old_parent, array_map('intval', array_column($albums, 'album_id')), true))
					{
						$album['parent_id'] = 0;
						$old_parent = 0;
					}
					else
					{
						continue;
					}
				}

				$row = $this->only_columns($album, 'albums');
				$row['parent_id'] = $old_parent ? $album_map[$old_parent] : 0;
				$row['owner_id'] = (int) $album['owner_id'] ? $map_user($album['owner_id'], $default_owner) : 0;
				$row['album_cover_id'] = 0;
				$row['album_last_photo_id'] = 0;
				if (!$keep_ids)
				{
					unset($row['album_id']);
				}

				$this->db->sql_query('INSERT INTO ' . $this->tables['albums'] . ' ' . $this->db->sql_build_array('INSERT', $row));
				$album_map[(int) $album['album_id']] = $keep_ids ? (int) $album['album_id'] : (int) $this->db->sql_nextid();
				$stats['albums']++;
				unset($pending[$key]);
			}
		}
		$this->albums->reset();

		// --- Droits de groupe et co-gestion
		foreach ($data['album_groups'] as $rule)
		{
			if (isset($album_map[(int) $rule['album_id']], $group_map[(int) $rule['group_id']]))
			{
				$row = $this->only_columns($rule, 'album_groups');
				$row['album_id'] = $album_map[(int) $rule['album_id']];
				$row['group_id'] = $group_map[(int) $rule['group_id']];
				$this->safe_insert('album_groups', $row);
			}
		}

		foreach ($data['members'] as $member)
		{
			$user_id = isset($user_map[(int) $member['user_id']]) ? $user_map[(int) $member['user_id']] : 0;
			$owner_id = (int) $member['owner_id'] ? $map_user($member['owner_id'], $default_owner) : 0;
			$album_id = (int) $member['album_id'] ? (isset($album_map[(int) $member['album_id']]) ? $album_map[(int) $member['album_id']] : -1) : 0;
			if (!$user_id || $album_id < 0 || $user_id === $owner_id)
			{
				continue;
			}
			$row = $this->only_columns($member, 'members');
			unset($row['member_id']);
			$row['user_id'] = $user_id;
			$row['owner_id'] = $owner_id;
			$row['album_id'] = $album_id;
			if ($this->safe_insert('members', $row))
			{
				$stats['members']++;
			}
		}

		// --- Photos (fichier original + vignettes recréées)
		$photo_map = [];
		foreach ($data['photos'] as $photo)
		{
			if (!isset($album_map[(int) $photo['album_id']]))
			{
				continue;
			}

			$entry = !empty($photo['zip_path']) ? $photo['zip_path'] : 'files/source/' . $photo['photo_file'];
			$content = $zip->getFromName($entry);
			if ($content === false)
			{
				$stats['missing_files']++;
				continue;
			}

			$tmp = $tmp_dir . 'p.' . preg_replace('/[^a-z0-9]/i', '', $photo['photo_ext']);
			file_put_contents($tmp, $content);
			unset($content);

			$file_info = $this->image->import_original($tmp, $keep_ids ? $photo['photo_file'] : '');
			@unlink($tmp);
			if (!$file_info)
			{
				$stats['missing_files']++;
				continue;
			}

			$row = array_merge($this->only_columns($photo, 'photos'), $file_info);
			$row['album_id'] = $album_map[(int) $photo['album_id']];
			$row['user_id'] = $map_user($photo['user_id'], $default_owner);
			$row['deleted_by'] = (int) $photo['deleted_by'] ? $map_user($photo['deleted_by'], 0) : 0;
			if (!$keep_ids)
			{
				unset($row['photo_id']);
			}

			$this->db->sql_query('INSERT INTO ' . $this->tables['photos'] . ' ' . $this->db->sql_build_array('INSERT', $row));
			$photo_map[(int) $photo['photo_id']] = $keep_ids ? (int) $photo['photo_id'] : (int) $this->db->sql_nextid();
			$stats['photos']++;
		}

		// --- Notes et commentaires (membres retrouvés uniquement)
		foreach ($data['ratings'] as $rating)
		{
			if (isset($photo_map[(int) $rating['photo_id']], $user_map[(int) $rating['user_id']]))
			{
				$row = $this->only_columns($rating, 'ratings');
				$row['photo_id'] = $photo_map[(int) $rating['photo_id']];
				$row['user_id'] = $user_map[(int) $rating['user_id']];
				if ($this->safe_insert('ratings', $row))
				{
					$stats['ratings']++;
				}
			}
		}

		// Réactions (v1.5.1)
		foreach ($data['reactions'] as $reaction)
		{
			if (isset($photo_map[(int) $reaction['photo_id']], $user_map[(int) $reaction['user_id']]))
			{
				$row = $this->only_columns($reaction, 'reactions');
				$row['photo_id'] = $photo_map[(int) $reaction['photo_id']];
				$row['user_id'] = $user_map[(int) $reaction['user_id']];
				$this->safe_insert('reactions', $row);
			}
		}

		foreach ($data['comments'] as $comment)
		{
			if (isset($photo_map[(int) $comment['photo_id']], $user_map[(int) $comment['user_id']]))
			{
				$row = $this->only_columns($comment, 'comments');
				$row['photo_id'] = $photo_map[(int) $comment['photo_id']];
				$row['user_id'] = $user_map[(int) $comment['user_id']];
				if (!$keep_ids)
				{
					unset($row['comment_id']);
				}
				$this->db->sql_query('INSERT INTO ' . $this->tables['comments'] . ' ' . $this->db->sql_build_array('INSERT', $row));
				$stats['comments']++;
			}
		}

		// --- Couvertures, compteurs
		foreach ($albums as $album)
		{
			$new_id = isset($album_map[(int) $album['album_id']]) ? $album_map[(int) $album['album_id']] : 0;
			if ($new_id && (int) $album['album_cover_id'] && isset($photo_map[(int) $album['album_cover_id']]))
			{
				$this->albums->set_cover($new_id, $photo_map[(int) $album['album_cover_id']]);
			}
		}
		$this->recount_photos(array_values($photo_map));
		foreach ($album_map as $new_id)
		{
			$this->albums->resync($new_id);
		}

		// --- Réglages et logo (restauration complète)
		if ($mode === 'replace' && $restore_config)
		{
			foreach ($data['config'] as $row)
			{
				if (isset($row['config_name']) && strpos($row['config_name'], 'portfolio_') === 0 && $row['config_name'] !== 'portfolio_version')
				{
					$this->settings->set($row['config_name'], $row['config_value']);
				}
			}
		}
		$logo = $zip->getFromName('files/wm/logo.png');
		if ($logo !== false && ($mode === 'replace' || !is_file($this->storage . 'wm/logo.png')))
		{
			@mkdir($this->storage . 'wm', 0755, true);
			file_put_contents($this->storage . 'wm/logo.png', $logo);
		}

		$zip->close();
		@rmdir($tmp_dir);

		if ($keep_ids)
		{
			$this->fix_sequences();
		}

		return $stats;
	}

	/* ==================================================================
	 * Interne
	 * ================================================================== */

	/**
	 * Rassemble les lignes liées à une liste d'albums.
	 */
	protected function collect(array $album_ids, $include_trash)
	{
		$data = array_fill_keys(self::TABLES, []);
		$data['users'] = [];
		$data['groups'] = [];
		if (!$album_ids)
		{
			return $data;
		}

		$in_albums = $this->db->sql_in_set('album_id', $album_ids);
		$data['albums'] = $this->fetch('SELECT * FROM ' . $this->tables['albums'] . ' WHERE ' . $in_albums . ' ORDER BY album_id');
		$data['album_groups'] = $this->fetch('SELECT * FROM ' . $this->tables['album_groups'] . ' WHERE ' . $in_albums);

		$owners = array_unique(array_map('intval', array_column($data['albums'], 'owner_id')));
		$data['members'] = $this->fetch('SELECT * FROM ' . $this->tables['members'] . '
			WHERE ' . $in_albums . ($owners ? ' OR (album_id = 0 AND ' . $this->db->sql_in_set('owner_id', $owners) . ')' : ''));

		$statuses = $include_trash ? [0, 1, 2] : [0, 1];
		$data['photos'] = $this->fetch('SELECT * FROM ' . $this->tables['photos'] . '
			WHERE ' . $in_albums . ' AND ' . $this->db->sql_in_set('photo_status', $statuses) . '
			ORDER BY album_id, photo_time, photo_id');

		$photo_ids = array_map('intval', array_column($data['photos'], 'photo_id'));
		if ($photo_ids)
		{
			$in_photos = $this->db->sql_in_set('photo_id', $photo_ids);
			$data['ratings'] = $this->fetch('SELECT * FROM ' . $this->tables['ratings'] . ' WHERE ' . $in_photos);
			$data['reactions'] = $this->fetch('SELECT * FROM ' . $this->tables['reactions'] . ' WHERE ' . $in_photos);
			$data['comments'] = $this->fetch('SELECT * FROM ' . $this->tables['comments'] . ' WHERE ' . $in_photos . ' ORDER BY comment_id');
		}

		// Membres et groupes cités, pour retrouver les comptes sur un autre forum
		$user_ids = array_merge(
			$owners,
			array_column($data['members'], 'owner_id'),
			array_column($data['members'], 'user_id'),
			array_column($data['photos'], 'user_id'),
			array_column($data['photos'], 'deleted_by'),
			array_column($data['ratings'], 'user_id'),
			array_column($data['reactions'], 'user_id'),
			array_column($data['comments'], 'user_id')
		);
		$user_ids = array_values(array_filter(array_unique(array_map('intval', $user_ids))));
		if ($user_ids)
		{
			$data['users'] = $this->fetch('SELECT user_id, username, username_clean FROM ' . USERS_TABLE . ' WHERE ' . $this->db->sql_in_set('user_id', $user_ids));
		}

		$group_ids = array_values(array_unique(array_map('intval', array_column($data['album_groups'], 'group_id'))));
		if ($group_ids)
		{
			$data['groups'] = $this->fetch('SELECT group_id, group_name FROM ' . GROUPS_TABLE . ' WHERE ' . $this->db->sql_in_set('group_id', $group_ids));
		}

		return $data;
	}

	protected function write_data($zip, array $data, $type)
	{
		$manifest = [
			'format'			=> self::FORMAT,
			'format_version'	=> self::FORMAT_VERSION,
			'type'				=> $type,
			'extension_version'	=> (string) $this->settings['portfolio_version'],
			'created'			=> gmdate('c'),
			'board_url'			=> generate_board_url(),
			'created_by'		=> $this->user->data['username'],
			'counts'			=> [
				'albums'	=> count($data['albums']),
				'photos'	=> count($data['photos']),
				'comments'	=> count($data['comments']),
				'ratings'	=> count($data['ratings']),
			],
		];

		$zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		foreach ($data as $name => $rows)
		{
			$zip->addFromString('data/' . $name . '.json', json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
	}

	/**
	 * @return \ZipArchive
	 */
	protected function zip_open($file)
	{
		$zip = new \ZipArchive();
		if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true)
		{
			throw new \RuntimeException('PORTFOLIO_ARCHIVE_WRITE');
		}

		return $zip;
	}

	protected function map_users(array $users)
	{
		$map = [];
		$by_clean = [];
		foreach ($users as $row)
		{
			if (!empty($row['username_clean']))
			{
				$by_clean[$row['username_clean']] = (int) $row['user_id'];
			}
		}
		if (!$by_clean)
		{
			return $map;
		}

		$sql = 'SELECT user_id, username_clean FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('username_clean', array_map('strval', array_keys($by_clean))) . '
				AND user_type <> ' . USER_IGNORE;
		foreach ($this->fetch($sql) as $row)
		{
			$map[$by_clean[$row['username_clean']]] = (int) $row['user_id'];
		}

		return $map;
	}

	protected function map_groups(array $groups)
	{
		$map = [];
		$by_name = [];
		foreach ($groups as $row)
		{
			$by_name[$row['group_name']] = (int) $row['group_id'];
		}
		if (!$by_name)
		{
			return $map;
		}

		$sql = 'SELECT group_id, group_name FROM ' . GROUPS_TABLE . ' WHERE ' . $this->db->sql_in_set('group_name', array_map('strval', array_keys($by_name)));
		foreach ($this->fetch($sql) as $row)
		{
			$map[$by_name[$row['group_name']]] = (int) $row['group_id'];
		}

		return $map;
	}

	/**
	 * Ne garde que les colonnes existantes de la table cible (archives d'une autre version).
	 */
	protected function only_columns(array $row, $table)
	{
		static $columns = [];
		if (!isset($columns[$table]))
		{
			$result = $this->db->sql_query_limit('SELECT * FROM ' . $this->tables[$table], 1);
			$sample = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			$columns[$table] = $sample ? array_keys($sample) : null;
		}

		$known = $columns[$table] ?: $this->default_columns($table);
		$row = array_intersect_key($row, array_flip($known));

		// Champs texte sans valeur par défaut sous MySQL strict : toujours fournis
		$text = ['albums' => ['album_desc'], 'photos' => ['photo_desc', 'deleted_reason'], 'comments' => ['comment_text']];
		foreach (isset($text[$table]) ? $text[$table] : [] as $column)
		{
			if (!isset($row[$column]) || $row[$column] === null)
			{
				$row[$column] = '';
			}
		}

		return $row;
	}

	protected function default_columns($table)
	{
		$defaults = [
			'albums'		=> ['album_id', 'parent_id', 'owner_id', 'album_name', 'album_desc', 'album_desc_uid', 'album_desc_bitfield', 'album_desc_options', 'album_visibility', 'album_friends', 'album_download', 'album_protected', 'album_locked', 'album_order', 'album_sort', 'album_cover_id', 'album_photos', 'album_last_photo_id', 'album_last_time', 'album_time'],
			'members'		=> ['member_id', 'owner_id', 'album_id', 'user_id', 'member_role', 'member_time'],
			'album_groups'	=> ['album_id', 'group_id', 'can_view', 'can_upload', 'can_rate', 'can_comment'],
			'photos'		=> ['photo_id', 'album_id', 'user_id', 'photo_title', 'photo_desc', 'photo_desc_uid', 'photo_desc_bitfield', 'photo_desc_options', 'photo_file', 'photo_realname', 'photo_ext', 'photo_mimetype', 'photo_filesize', 'photo_width', 'photo_height', 'photo_hash', 'photo_exif_stripped', 'photo_time', 'photo_status', 'photo_prev_status', 'photo_views', 'photo_rating_sum', 'photo_rating_count', 'photo_rating_avg', 'photo_comments', 'deleted_time', 'deleted_by', 'deleted_reason'],
			'ratings'		=> ['photo_id', 'user_id', 'rating', 'rating_time'],
			'reactions'		=> ['photo_id', 'user_id', 'reaction', 'reaction_time'],
			'comments'		=> ['comment_id', 'photo_id', 'user_id', 'comment_text', 'comment_uid', 'comment_bitfield', 'comment_options', 'comment_time', 'comment_edit_time'],
		];

		return $defaults[$table];
	}

	/**
	 * Insertion qui ignore les doublons (clé primaire composée).
	 */
	protected function safe_insert($table, array $row)
	{
		$this->db->sql_return_on_error(true);
		$this->db->sql_query('INSERT INTO ' . $this->tables[$table] . ' ' . $this->db->sql_build_array('INSERT', $row));
		$ok = !$this->db->get_sql_error_triggered();
		$this->db->sql_return_on_error(false);

		return $ok;
	}

	protected function recount_photos(array $photo_ids)
	{
		foreach (array_chunk($photo_ids, 200) as $chunk)
		{
			foreach ($chunk as $photo_id)
			{
				$result = $this->db->sql_query('SELECT COUNT(user_id) AS total, SUM(rating) AS total_sum FROM ' . $this->tables['ratings'] . ' WHERE photo_id = ' . (int) $photo_id);
				$row = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);
				$count = (int) $row['total'];
				$sum = (int) $row['total_sum'];

				$result = $this->db->sql_query('SELECT COUNT(comment_id) AS total FROM ' . $this->tables['comments'] . ' WHERE photo_id = ' . (int) $photo_id);
				$comments = (int) $this->db->sql_fetchfield('total');
				$this->db->sql_freeresult($result);

				$this->db->sql_query('UPDATE ' . $this->tables['photos'] . ' SET ' . $this->db->sql_build_array('UPDATE', [
					'photo_rating_count'	=> $count,
					'photo_rating_sum'		=> $sum,
					'photo_rating_avg'		=> $count ? (int) round($sum * 100 / $count) : 0,
					'photo_comments'		=> $comments,
				]) . ' WHERE photo_id = ' . (int) $photo_id);
			}
		}
	}

	/**
	 * Mode « remplacer » : vide toutes les données et fichiers de l'extension (pas les réglages).
	 */
	protected function wipe_everything()
	{
		foreach (self::TABLES as $table)
		{
			$this->db->sql_query('DELETE FROM ' . $this->tables[$table]);
		}

		foreach (['source', 'medium', 'thumb', 'cache'] as $dir)
		{
			$path = $this->storage . $dir;
			if (!is_dir($path))
			{
				continue;
			}
			$items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($items as $item)
			{
				$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
			}
		}

		$this->albums->reset();
	}

	/**
	 * PostgreSQL : remet les séquences d'auto-incrément à jour après insertion d'identifiants explicites.
	 */
	protected function fix_sequences()
	{
		if (strpos($this->db->get_sql_layer(), 'postgres') !== 0)
		{
			return;
		}

		foreach (['albums' => 'album_id', 'photos' => 'photo_id', 'comments' => 'comment_id', 'members' => 'member_id'] as $table => $column)
		{
			$name = $this->tables[$table];
			$this->db->sql_query("SELECT setval('{$name}_seq', COALESCE((SELECT MAX({$column}) FROM {$name}), 0) + 1, false)");
		}
	}

	protected function fetch($sql)
	{
		$rows = [];
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function clean_name($name)
	{
		$name = htmlspecialchars_decode((string) $name, ENT_QUOTES);
		$name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', $name);
		$name = trim(preg_replace('/\s+/u', ' ', $name), ' .');

		return utf8_substr($name, 0, 80);
	}

	protected function unique_name($name, array $paths, $prefix)
	{
		$name = $name !== '' ? $name : 'Album';
		$candidate = $name;
		$i = 2;
		while (in_array($prefix . $candidate, $paths, true))
		{
			$candidate = $name . ' (' . $i++ . ')';
		}

		return $candidate;
	}

	protected function readme_user($owner, $count)
	{
		return "Export du portfolio de {$owner}\r\n"
			. 'Date : ' . gmdate('d/m/Y H:i') . " UTC\r\n"
			. "Photos : {$count}\r\n\r\n"
			. "Les dossiers reprennent vos albums et sous-albums ; les photos sont les originaux envoyés.\r\n"
			. "Le dossier « data » et le fichier manifest.json permettent à un administrateur de réimporter cet export\r\n"
			. "(ACP › Extensions › Portfolio › Sauvegarde) : merci de ne pas les modifier.\r\n";
	}

	protected function long_run()
	{
		if (function_exists('set_time_limit'))
		{
			@set_time_limit(0);
		}
		@ignore_user_abort(true);
	}
}
