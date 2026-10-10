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
			'missing_files'		=> $this->scalar('SELECT COUNT(legacy_id) AS n FROM ' . $this->tables['legacy'] . " WHERE legacy_type = 'image' AND new_id = 0"),
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
			$this->ensure_album((int) $row['album_id'], false);
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

		foreach ($rows as $row)
		{
			// Fichier absent : aucun album n'est créé pour rien
			$source = $this->gallery_source_dir() . basename((string) $row['image_filename']);
			if ($row['image_filename'] === '' || !is_file($source))
			{
				$this->set_map('image', (int) $row['image_id'], 0, (int) $row['image_user_id']);
				$skipped++;
				continue;
			}

			// L'album racine personnel n'est créé que s'il contient des photos
			$album_id = $this->ensure_album((int) $row['image_album_id'], true);
			$photo_id = $album_id ? $this->migrate_image($row, $album_id, $strip_exif, $max_rating) : 0;
			if ($photo_id)
			{
				$migrated++;
				$touched[$album_id] = true;
			}
			else
			{
				// Fichier introuvable ou illisible : noté pour ne pas le retenter indéfiniment
				$this->set_map('image', (int) $row['image_id'], 0, (int) $row['image_user_id']);
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

		$rows = $this->fetch('SELECT * FROM ' . $this->gtable('albums') . ' WHERE album_id = ' . $gallery_album_id);
		if (!$rows)
		{
			return 0;
		}
		$album = $rows[0];
		$owner_id = (int) $album['album_user_id'];

		if ($this->is_personal_root($album))
		{
			// Lien vers la galerie du membre => page du membre dans le Portfolio
			$this->set_map('uroot', $gallery_album_id, $owner_id, $owner_id);
			if (!$for_photo)
			{
				return 0;
			}
		}

		$parent_id = 0;
		if ((int) $album['parent_id'])
		{
			$parents = $this->fetch('SELECT * FROM ' . $this->gtable('albums') . ' WHERE album_id = ' . (int) $album['parent_id']);
			if ($parents && !$this->is_personal_root($parents[0]))
			{
				$parent_id = $this->ensure_album((int) $album['parent_id'], false);
			}
		}

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

		$info = $strip_exif ? $this->image->process_file($source, $row['image_filename']) : $this->image->import_original($source);
		if (!$info || isset($info['error']))
		{
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
		foreach ($this->fetch('SELECT new_id FROM ' . $this->tables['legacy'] . " WHERE legacy_type = 'album' AND new_id > 0") as $row)
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

	protected function set_map($type, $legacy_id, $new_id, $user_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->tables['legacy'] . " WHERE legacy_type = '" . $this->db->sql_escape($type) . "' AND legacy_id = " . (int) $legacy_id);
		$this->db->sql_query('INSERT INTO ' . $this->tables['legacy'] . ' ' . $this->db->sql_build_array('INSERT', [
			'legacy_type'	=> $type,
			'legacy_id'		=> (int) $legacy_id,
			'new_id'		=> (int) $new_id,
			'user_id'		=> (int) $user_id,
			'legacy_time'	=> time(),
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
			$this->map = ['image' => [], 'album' => [], 'uroot' => []];
			foreach ($this->fetch('SELECT legacy_type, legacy_id, new_id FROM ' . $this->tables['legacy'] . " WHERE new_id > 0 AND legacy_type IN ('image', 'album', 'uroot')") as $row)
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
