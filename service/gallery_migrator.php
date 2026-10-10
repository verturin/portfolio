<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Bascule phpBB Gallery → Portfolio (v1.5.0).
 *
 * Les deux extensions sont installées en même temps. Les données de phpBB Gallery sont COPIÉES
 * (jamais modifiées ni supprimées) vers le Portfolio, pour un membre, un groupe ou tout le forum.
 * Chaque élément copié est noté dans la table de correspondance portfolio_legacy, ce qui permet :
 *  - de reprendre une bascule interrompue (traitement par lots, rien n'est copié deux fois) ;
 *  - de rediriger les anciens liens /gallery/… et le BBCode [image] vers les photos du Portfolio ;
 *  - d'annuler la bascule (suppression des seules copies créées dans le Portfolio).
 */
class gallery_migrator
{
	/** Durée maximale d'un lot de bascule (secondes) : le lot suivant s'enchaîne tout seul */
	const BATCH_SECONDS = 12;

	/** @var string Raison du dernier échec (missing, memory, unreadable, refused, crash) */
	protected $last_error = '';

	/** @var string Message d'erreur détaillé du dernier échec */
	protected $last_message = '';

	/** @var string Nom de l'album qui reçoit les photos dont l'album a disparu de la galerie */
	protected $fallback_name = 'Photos récupérées de la galerie';

	public function set_fallback_name($name)
	{
		if ((string) $name !== '')
		{
			$this->fallback_name = (string) $name;
		}
	}

	/**
	 * Album de secours d'un membre (un seul par membre, créé au premier besoin).
	 */
	protected function fallback_album($user_id)
	{
		$user_id = max(0, (int) $user_id);
		$owner_id = $user_id > ANONYMOUS ? $user_id : 0;
		$existing = $this->get_map('fallback', $owner_id);
		if ($existing && $this->albums->get($existing))
		{
			return $existing;
		}

		$this->db->sql_query('INSERT INTO ' . $this->tables['albums'] . ' ' . $this->db->sql_build_array('INSERT', [
			'parent_id'				=> 0,
			'owner_id'				=> $owner_id,
			'album_name'			=> $this->fallback_name,
			'album_desc'			=> '',
			'album_desc_uid'		=> '',
			'album_desc_bitfield'	=> '',
			'album_desc_options'	=> 7,
			'album_visibility'		=> access::VIS_REGISTERED,
			'album_locked'			=> 0,
			'album_order'			=> 9999,
			'album_sort'			=> 'date_desc',
			'album_time'			=> time(),
		]));
		$new_id = (int) $this->db->sql_nextid();
		$this->albums->reset();
		$this->set_map('fallback', $owner_id, $new_id, $owner_id);

		return $new_id;
	}

	/** @var array Albums en cours de recréation (protection contre les boucles parent/enfant) */
	protected $resolving = [];

	/** @var array|null Photo en cours de copie (pour le rapport d'incident) */
	protected $current;

	/** @var bool */
	protected $watching = false;

	/**
	 * Si PHP s'arrête en pleine copie (mémoire épuisée, erreur fatale), l'erreur exacte et la photo concernée
	 * sont enregistrées et affichées dans l'ACP, sans avoir à chercher dans les journaux du serveur (v1.9.3).
	 */
	protected function watch_fatal()
	{
		if ($this->watching)
		{
			return;
		}
		$this->watching = true;
		// Réserve libérée au moment de l'erreur, pour pouvoir encore écrire le rapport
		$reserve = str_repeat(' ', 1024 * 1024);
		register_shutdown_function(function () use (&$reserve) {
			$reserve = null;
			$error = error_get_last();
			if (!$this->current || !$error || !in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true))
			{
				return;
			}
			$row = $this->current;
			$memory = stripos($error['message'], 'memory') !== false;
			try
			{
				$this->set_map('image', (int) $row['image_id'], 0, (int) $row['image_user_id'], $memory ? 'memory' : 'crash', $error['message'] . ' (' . basename((string) $error['file']) . ':' . (int) $error['line'] . ')');
				$this->save_incident([
					'time'		=> time(),
					'image_id'	=> (int) $row['image_id'],
					'title'		=> (string) $row['image_name'],
					'file'		=> (string) $row['image_filename'],
					'message'	=> (string) $error['message'],
					'where'		=> basename((string) $error['file']) . ':' . (int) $error['line'],
					'memory'	=> (string) @ini_get('memory_limit'),
					'peak'		=> (int) memory_get_peak_usage(true),
				]);
			}
			catch (\Throwable $e)
			{
			}
		});
	}

	protected function save_incident(array $data)
	{
		$table = $this->table_prefix . 'portfolio_config';
		$this->db->sql_query('DELETE FROM ' . $table . " WHERE config_name = 'portfolio_mig_incident'");
		$this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', [
			'config_name'	=> 'portfolio_mig_incident',
			'config_value'	=> truncate_string(json_encode($data), 255, 255, false),
		]));
	}

	/**
	 * Dernier incident enregistré, ou photo restée « en cours » (le serveur a coupé la page sans laisser PHP finir).
	 *
	 * @return array|null
	 */
	public function incident()
	{
		$result = $this->db->sql_query('SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_mig_incident'");
		$json = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);
		$incident = $json !== '' ? json_decode($json, true) : null;

		// Photo notée « en cours » depuis plus d'une minute : la page a été coupée par le serveur web (délai)
		$sql = 'SELECT legacy_id, legacy_time FROM ' . $this->tables['legacy'] . "
			WHERE legacy_type = 'image' AND new_id = 0 AND legacy_note = 'started' AND legacy_time < " . (time() - 60) . '
			ORDER BY legacy_time DESC';
		$started = $this->fetch_limit($sql, 1);
		if ($started && (!$incident || (int) $started[0]['legacy_time'] > (int) $incident['time']))
		{
			$image = $this->gallery_available() ? $this->fetch('SELECT image_name, image_filename FROM ' . $this->gtable('images') . ' WHERE image_id = ' . (int) $started[0]['legacy_id']) : [];
			$incident = [
				'time'		=> (int) $started[0]['legacy_time'],
				'image_id'	=> (int) $started[0]['legacy_id'],
				'title'		=> $image ? (string) $image[0]['image_name'] : '',
				'file'		=> $image ? (string) $image[0]['image_filename'] : '',
				'message'	=> '',
				'where'		=> '',
				'memory'	=> '',
				'peak'		=> 0,
				'killed'	=> true,
			];
		}

		return $incident;
	}

	public function clear_incident()
	{
		$this->db->sql_query('DELETE FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_mig_incident'");
		$this->db->sql_query('UPDATE ' . $this->tables['legacy'] . " SET legacy_note = 'crash' WHERE legacy_type = 'image' AND new_id = 0 AND legacy_note = 'started'");
	}

	/**
	 * Limites PHP utiles au diagnostic.
	 */
	public function server_limits()
	{
		$before = (string) @ini_get('memory_limit');
		$can_raise = false;
		if (function_exists('ini_set') && $before !== '-1')
		{
			$test = $this->image->memory_limit() + 64 * 1024 * 1024;
			@ini_set('memory_limit', (string) $test);
			$can_raise = $this->image->memory_limit() >= $test;
			@ini_set('memory_limit', $before);
		}

		return [
			'memory_limit'	=> $before,
			'can_raise'		=> $before === '-1' || $can_raise,
			'max_time'		=> (int) @ini_get('max_execution_time'),
			'php'			=> PHP_VERSION,
			'gd'			=> function_exists('gd_info') ? gd_info()['GD Version'] : '',
		];
	}

	const GALLERY_STATUS_UNAPPROVED	= 0;
	const GALLERY_STATUS_APPROVED	= 1;
	const GALLERY_STATUS_LOCKED		= 2;
	const GALLERY_STATUS_ORPHAN		= 3;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\db\tools\tools_interface */
	protected $db_tools;

	/** @var \phpbb\config\config Configuration du forum */
	protected $board_config;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\extension\manager */
	protected $ext_manager;

	/** @var album_manager */
	protected $albums;

	/** @var image_processor */
	protected $image;

	/** @var array */
	protected $tables;

	/** @var string */
	protected $table_prefix;

	/** @var string */
	protected $root_path;

	/** @var array|null [type][legacy_id] => new_id */
	protected $map;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\db\tools\tools_interface $db_tools, \phpbb\config\config $board_config, \phpbb\cache\service $cache, \phpbb\extension\manager $ext_manager, album_manager $albums, image_processor $image, array $tables, $table_prefix, $root_path)
	{
		$this->db = $db;
		$this->db_tools = $db_tools;
		$this->board_config = $board_config;
		$this->cache = $cache;
		$this->ext_manager = $ext_manager;
		$this->albums = $albums;
		$this->image = $image;
		$this->tables = $tables;
		$this->table_prefix = $table_prefix;
		$this->root_path = $root_path;
	}

	/* ==================================================================
	 * État
	 * ================================================================== */

	protected function gtable($name)
	{
		return $this->table_prefix . 'gallery_' . $name;
	}

	public function gallery_available()
	{
		return $this->db_tools->sql_table_exists($this->gtable('images')) && $this->db_tools->sql_table_exists($this->gtable('albums'));
	}

	public function gallery_enabled()
	{
		return $this->ext_manager->is_enabled('phpbbgallery/core');
	}

	public function gallery_source_dir()
	{
		return $this->root_path . 'files/phpbbgallery/core/source/';
	}

	/**
	 * Photos de la galerie qui n'ont pas pu être copiées (v1.9.1), avec la raison.
	 *
	 * reason : gone (photo supprimée de la galerie), noname (aucun nom de fichier), missing (fichier absent du disque),
	 *          unreadable (fichier illisible ou pas une image), refused (image valide mais refusée à la conversion)
	 *
	 * @return array
	 */
	public function missing_list($limit = 1000)
	{
		$available = $this->gallery_available();
		$sql = $available
			? 'SELECT l.legacy_id, l.user_id, l.legacy_note, l.legacy_error, i.image_id, i.image_name, i.image_filename, i.image_username, i.image_time, a.album_name
				FROM ' . $this->tables['legacy'] . ' l
				LEFT JOIN ' . $this->gtable('images') . ' i ON (i.image_id = l.legacy_id)
				LEFT JOIN ' . $this->gtable('albums') . " a ON (a.album_id = i.image_album_id)
				WHERE l.legacy_type = 'image' AND l.new_id = 0 AND l.legacy_note <> 'dropped'
				ORDER BY l.legacy_id ASC"
			: 'SELECT l.legacy_id, l.user_id, l.legacy_note, l.legacy_error FROM ' . $this->tables['legacy'] . " l WHERE l.legacy_type = 'image' AND l.new_id = 0 AND l.legacy_note <> 'dropped' ORDER BY l.legacy_id ASC";

		$out = [];
		foreach ($this->fetch_limit($sql, $limit) as $row)
		{
			$filename = isset($row['image_filename']) ? (string) $row['image_filename'] : '';
			$path = $filename !== '' ? $this->gallery_source_dir() . basename($filename) : '';
			$info = ['width' => 0, 'height' => 0, 'size' => 0];

			if (!$available || empty($row['image_id']))
			{
				$reason = 'gone';
			}
			else if ($filename === '')
			{
				$reason = 'noname';
			}
			else if (!is_file($path))
			{
				$reason = 'missing';
			}
			else
			{
				$size = @getimagesize($path);
				$info = ['width' => $size ? (int) $size[0] : 0, 'height' => $size ? (int) $size[1] : 0, 'size' => (int) @filesize($path)];
				$note = isset($row['legacy_note']) ? (string) $row['legacy_note'] : '';
				$limit = $this->image->memory_limit();
				if (!$size)
				{
					$reason = 'unreadable';
				}
				else if ($note === 'memory' || ($limit > 0 && $this->image->memory_minimum($info['width'], $info['height']) > $limit))
				{
					$reason = 'memory';
				}
				else if ($note === 'started' || $note === 'crash')
				{
					$reason = 'crash';
				}
				else
				{
					$reason = 'refused';
				}
			}

			$out[] = [
				'image_id'	=> (int) $row['legacy_id'],
				'title'		=> isset($row['image_name']) ? (string) $row['image_name'] : '',
				'user_id'	=> (int) $row['user_id'],
				'username'	=> isset($row['image_username']) ? (string) $row['image_username'] : '',
				'album'		=> isset($row['album_name']) ? (string) $row['album_name'] : '',
				'time'		=> isset($row['image_time']) ? (int) $row['image_time'] : 0,
				'file'		=> $filename,
				'path'		=> $path !== '' ? 'files/phpbbgallery/core/source/' . basename($filename) : '',
				'reason'	=> $reason,
				'error'		=> isset($row['legacy_error']) ? (string) $row['legacy_error'] : '',
			] + $info;
		}

		return $out;
	}

	/**
	 * Oublie les échecs : ces photos seront retentées au prochain lancement de la bascule.
	 *
	 * @return int
	 */
	/**
	 * @param array $ids Photos de la galerie à retenter (vide = toutes celles en échec, hors abandonnées)
	 */
	public function retry_missing(array $ids = [])
	{
		$this->db->sql_query('DELETE FROM ' . $this->tables['legacy'] . " WHERE legacy_type = 'image' AND new_id = 0 AND legacy_note <> 'dropped'" . ($ids ? ' AND ' . $this->db->sql_in_set('legacy_id', array_map('intval', $ids)) : ''));
		$count = (int) $this->db->sql_affectedrows();
		$this->map = null;

		return $count;
	}

	/**
	 * Abandonne des photos en échec : elles sortent de la liste et ne sont plus retentées
	 * (leurs anciens liens affichent « Photo indisponible ») (v1.9.6).
	 */
	public function drop_missing(array $ids)
	{
		if (!$ids)
		{
			return 0;
		}
		$this->db->sql_query('UPDATE ' . $this->tables['legacy'] . " SET legacy_note = 'dropped'
			WHERE legacy_type = 'image' AND new_id = 0 AND " . $this->db->sql_in_set('legacy_id', array_map('intval', $ids)));

		return (int) $this->db->sql_affectedrows();
	}

	/**
	 * Copie tout de suite des photos en échec dans l'album « Photos récupérées » d'un membre (le repreneur),
	 * qui en devient l'auteur (v1.9.6). Limité en temps : le reste est indiqué pour un nouveau clic.
	 *
	 * @return array ['done' => n, 'failed' => [...], 'left' => n]
	 */
	public function assign_missing(array $ids, $owner_id, $strip_exif)
	{
		$ids = array_map('intval', $ids);
		$out = ['done' => 0, 'failed' => [], 'left' => 0];
		if (!$ids || !$this->gallery_available())
		{
			return $out;
		}
		if (function_exists('set_time_limit'))
		{
			@set_time_limit(0);
		}

		$rows = $this->fetch('SELECT i.* FROM ' . $this->gtable('images') . ' i
			INNER JOIN ' . $this->tables['legacy'] . " l ON (l.legacy_type = 'image' AND l.legacy_id = i.image_id AND l.new_id = 0)
			WHERE " . $this->db->sql_in_set('i.image_id', $ids) . '
			ORDER BY i.image_id ASC');
		$max_rating = max(1, (int) ($this->board_config['phpbb_gallery_max_rating'] ?: 10));
		$album_id = $this->fallback_album((int) $owner_id);
		$started = microtime(true);
		$this->watch_fatal();

		foreach ($rows as $k => $row)
		{
			if ($k > 0 && microtime(true) - $started > self::BATCH_SECONDS)
			{
				$out['left'] = count($rows) - $k;
				break;
			}
			$row['image_user_id'] = (int) $owner_id;
			$this->set_map('image', (int) $row['image_id'], 0, (int) $owner_id, 'started');
			$this->last_error = '';
			$this->last_message = '';
			$this->current = $row;
			try
			{
				$photo_id = $this->migrate_image($row, $album_id, $strip_exif, $max_rating);
			}
			catch (\Throwable $e)
			{
				$photo_id = 0;
				$this->last_error = 'crash';
				$this->last_message = $e->getMessage();
			}
			$this->current = null;
			if ($photo_id)
			{
				$out['done']++;
			}
			else
			{
				$reason = $this->last_error ?: 'refused';
				if ($reason === 'refused' && !is_file($this->gallery_source_dir() . basename((string) $row['image_filename'])))
				{
					$reason = 'missing';
				}
				$this->set_map('image', (int) $row['image_id'], 0, (int) $owner_id, $reason, $this->last_message);
				$out['failed'][] = ['image_id' => (int) $row['image_id'], 'title' => (string) $row['image_name'], 'reason' => $reason, 'message' => $this->last_message];
			}
		}
		$this->albums->resync($album_id);
		$this->map = null;

		return $out;
	}

	public function has_migrated()
	{
		return $this->count_map('image') > 0 || $this->count_map('album') > 0;
	}

	public function stats()
	{
		$stats = [
			'available'			=> $this->gallery_available(),
			'enabled'			=> $this->gallery_enabled(),
			'albums'			=> 0,
			'images'			=> 0,
			'users'				=> 0,
			'migrated_albums'	=> $this->count_map('album'),
			'migrated_images'	=> $this->count_map('image'),
			'missing_files'		=> $this->scalar('SELECT COUNT(legacy_id) AS n FROM ' . $this->tables['legacy'] . " WHERE legacy_type = 'image' AND new_id = 0 AND legacy_note <> 'dropped'"),
			'dropped_files'		=> $this->scalar('SELECT COUNT(legacy_id) AS n FROM ' . $this->tables['legacy'] . " WHERE legacy_type = 'image' AND new_id = 0 AND legacy_note = 'dropped'"),
			'migrated_comments'	=> $this->count_map('comment'),
			'bbcode'			=> $this->bbcode_state(),
			'rewritten_posts'	=> $this->count_rewritten('post'),
			'rewritten_sigs'	=> $this->count_rewritten('sig'),
		];

		if ($stats['available'])
		{
			$stats['albums'] = $this->scalar('SELECT COUNT(album_id) AS n FROM ' . $this->gtable('albums'));
			$stats['images'] = $this->scalar('SELECT COUNT(image_id) AS n FROM ' . $this->gtable('images') . ' WHERE image_status <> ' . self::GALLERY_STATUS_ORPHAN);
			$stats['users'] = $this->scalar('SELECT COUNT(DISTINCT image_user_id) AS n FROM ' . $this->gtable('images'));
		}

		return $stats;
	}

	/* ==================================================================
	 * Périmètre : tout, un groupe, un membre
	 * ================================================================== */

	/**
	 * @param string $type  all | group | user
	 * @param int    $value group_id ou user_id
	 * @return int[]|null Membres concernés (null = tout le forum)
	 */
	public function scope_users($type, $value)
	{
		if ($type === 'user')
		{
			return [(int) $value];
		}

		if ($type === 'group')
		{
			$ids = [];
			foreach ($this->fetch('SELECT user_id FROM ' . USER_GROUP_TABLE . ' WHERE group_id = ' . (int) $value . ' AND user_pending = 0') as $row)
			{
				$ids[] = (int) $row['user_id'];
			}

			return $ids;
		}

		return null;
	}

	protected function image_scope_where(?array $users, $include_public)
	{
		$where = 'i.image_status <> ' . self::GALLERY_STATUS_ORPHAN;
		if ($users !== null)
		{
			if (!$users)
			{
				return '1 = 0';
			}
			$where .= ' AND ' . $this->db->sql_in_set('i.image_user_id', $users);
			if (!$include_public)
			{
				$where .= ' AND a.album_user_id <> 0';
			}
		}

		return $where;
	}

	/**
	 * Simulation : ce qui sera copié (rien n'est modifié).
	 */
	public function plan(?array $users, $include_public)
	{
		if (!$this->gallery_available())
		{
			return ['images' => 0, 'done' => 0, 'remaining' => 0, 'albums' => 0, 'users' => 0, 'per_user' => []];
		}

		$where = $this->image_scope_where($users, $include_public);
		$from = $this->gtable('images') . ' i LEFT JOIN ' . $this->gtable('albums') . ' a ON (a.album_id = i.image_album_id)';

		$total = $this->scalar('SELECT COUNT(i.image_id) AS n FROM ' . $from . ' WHERE ' . $where);
		$done = $this->scalar('SELECT COUNT(i.image_id) AS n FROM ' . $from . '
			INNER JOIN ' . $this->tables['legacy'] . " l ON (l.legacy_type = 'image' AND l.legacy_id = i.image_id)
			WHERE " . $where);

		$album_where = $users === null ? '1 = 1' : ($users ? $this->db->sql_in_set('album_user_id', $users) : '1 = 0');
		$albums = $this->scalar('SELECT COUNT(album_id) AS n FROM ' . $this->gtable('albums') . ' WHERE ' . $album_where);

		$per_user = [];
		$sql = 'SELECT i.image_user_id, MAX(i.image_username) AS username, COUNT(i.image_id) AS total, COUNT(l.legacy_id) AS done
			FROM ' . $from . '
			LEFT JOIN ' . $this->tables['legacy'] . " l ON (l.legacy_type = 'image' AND l.legacy_id = i.image_id)
			WHERE " . $where . '
			GROUP BY i.image_user_id
			ORDER BY total DESC';
		foreach ($this->fetch_limit($sql, 50) as $row)
		{
			$per_user[] = ['user_id' => (int) $row['image_user_id'], 'username' => $row['username'], 'total' => (int) $row['total'], 'done' => (int) $row['done']];
		}

		return [
			'images'	=> $total,
			'done'		=> $done,
			'remaining'	=> max(0, $total - $done),
			'albums'	=> $albums,
			'users'		=> count($per_user),
			'per_user'	=> $per_user,
		];
	}

	/* ==================================================================
	 * Copie par lots
	 * ================================================================== */

	/**
	 * Copie un lot de photos (et les albums nécessaires).
	 *
	 * @param array|null $users          Membres (null = tout)
	 * @param bool       $include_public Copier aussi leurs photos des albums publics
	 * @param int        $limit          Taille du lot
	 * @param bool       $strip_exif     Ré-encoder les originaux pour retirer EXIF/GPS
	 * @return array ['migrated', 'skipped', 'remaining', 'total', 'done']
	 */
	public function run_batch(?array $users, $include_public, $limit, $strip_exif)
	{
		if (function_exists('set_time_limit'))
		{
			@set_time_limit(0);
		}

		// 1. Albums personnels du périmètre (même vides) : la structure est recréée en entier
		$album_where = $users === null ? '1 = 1' : ($users ? $this->db->sql_in_set('album_user_id', $users) : '1 = 0');
		foreach ($this->fetch('SELECT album_id FROM ' . $this->gtable('albums') . ' WHERE ' . $album_where . ' ORDER BY left_id ASC, album_id ASC') as $row)
		{
			try
			{
				$this->resolving = [];
				$this->ensure_album((int) $row['album_id'], false);
			}
			catch (\Throwable $e)
			{
				// Un album illisible ne bloque pas toute la bascule : ses photos seront mises de côté avec la raison
			}
		}

		// 2. Lot de photos pas encore copiées
		$sql = 'SELECT i.*
			FROM ' . $this->gtable('images') . ' i
			LEFT JOIN ' . $this->gtable('albums') . ' a ON (a.album_id = i.image_album_id)
			LEFT JOIN ' . $this->tables['legacy'] . " l ON (l.legacy_type = 'image' AND l.legacy_id = i.image_id)
			WHERE l.legacy_id IS NULL
				AND " . $this->image_scope_where($users, $include_public) . '
			ORDER BY i.image_id ASC';
		$rows = $this->fetch_limit($sql, max(1, (int) $limit));

		$migrated = 0;
		$skipped = 0;
		$touched = [];
		$max_rating = max(1, (int) ($this->board_config['phpbb_gallery_max_rating'] ?: 10));

		$started = microtime(true);
		$failed = [];
		$this->watch_fatal();
		foreach ($rows as $row)
		{
			// Lot limité en temps : les grosses photos sont longues à traiter sur un hébergement mutualisé,
			// et le serveur web coupe (erreur 500) bien avant la limite de PHP (v1.9.2)
			if ($migrated + $skipped > 0 && microtime(true) - $started > self::BATCH_SECONDS)
			{
				break;
			}

			// Fichier absent : aucun album n'est créé pour rien
			$source = $this->gallery_source_dir() . basename((string) $row['image_filename']);
			if ($row['image_filename'] === '' || !is_file($source))
			{
				$this->set_map('image', (int) $row['image_id'], 0, (int) $row['image_user_id'], 'missing');
				$failed[] = ['image_id' => (int) $row['image_id'], 'title' => (string) $row['image_name'], 'reason' => 'missing', 'message' => 'files/phpbbgallery/core/source/' . basename((string) $row['image_filename'])];
				$skipped++;
				continue;
			}

			// Noté « en cours » avant le traitement : si PHP s'arrête sur cette photo (mémoire, temps),
			// le lot suivant passe à la suivante au lieu de replanter indéfiniment sur la même
			$this->set_map('image', (int) $row['image_id'], 0, (int) $row['image_user_id'], 'started');
			$this->last_error = '';
			$this->last_message = '';
			$this->current = $row;

			try
			{
				// L'album racine personnel n'est créé que s'il contient des photos
				$this->resolving = [];
				$album_id = $this->ensure_album((int) $row['image_album_id'], true);
				if (!$album_id)
				{
					// Album supprimé ou introuvable dans phpBB Gallery : la photo n'est pas perdue,
					// elle va dans un album « Photos récupérées » de son auteur (v1.9.6)
					$album_id = $this->fallback_album((int) $row['image_user_id']);
				}
				$photo_id = $album_id ? $this->migrate_image($row, $album_id, $strip_exif, $max_rating) : 0;
			}
			catch (\Throwable $e)
			{
				$photo_id = 0;
				$this->last_error = 'crash';
				$this->last_message = $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
			}

			$this->current = null;
			if ($photo_id)
			{
				$migrated++;
				$touched[$album_id] = true;
			}
			else
			{
				// Échec noté avec sa raison pour ne pas le retenter indéfiniment (ACP › Voir la liste)
				$this->set_map('image', (int) $row['image_id'], 0, (int) $row['image_user_id'], $this->last_error ?: 'refused', $this->last_message);
				$failed[] = ['image_id' => (int) $row['image_id'], 'title' => (string) $row['image_name'], 'reason' => $this->last_error ?: 'refused', 'message' => $this->last_message];
				$skipped++;
			}
		}

		foreach (array_keys($touched) as $album_id)
		{
			$this->albums->resync($album_id);
		}
		$this->map = null;

		$plan = $this->plan($users, $include_public);

		return [
			'migrated'	=> $migrated,
			'skipped'	=> $skipped,
			'remaining'	=> $plan['remaining'],
			'total'		=> $plan['images'],
			'done'		=> $plan['remaining'] === 0,
			'failed'	=> $failed,
			'set_aside'	=> $this->scalar('SELECT COUNT(legacy_id) AS n FROM ' . $this->tables['legacy'] . " WHERE legacy_type = 'image' AND new_id = 0 AND legacy_note <> 'dropped'"),
		];
	}

	/**
	 * Album racine personnel de phpBB Gallery (conteneur « galerie de X ») ?
	 * Dans le Portfolio, ce conteneur est la page du membre : ses sous-albums deviennent des albums principaux.
	 */
	protected function is_personal_root(array $album)
	{
		return (int) $album['parent_id'] === 0 && (int) $album['album_user_id'] > 0;
	}

	/**
	 * Crée (une seule fois) l'album Portfolio correspondant à un album phpBB Gallery.
	 *
	 * @param bool $for_photo L'album doit recevoir une photo (crée aussi un album racine personnel)
	 * @return int album_id Portfolio (0 si impossible)
	 */
	public function ensure_album($gallery_album_id, $for_photo)
	{
		$gallery_album_id = (int) $gallery_album_id;
		$existing = $this->get_map('album', $gallery_album_id);
		if ($existing && $this->albums->get($existing))
		{
			return $existing;
		}
		if (!$for_photo && $this->get_map('uroot', $gallery_album_id))
		{
			return 0;
		}

		$rows = $this->fetch('SELECT * FROM ' . $this->gtable('albums') . ' WHERE album_id = ' . $gallery_album_id);
		if (!$rows)
		{
			return 0;
		}
		$album = $rows[0];
		$owner_id = (int) $album['album_user_id'];

		if ($this->is_personal_root($album))
		{
			// Lien vers la galerie du membre => page du membre dans le Portfolio (une seule fois)
			if ($this->get_map('uroot', $gallery_album_id) !== $owner_id)
			{
				$this->set_map('uroot', $gallery_album_id, $owner_id, $owner_id);
			}
			if (!$for_photo)
			{
				return 0;
			}
		}

		$parent_id = 0;
		// Arborescence abîmée dans phpBB Gallery (album parent de lui-même, ou A parent de B parent de A) :
		// sans cette garde, la recréation tournait en rond jusqu'à l'erreur 500 (v1.9.5). L'album est alors
		// recréé à la racine.
		$this->resolving[$gallery_album_id] = true;
		if ((int) $album['parent_id'] && (int) $album['parent_id'] !== $gallery_album_id && empty($this->resolving[(int) $album['parent_id']]))
		{
			$parents = $this->fetch('SELECT * FROM ' . $this->gtable('albums') . ' WHERE album_id = ' . (int) $album['parent_id']);
			if ($parents && !$this->is_personal_root($parents[0]))
			{
				$parent_id = $this->ensure_album((int) $album['parent_id'], false);
			}
		}
		unset($this->resolving[$gallery_album_id]);

		// Accès phpBB Gallery : tous 0, inscrits 1, non-ignorés 2, amis 3, amis spéciaux 4
		switch ((int) $album['album_auth_access'])
		{
			case 1:
			case 2:
				$visibility = access::VIS_REGISTERED;
			break;
			case 3:
			case 4:
				$visibility = $owner_id ? access::VIS_FRIENDS : access::VIS_REGISTERED;
			break;
			default:
				$visibility = access::VIS_ALL;
		}

		$row = [
			'parent_id'				=> $parent_id,
			'owner_id'				=> $owner_id,
			'album_name'			=> $album['album_name'] !== '' ? $album['album_name'] : 'Album',
			'album_desc'			=> (string) $album['album_desc'],
			'album_desc_uid'		=> (string) $album['album_desc_uid'],
			'album_desc_bitfield'	=> (string) $album['album_desc_bitfield'],
			'album_desc_options'	=> (int) $album['album_desc_options'],
			'album_visibility'		=> $visibility,
			'album_locked'			=> (int) $album['album_status'] === 1 ? 1 : 0,
			'album_order'			=> (int) $album['left_id'],
			'album_sort'			=> 'date_desc',
			'album_time'			=> (int) $album['album_last_image_time'] ?: time(),
		];

		$this->db->sql_query('INSERT INTO ' . $this->tables['albums'] . ' ' . $this->db->sql_build_array('INSERT', $row));
		$new_id = (int) $this->db->sql_nextid();
		$this->albums->reset();

		$this->set_map('album', $gallery_album_id, $new_id, $owner_id);

		return $new_id;
	}

	protected function migrate_image(array $row, $album_id, $strip_exif, $max_rating)
	{
		$source = $this->gallery_source_dir() . basename((string) $row['image_filename']);
		if ($row['image_filename'] === '' || !is_file($source))
		{
			return 0;
		}

		$size = @getimagesize($source);
		if ($size && !$this->image->ensure_memory((int) $size[0], (int) $size[1]))
		{
			$this->last_error = 'memory';
			$this->last_message = (int) $size[0] . ' × ' . (int) $size[1] . ' px, memory_limit ' . @ini_get('memory_limit');

			return 0;
		}

		$info = $strip_exif ? $this->image->process_file($source, $row['image_filename'], false) : $this->image->import_original($source);
		if (!$info || isset($info['error']))
		{
			$this->last_error = !$size ? 'unreadable' : (isset($info['error']) && $info['error'] === 'PORTFOLIO_ERR_MEMORY' ? 'memory' : 'refused');
			$this->last_message = isset($info['error']) ? (string) $info['error'] : '';

			return 0;
		}

		$status = (int) $row['image_status'] === self::GALLERY_STATUS_UNAPPROVED ? photo_manager::STATUS_PENDING : photo_manager::STATUS_APPROVED;

		$photo = array_merge([
			'photo_realname'		=> $row['image_filename'],
			'photo_exif_stripped'	=> $strip_exif ? 1 : 0,
		], $info, [
			'album_id'				=> (int) $album_id,
			'user_id'				=> (int) $row['image_user_id'],
			'photo_title'			=> $row['image_name'] !== '' ? $row['image_name'] : 'Photo',
			'photo_desc'			=> (string) $row['image_desc'],
			'photo_desc_uid'		=> (string) $row['image_desc_uid'],
			'photo_desc_bitfield'	=> (string) $row['image_desc_bitfield'],
			'photo_desc_options'	=> 7,
			'photo_time'			=> (int) $row['image_time'] ?: time(),
			'photo_status'			=> $status,
			'photo_prev_status'		=> $status,
			'photo_views'			=> (int) $row['image_view_count'],
		]);
		unset($photo['error']);

		$this->db->sql_query('INSERT INTO ' . $this->tables['photos'] . ' ' . $this->db->sql_build_array('INSERT', $photo));
		$photo_id = (int) $this->db->sql_nextid();
		$this->set_map('image', (int) $row['image_id'], $photo_id, (int) $row['image_user_id']);

		// Notes : échelle phpBB Gallery (1 à max_rating, 10 par défaut) convertie en 1 à 5 étoiles
		$sum = 0;
		$count = 0;
		foreach ($this->fetch('SELECT rate_user_id, rate_point FROM ' . $this->gtable('rates') . ' WHERE rate_image_id = ' . (int) $row['image_id']) as $rate)
		{
			$stars = max(1, min(5, (int) round((int) $rate['rate_point'] * 5 / $max_rating)));
			$this->db->sql_return_on_error(true);
			$this->db->sql_query('INSERT INTO ' . $this->tables['ratings'] . ' ' . $this->db->sql_build_array('INSERT', [
				'photo_id'		=> $photo_id,
				'user_id'		=> (int) $rate['rate_user_id'],
				'rating'		=> $stars,
				'rating_time'	=> (int) $row['image_time'],
			]));
			if (!$this->db->get_sql_error_triggered())
			{
				$sum += $stars;
				$count++;
			}
			$this->db->sql_return_on_error(false);
		}

		// Commentaires, avec leur auteur et leur date
		$comments = 0;
		foreach ($this->fetch('SELECT * FROM ' . $this->gtable('comments') . ' WHERE comment_image_id = ' . (int) $row['image_id'] . ' ORDER BY comment_id ASC') as $comment)
		{
			$this->db->sql_query('INSERT INTO ' . $this->tables['comments'] . ' ' . $this->db->sql_build_array('INSERT', [
				'photo_id'			=> $photo_id,
				'user_id'			=> (int) $comment['comment_user_id'],
				'comment_text'		=> (string) $comment['comment'],
				'comment_uid'		=> (string) $comment['comment_uid'],
				'comment_bitfield'	=> (string) $comment['comment_bitfield'],
				'comment_options'	=> 7,
				'comment_time'		=> (int) $comment['comment_time'],
				'comment_edit_time'	=> (int) $comment['comment_edit_time'],
			]));
			$this->set_map('comment', (int) $comment['comment_id'], (int) $this->db->sql_nextid(), (int) $comment['comment_user_id']);
			$comments++;
		}

		$this->db->sql_query('UPDATE ' . $this->tables['photos'] . ' SET ' . $this->db->sql_build_array('UPDATE', [
			'photo_rating_sum'		=> $sum,
			'photo_rating_count'	=> $count,
			'photo_rating_avg'		=> $count ? (int) round($sum * 100 / $count) : 0,
			'photo_comments'		=> $comments,
		]) . ' WHERE photo_id = ' . $photo_id);

		return $photo_id;
	}

	/**
	 * Annule la bascule : supprime du Portfolio les seules copies créées par la bascule.
	 * Les données de phpBB Gallery n'ont jamais été modifiées.
	 *
	 * @return array ['photos' => n, 'albums' => n]
	 */
	public function undo()
	{
		$photo_ids = [];
		foreach ($this->fetch('SELECT new_id FROM ' . $this->tables['legacy'] . " WHERE legacy_type = 'image' AND new_id > 0") as $row)
		{
			$photo_ids[] = (int) $row['new_id'];
		}

		$photos = 0;
		foreach (array_chunk($photo_ids, 200) as $chunk)
		{
			foreach ($this->fetch('SELECT photo_id, photo_file FROM ' . $this->tables['photos'] . ' WHERE ' . $this->db->sql_in_set('photo_id', $chunk)) as $row)
			{
				$this->image->delete_files($row['photo_file']);
				$photos++;
			}
			foreach (['photos', 'ratings', 'comments', 'reactions'] as $table)
			{
				$this->db->sql_query('DELETE FROM ' . $this->tables[$table] . ' WHERE ' . $this->db->sql_in_set('photo_id', $chunk));
			}
		}

		// Albums créés par la bascule, des plus profonds aux racines, s'ils sont vides
		$album_ids = [];
		foreach ($this->fetch('SELECT new_id FROM ' . $this->tables['legacy'] . " WHERE legacy_type IN ('album', 'fallback') AND new_id > 0") as $row)
		{
			$album_ids[] = (int) $row['new_id'];
		}
		$this->albums->reset();
		usort($album_ids, function ($a, $b) {
			return $this->albums->depth($b) - $this->albums->depth($a);
		});

		$albums = 0;
		foreach ($album_ids as $album_id)
		{
			if ($this->albums->get($album_id) && $this->albums->delete($album_id) === '')
			{
				$albums++;
			}
		}

		$this->db->sql_query('DELETE FROM ' . $this->tables['legacy']);
		$this->map = null;

		return ['photos' => $photos, 'albums' => $albums];
	}

	/* ==================================================================
	 * Correspondances (redirections des anciens liens)
	 * ================================================================== */

	public function get_map($type, $legacy_id)
	{
		$map = $this->load_map();

		return isset($map[$type][(int) $legacy_id]) ? $map[$type][(int) $legacy_id] : 0;
	}

	/**
	 * URL Portfolio correspondant à un ancien lien phpBB Gallery (chemin relatif à app.php, sans « / » initial),
	 * ou chaîne vide si l'élément n'a pas été basculé.
	 *
	 * @param string $kind image | album
	 * @param string $size '' (page), mini, medium, source
	 */
	public function legacy_target($kind, $legacy_id, $size = '')
	{
		if ($kind === 'image')
		{
			$photo_id = $this->get_map('image', $legacy_id);
			if (!$photo_id)
			{
				return '';
			}
			if ($size === 'mini' || $size === 'medium' || $size === 'source')
			{
				return 'portfolio/file/' . $photo_id . '/' . ($size === 'mini' ? 'thumb' : $size);
			}

			return 'portfolio/photo/' . $photo_id;
		}

		$album_id = $this->get_map('album', $legacy_id);
		if ($album_id)
		{
			return 'portfolio/album/' . $album_id;
		}

		$user_id = $this->get_map('uroot', $legacy_id);

		return $user_id ? 'portfolio/user/' . $user_id : '';
	}

	protected function set_map($type, $legacy_id, $new_id, $user_id, $note = '', $error = '')
	{
		$this->db->sql_query('DELETE FROM ' . $this->tables['legacy'] . " WHERE legacy_type = '" . $this->db->sql_escape($type) . "' AND legacy_id = " . (int) $legacy_id);
		$this->db->sql_query('INSERT INTO ' . $this->tables['legacy'] . ' ' . $this->db->sql_build_array('INSERT', [
			'legacy_type'	=> $type,
			'legacy_id'		=> (int) $legacy_id,
			'new_id'		=> (int) $new_id,
			'user_id'		=> (int) $user_id,
			'legacy_time'	=> time(),
			'legacy_note'	=> (string) $note,
			'legacy_error'	=> truncate_string((string) $error, 255, 255, false),
		]));
		if ($this->map !== null)
		{
			$this->map[$type][(int) $legacy_id] = (int) $new_id;
		}
	}

	protected function count_map($type)
	{
		return $this->scalar('SELECT COUNT(legacy_id) AS n FROM ' . $this->tables['legacy'] . " WHERE legacy_type = '" . $this->db->sql_escape($type) . "' AND new_id > 0");
	}

	protected function load_map()
	{
		if ($this->map === null)
		{
			$this->map = ['image' => [], 'album' => [], 'uroot' => [], 'fallback' => []];
			foreach ($this->fetch('SELECT legacy_type, legacy_id, new_id FROM ' . $this->tables['legacy'] . " WHERE new_id > 0 AND legacy_type IN ('image', 'album', 'uroot', 'fallback')") as $row)
			{
				$this->map[$row['legacy_type']][(int) $row['legacy_id']] = (int) $row['new_id'];
			}
		}

		return $this->map;
	}

	/* ==================================================================
	 * BBCode [image]N[/image] de phpBB Gallery
	 * ================================================================== */

	/**
	 * missing (aucun BBCode [image]), gallery (celui de phpBB Gallery), portfolio (repris par le Portfolio)
	 */
	public function bbcode_state()
	{
		$rows = $this->fetch('SELECT bbcode_tpl FROM ' . BBCODES_TABLE . " WHERE LOWER(bbcode_tag) = 'image'");
		if (!$rows)
		{
			return 'missing';
		}

		return strpos($rows[0]['bbcode_tpl'], 'portfolio/legacy/image') !== false ? 'portfolio' : 'gallery';
	}

	/**
	 * Crée ou met à jour le BBCode [image] pour qu'il passe par le Portfolio.
	 * Les messages n'ont pas besoin d'être modifiés : phpBB réaffiche le BBCode avec le nouveau modèle.
	 * Une photo pas encore basculée continue d'être servie par phpBB Gallery tant que celle-ci est installée.
	 */
	public function install_bbcode()
	{
		$url = generate_board_url() . '/' . ($this->board_config['enable_mod_rewrite'] ? '' : 'app.php/') . 'portfolio/legacy/image/';

		$fields = [
			'bbcode_tag'			=> 'image',
			'bbcode_helpline'		=> '',
			'display_on_posting'	=> 0,
			'bbcode_match'			=> '[image]{NUMBER}[/image]',
			'bbcode_tpl'			=> '<a href="' . $url . '{NUMBER}"><img src="' . $url . '{NUMBER}/mini" alt="{NUMBER}" /></a>',
			'first_pass_match'		=> '!\[image\]([0-9]+)\[/image\]!i',
			'first_pass_replace'	=> '[image:$uid]${1}[/image:$uid]',
			'second_pass_match'		=> '!\[image:$uid\]([0-9]+)\[/image:$uid\]!s',
			'second_pass_replace'	=> '<a href="' . $url . '${1}"><img src="' . $url . '${1}/mini" alt="${1}" /></a>',
		];

		$rows = $this->fetch('SELECT bbcode_id FROM ' . BBCODES_TABLE . " WHERE LOWER(bbcode_tag) = 'image'");
		if ($rows)
		{
			$this->db->sql_query('UPDATE ' . BBCODES_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', $fields) . ' WHERE bbcode_id = ' . (int) $rows[0]['bbcode_id']);
		}
		else
		{
			$max = $this->scalar('SELECT MAX(bbcode_id) AS n FROM ' . BBCODES_TABLE);
			$fields['bbcode_id'] = max(NUM_CORE_BBCODES + 1, $max + 1);
			if ($fields['bbcode_id'] > BBCODE_LIMIT)
			{
				return false;
			}
			$this->db->sql_query('INSERT INTO ' . BBCODES_TABLE . ' ' . $this->db->sql_build_array('INSERT', $fields));
		}

		// Le moteur de rendu des messages garde les BBCode en cache
		$this->cache->purge();

		return true;
	}

	/**
	 * Après la suppression des données de phpBB Gallery (qui efface le BBCode [image]) : le recrée si des photos ont été basculées.
	 */
	public function ensure_bbcode()
	{
		if ($this->has_migrated() && $this->bbcode_state() !== 'portfolio')
		{
			return $this->install_bbcode();
		}

		return false;
	}

	/* ==================================================================
	 * Réécriture des liens dans les messages et signatures (facultative, annulable)
	 * ================================================================== */

	public function rewrite_text($text, &$changes)
	{
		$text = preg_replace_callback('#(app\.php/)?gallery/image/(\d+)(?:/(mini|medium|source)|/\d+)?(?![\d/a-z])#i', function ($m) use (&$changes) {
			$target = $this->legacy_target('image', (int) $m[2], isset($m[3]) ? strtolower($m[3]) : '');
			if ($target === '')
			{
				return $m[0];
			}
			$changes++;

			return $m[1] . $target;
		}, $text);

		return preg_replace_callback('#(app\.php/)?gallery/album/(\d+)(?![\d])#i', function ($m) use (&$changes) {
			$target = $this->legacy_target('album', (int) $m[2]);
			if ($target === '')
			{
				return $m[0];
			}
			$changes++;

			return $m[1] . $target;
		}, $text);
	}

	/**
	 * @param bool $dry_run Compter seulement
	 * @return array ['posts' => n, 'sigs' => n, 'links' => n]
	 */
	public function rewrite_links($dry_run)
	{
		$stats = ['posts' => 0, 'sigs' => 0, 'links' => 0];
		$like = $this->db->sql_like_expression($this->db->get_any_char() . 'gallery/' . $this->db->get_any_char());

		foreach ($this->fetch('SELECT post_id, post_text FROM ' . POSTS_TABLE . ' WHERE post_text ' . $like) as $row)
		{
			$changes = 0;
			$new = $this->rewrite_text($row['post_text'], $changes);
			if (!$changes)
			{
				continue;
			}
			$stats['posts']++;
			$stats['links'] += $changes;
			if (!$dry_run)
			{
				$this->keep_original('post', (int) $row['post_id'], $row['post_text']);
				$this->db->sql_query('UPDATE ' . POSTS_TABLE . " SET post_text = '" . $this->db->sql_escape($new) . "' WHERE post_id = " . (int) $row['post_id']);
			}
		}

		foreach ($this->fetch('SELECT user_id, user_sig FROM ' . USERS_TABLE . ' WHERE user_sig ' . $like) as $row)
		{
			$changes = 0;
			$new = $this->rewrite_text($row['user_sig'], $changes);
			if (!$changes)
			{
				continue;
			}
			$stats['sigs']++;
			$stats['links'] += $changes;
			if (!$dry_run)
			{
				$this->keep_original('sig', (int) $row['user_id'], $row['user_sig']);
				$this->db->sql_query('UPDATE ' . USERS_TABLE . " SET user_sig = '" . $this->db->sql_escape($new) . "' WHERE user_id = " . (int) $row['user_id']);
			}
		}

		if (!$dry_run && ($stats['posts'] || $stats['sigs']))
		{
			$this->cache->purge();
		}

		return $stats;
	}

	/**
	 * Remet les messages et signatures exactement comme avant la réécriture.
	 */
	public function restore_links()
	{
		$count = 0;
		foreach ($this->fetch('SELECT * FROM ' . $this->tables['legacy_texts']) as $row)
		{
			if ($row['text_type'] === 'post')
			{
				$this->db->sql_query('UPDATE ' . POSTS_TABLE . " SET post_text = '" . $this->db->sql_escape($row['original_text']) . "' WHERE post_id = " . (int) $row['text_id']);
			}
			else
			{
				$this->db->sql_query('UPDATE ' . USERS_TABLE . " SET user_sig = '" . $this->db->sql_escape($row['original_text']) . "' WHERE user_id = " . (int) $row['text_id']);
			}
			$count++;
		}
		$this->db->sql_query('DELETE FROM ' . $this->tables['legacy_texts']);
		$this->cache->purge();

		return $count;
	}

	protected function keep_original($type, $id, $text)
	{
		// Seule la toute première version est gardée : une seconde réécriture n'écrase pas l'original
		$exists = $this->scalar('SELECT COUNT(text_id) AS n FROM ' . $this->tables['legacy_texts'] . " WHERE text_type = '" . $this->db->sql_escape($type) . "' AND text_id = " . (int) $id);
		if (!$exists)
		{
			$this->db->sql_query('INSERT INTO ' . $this->tables['legacy_texts'] . ' ' . $this->db->sql_build_array('INSERT', [
				'text_type'		=> $type,
				'text_id'		=> (int) $id,
				'original_text'	=> $text,
				'rewrite_time'	=> time(),
			]));
		}
	}

	public function count_rewritten_total()
	{
		return $this->scalar('SELECT COUNT(text_id) AS n FROM ' . $this->tables['legacy_texts']);
	}

	protected function count_rewritten($type)
	{
		return $this->scalar('SELECT COUNT(text_id) AS n FROM ' . $this->tables['legacy_texts'] . " WHERE text_type = '" . $this->db->sql_escape($type) . "'");
	}

	/* ==================================================================
	 * Outils
	 * ================================================================== */

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

	protected function fetch_limit($sql, $limit)
	{
		$rows = [];
		$result = $this->db->sql_query_limit($sql, (int) $limit);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function scalar($sql)
	{
		$result = $this->db->sql_query($sql);
		$value = (int) $this->db->sql_fetchfield('n');
		$this->db->sql_freeresult($result);

		return $value;
	}
}
