<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Photos : ajout, édition, déplacement, corbeille, restauration, purge définitive,
 * notation, commentaires et alerte de suppression massive.
 */
class photo_manager
{
	const STATUS_PENDING	= 0;
	const STATUS_APPROVED	= 1;
	const STATUS_TRASH		= 2;

	/** Réactions disponibles (code => émoji) ; la liste active se règle dans l'ACP (v1.5.0) */
	const REACTIONS = [
		'like'		=> '👍',
		'love'		=> '❤️',
		'haha'		=> '😀',
		'wow'		=> '😲',
		'sad'		=> '😢',
		'clap'		=> '👏',
		'laugh'		=> '😂',
		'fire'		=> '🔥',
		'star'		=> '⭐',
		'thanks'	=> '🙏',
		'cool'		=> '😎',
		'camera'	=> '📷',
	];

	/** @var string */
	protected $reactions_table = '';

	public function set_reactions_table($table)
	{
		$this->reactions_table = $table;
	}

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \verturin\portfolio\service\settings Réglages du Portfolio */
	protected $config;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\notification\manager */
	protected $notifications;

	/** @var \phpbb\event\dispatcher_interface */
	protected $dispatcher;

	/** @var album_manager */
	protected $albums;

	/** @var image_processor */
	protected $image;

	protected $photos_table;
	protected $ratings_table;
	protected $comments_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, \verturin\portfolio\service\settings $config, \phpbb\user $user, \phpbb\cache\driver\driver_interface $cache, \phpbb\log\log_interface $log, \phpbb\notification\manager $notifications, \phpbb\event\dispatcher_interface $dispatcher, album_manager $albums, image_processor $image, $photos_table, $ratings_table, $comments_table)
	{
		$this->db = $db;
		$this->config = $config;
		$this->user = $user;
		$this->cache = $cache;
		$this->log = $log;
		$this->notifications = $notifications;
		$this->dispatcher = $dispatcher;
		$this->albums = $albums;
		$this->image = $image;
		$this->photos_table = $photos_table;
		$this->ratings_table = $ratings_table;
		$this->comments_table = $comments_table;
	}

	/* ------------------------------------------------------------------
	 * Lecture
	 * ------------------------------------------------------------------ */

	public function get($photo_id)
	{
		$sql = 'SELECT * FROM ' . $this->photos_table . ' WHERE photo_id = ' . (int) $photo_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	public function get_many(array $ids)
	{
		$ids = array_filter(array_map('intval', $ids));
		if (!$ids)
		{
			return [];
		}

		$rows = [];
		$sql = 'SELECT * FROM ' . $this->photos_table . ' WHERE ' . $this->db->sql_in_set('photo_id', array_unique($ids));
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['photo_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function order_by($sort)
	{
		switch ($sort)
		{
			case 'date_asc':
				return 'photo_time ASC, photo_id ASC';
			case 'title':
				return 'photo_title ASC, photo_id ASC';
			case 'rating':
				return 'photo_rating_avg DESC, photo_rating_count DESC, photo_id DESC';
			case 'views':
				return 'photo_views DESC, photo_id DESC';
			default:
				return 'photo_time DESC, photo_id DESC';
		}
	}

	/**
	 * Photos d'un album.
	 *
	 * @param array $statuses Statuts à inclure
	 * @param int   $owner_id Si > 0, inclut aussi les photos en attente de cet utilisateur
	 */
	public function list_album($album_id, $sort, $start, $limit, array $statuses = [self::STATUS_APPROVED], $owner_id = 0)
	{
		$where = $this->album_where($album_id, $statuses, $owner_id);

		$sql = 'SELECT COUNT(photo_id) AS total FROM ' . $this->photos_table . ' WHERE ' . $where;
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$rows = [];
		$sql = 'SELECT * FROM ' . $this->photos_table . ' WHERE ' . $where . ' ORDER BY ' . $this->order_by($sort);
		$result = $this->db->sql_query_limit($sql, (int) $limit, (int) $start);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['photo_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return [$rows, $total];
	}

	protected function album_where($album_id, array $statuses, $owner_id)
	{
		$where = 'album_id = ' . (int) $album_id . ' AND (' . $this->db->sql_in_set('photo_status', $statuses);
		if ($owner_id && !in_array(self::STATUS_PENDING, $statuses, true))
		{
			$where .= ' OR (photo_status = ' . self::STATUS_PENDING . ' AND user_id = ' . (int) $owner_id . ')';
		}

		return $where . ')';
	}

	/**
	 * Identifiants des photos publiées d'un album, dans l'ordre d'affichage.
	 */
	public function sequence($album_id, $sort)
	{
		$ids = [];
		$sql = 'SELECT photo_id FROM ' . $this->photos_table . '
			WHERE album_id = ' . (int) $album_id . '
				AND photo_status = ' . self::STATUS_APPROVED . '
			ORDER BY ' . $this->order_by($sort);
		$result = $this->db->sql_query_limit($sql, 10000);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['photo_id'];
		}
		$this->db->sql_freeresult($result);

		return $ids;
	}

	/**
	 * Navigation dans l'album : précédente, suivante, première, dernière, position et pellicule.
	 *
	 * @param int $radius Nombre de vignettes de part et d'autre dans la pellicule
	 */
	public function navigation(array $photo, $sort, $radius = 5)
	{
		$ids = $this->sequence($photo['album_id'], $sort);
		$count = count($ids);
		$pos = array_search((int) $photo['photo_id'], $ids, true);

		$nav = ['prev' => 0, 'next' => 0, 'first' => 0, 'last' => 0, 'position' => 0, 'count' => $count, 'strip' => []];
		if ($pos === false)
		{
			return $nav;
		}

		$nav['prev'] = isset($ids[$pos - 1]) ? $ids[$pos - 1] : 0;
		$nav['next'] = isset($ids[$pos + 1]) ? $ids[$pos + 1] : 0;
		$nav['first'] = $pos > 0 ? $ids[0] : 0;
		$nav['last'] = $pos < $count - 1 ? $ids[$count - 1] : 0;
		$nav['position'] = $pos + 1;

		// Fenêtre centrée sur la photo courante (décalée aux extrémités)
		$size = $radius * 2 + 1;
		$from = max(0, min($pos - $radius, $count - $size));
		$nav['strip'] = array_slice($ids, $from, $size);

		return $nav;
	}

	/**
	 * Photos précédente et suivante (compatibilité 1.0/1.1).
	 */
	public function neighbours(array $photo, $sort)
	{
		$nav = $this->navigation($photo, $sort, 0);

		return [$nav['prev'], $nav['next'], $nav['position'], $nav['count']];
	}

	/**
	 * Dernières photos ou mieux notées parmi des albums visibles.
	 */
	public function list_in_albums(array $album_ids, $order, $limit, $user_id = 0)
	{
		if (!$album_ids)
		{
			return [];
		}

		$where = $this->db->sql_in_set('album_id', $album_ids) . ' AND photo_status = ' . self::STATUS_APPROVED;
		if ($user_id)
		{
			$where .= ' AND user_id = ' . (int) $user_id;
		}
		if ($order === 'rating')
		{
			$where .= ' AND photo_rating_count > 0';
		}

		$rows = [];
		$sql = 'SELECT * FROM ' . $this->photos_table . ' WHERE ' . $where . ' ORDER BY ' . $this->order_by($order === 'rating' ? 'rating' : 'date_desc');
		$result = $this->db->sql_query_limit($sql, (int) $limit);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['photo_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Photos au hasard parmi les albums donnés (pages Portfolio, v1.8.0).
	 */
	public function list_random(array $album_ids, $limit)
	{
		if (!$album_ids)
		{
			return [];
		}

		$ids = [];
		$sql = 'SELECT photo_id FROM ' . $this->photos_table . '
			WHERE ' . $this->db->sql_in_set('album_id', $album_ids) . ' AND photo_status = ' . self::STATUS_APPROVED . '
			ORDER BY photo_id DESC';
		$result = $this->db->sql_query_limit($sql, 1000);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['photo_id'];
		}
		$this->db->sql_freeresult($result);

		shuffle($ids);

		return $this->get_many(array_slice($ids, 0, (int) $limit));
	}

	/**
	 * Derniers commentaires sur les photos des albums donnés (pages Portfolio, v1.8.0).
	 *
	 * @return array lignes de commentaires (comment_*) avec la photo associée sous la clé 'photo'
	 */
	public function latest_comments(array $album_ids, $limit)
	{
		if (!$album_ids)
		{
			return [];
		}

		$rows = [];
		$sql = 'SELECT c.*
			FROM ' . $this->comments_table . ' c, ' . $this->photos_table . ' p
			WHERE p.photo_id = c.photo_id
				AND ' . $this->db->sql_in_set('p.album_id', $album_ids) . '
				AND p.photo_status = ' . self::STATUS_APPROVED . '
			ORDER BY c.comment_time DESC, c.comment_id DESC';
		$result = $this->db->sql_query_limit($sql, (int) $limit);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		$photos = $this->get_many(array_column($rows, 'photo_id'));
		foreach ($rows as &$row)
		{
			$row['photo'] = isset($photos[(int) $row['photo_id']]) ? $photos[(int) $row['photo_id']] : null;
		}

		return array_values(array_filter($rows, function ($row) {
			return $row['photo'] !== null;
		}));
	}

	public function list_by_status($status, $start, $limit, $where_extra = '')
	{
		$where = 'photo_status = ' . (int) $status . ($where_extra ? ' AND ' . $where_extra : '');

		$sql = 'SELECT COUNT(photo_id) AS total FROM ' . $this->photos_table . ' WHERE ' . $where;
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$rows = [];
		$order = $status === self::STATUS_TRASH ? 'deleted_time DESC' : 'photo_time ASC';
		$sql = 'SELECT * FROM ' . $this->photos_table . ' WHERE ' . $where . ' ORDER BY ' . $order . ', photo_id DESC';
		$result = $this->db->sql_query_limit($sql, (int) $limit, (int) $start);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['photo_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return [$rows, $total];
	}

	public function count_by_status($status)
	{
		$sql = 'SELECT COUNT(photo_id) AS total FROM ' . $this->photos_table . ' WHERE photo_status = ' . (int) $status;
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	public function count_user_photos($user_id)
	{
		$sql = 'SELECT COUNT(photo_id) AS total
			FROM ' . $this->photos_table . '
			WHERE user_id = ' . (int) $user_id . '
				AND ' . $this->db->sql_in_set('photo_status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	/**
	 * Membres ayant au moins une photo publiée dans les albums donnés.
	 */
	public function top_contributors(array $album_ids, $limit)
	{
		if (!$album_ids)
		{
			return [];
		}

		$rows = [];
		$sql = 'SELECT user_id, COUNT(photo_id) AS total
			FROM ' . $this->photos_table . '
			WHERE ' . $this->db->sql_in_set('album_id', $album_ids) . '
				AND photo_status = ' . self::STATUS_APPROVED . '
			GROUP BY user_id
			ORDER BY total DESC';
		$result = $this->db->sql_query_limit($sql, (int) $limit);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['user_id']] = (int) $row['total'];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function stats(array $album_ids)
	{
		if (!$album_ids)
		{
			return ['photos' => 0, 'ratings' => 0, 'comments' => 0];
		}

		$sql = 'SELECT COUNT(photo_id) AS photos, SUM(photo_rating_count) AS ratings, SUM(photo_comments) AS comments
			FROM ' . $this->photos_table . '
			WHERE ' . $this->db->sql_in_set('album_id', $album_ids) . '
				AND photo_status = ' . self::STATUS_APPROVED;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return ['photos' => (int) $row['photos'], 'ratings' => (int) $row['ratings'], 'comments' => (int) $row['comments']];
	}

	/* ------------------------------------------------------------------
	 * Écriture
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $album
	 * @param array $file   Résultat de image_processor::process_*()
	 * @param bool  $pending
	 * @return int photo_id
	 */
	public function add(array $album, array $file, $title, $pending)
	{
		if ($title === '')
		{
			$title = pathinfo($file['photo_realname'], PATHINFO_FILENAME);
			$title = trim(str_replace(['_', '-'], ' ', $title)) ?: 'Photo';
		}

		// MySQL en mode strict refuse un champ texte (MEDIUMTEXT) absent : il n'a pas de valeur par défaut
		$row = array_merge([
			'photo_desc'			=> '',
			'photo_desc_uid'		=> '',
			'photo_desc_bitfield'	=> '',
			'photo_realname'		=> '',
			'deleted_reason'		=> '',
		], $file, [
			'album_id'		=> (int) $album['album_id'],
			'user_id'		=> (int) $this->user->data['user_id'],
			'photo_title'	=> truncate_string($title, 255, 255, false),
			'photo_time'	=> time(),
			'photo_status'	=> $pending ? self::STATUS_PENDING : self::STATUS_APPROVED,
			'photo_prev_status' => $pending ? self::STATUS_PENDING : self::STATUS_APPROVED,
		]);

		/**
		 * @event verturin.portfolio.photo_add_before
		 * @var array row   Ligne insérée
		 * @var array album Album de destination
		 */
		$vars = ['row', 'album'];
		extract($this->dispatcher->trigger_event('verturin.portfolio.photo_add_before', compact($vars)));

		$this->db->sql_query('INSERT INTO ' . $this->photos_table . ' ' . $this->db->sql_build_array('INSERT', $row));
		$photo_id = (int) $this->db->sql_nextid();

		$this->albums->resync($album['album_id']);

		return $photo_id;
	}

	public function update($photo_id, array $data)
	{
		$row = [];
		if (isset($data['photo_title']))
		{
			$row['photo_title'] = truncate_string(trim($data['photo_title']), 255, 255, false);
		}
		if (isset($data['photo_desc']))
		{
			$text = $data['photo_desc'];
			$uid = $bitfield = '';
			$flags = 7;
			// Texte vide : rien à analyser (sinon phpBB signale « TOO_FEW_CHARS » hors des pages de messages)
			if (trim($text) !== '')
			{
				// Messages d'erreur de l'analyseur BBCode (texte trop court, trop de liens…)
				global $phpbb_container;
				$phpbb_container->get('language')->add_lang('posting');
				generate_text_for_storage($text, $uid, $bitfield, $flags, true, true, true);
			}
			$row['photo_desc'] = $text;
			$row['photo_desc_uid'] = $uid;
			$row['photo_desc_bitfield'] = $bitfield;
			$row['photo_desc_options'] = $flags;
		}
		foreach (['photo_width', 'photo_height', 'photo_filesize'] as $key)
		{
			if (isset($data[$key]))
			{
				$row[$key] = (int) $data[$key];
			}
		}
		if (isset($data['photo_hash']))
		{
			$row['photo_hash'] = (string) $data['photo_hash'];
		}

		if ($row)
		{
			$this->db->sql_query('UPDATE ' . $this->photos_table . ' SET ' . $this->db->sql_build_array('UPDATE', $row) . ' WHERE photo_id = ' . (int) $photo_id);
		}
	}

	/**
	 * Déplace des photos vers un autre album.
	 */
	public function move(array $photo_ids, $target_album_id)
	{
		$photos = $this->get_many($photo_ids);
		if (!$photos)
		{
			return 0;
		}

		$sql = 'UPDATE ' . $this->photos_table . '
			SET album_id = ' . (int) $target_album_id . '
			WHERE ' . $this->db->sql_in_set('photo_id', array_keys($photos));
		$this->db->sql_query($sql);

		$this->resync_albums_of($photos, [(int) $target_album_id]);

		return count($photos);
	}

	public function approve(array $photo_ids)
	{
		$photos = $this->get_many($photo_ids);
		$count = 0;

		foreach ($photos as $photo)
		{
			if ((int) $photo['photo_status'] !== self::STATUS_PENDING)
			{
				continue;
			}

			$this->db->sql_query('UPDATE ' . $this->photos_table . '
				SET photo_status = ' . self::STATUS_APPROVED . ', photo_prev_status = ' . self::STATUS_APPROVED . '
				WHERE photo_id = ' . (int) $photo['photo_id']);
			$count++;

			$this->notifications->add_notifications('verturin.portfolio.notification.type.approved', [
				'photo_id'		=> (int) $photo['photo_id'],
				'photo_title'	=> $photo['photo_title'],
				'user_id'		=> (int) $photo['user_id'],
				'actor_id'		=> (int) $this->user->data['user_id'],
				'time'			=> time(),
			]);
		}

		$this->resync_albums_of($photos);

		return $count;
	}

	/* ------------------------------------------------------------------
	 * Corbeille
	 * ------------------------------------------------------------------ */

	/**
	 * Nombre de suppressions de l'utilisateur dans la fenêtre de surveillance.
	 */
	public function recent_deletions($user_id)
	{
		$window = max(60, (int) $this->config['portfolio_alert_window']);
		$sql = 'SELECT COUNT(photo_id) AS total
			FROM ' . $this->photos_table . '
			WHERE deleted_by = ' . (int) $user_id . '
				AND photo_status = ' . self::STATUS_TRASH . '
				AND deleted_time > ' . (time() - $window);
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	/**
	 * Vérifie si l'utilisateur peut encore supprimer $count photos (blocage optionnel).
	 */
	public function deletion_allowed($user_id, $count, $exempt)
	{
		$threshold = (int) $this->config['portfolio_alert_threshold'];
		if ($exempt || !$threshold || !$this->config['portfolio_alert_block'])
		{
			return true;
		}

		if ($this->recent_deletions($user_id) + $count > $threshold)
		{
			$this->raise_alert($user_id, $this->recent_deletions($user_id) + $count, true);

			return false;
		}

		return true;
	}

	/**
	 * Place des photos dans la corbeille (rien n'est effacé avant validation par un administrateur).
	 *
	 * @return int Nombre de photos placées dans la corbeille
	 */
	public function trash(array $photo_ids, $reason = '')
	{
		$photos = $this->get_many($photo_ids);
		$user_id = (int) $this->user->data['user_id'];
		$ids = [];

		foreach ($photos as $photo)
		{
			if ((int) $photo['photo_status'] !== self::STATUS_TRASH)
			{
				$ids[] = (int) $photo['photo_id'];
			}
		}

		if (!$ids)
		{
			return 0;
		}

		/**
		 * @event verturin.portfolio.photo_trash_before
		 * @var array ids    Photos concernées
		 * @var string reason Motif
		 */
		$vars = ['ids', 'reason'];
		extract($this->dispatcher->trigger_event('verturin.portfolio.photo_trash_before', compact($vars)));

		$sql = 'UPDATE ' . $this->photos_table . '
			SET photo_prev_status = photo_status,
				photo_status = ' . self::STATUS_TRASH . ',
				deleted_time = ' . time() . ',
				deleted_by = ' . $user_id . ",
				deleted_reason = '" . $this->db->sql_escape(truncate_string($reason, 255, 255, false)) . "'
			WHERE " . $this->db->sql_in_set('photo_id', $ids);
		$this->db->sql_query($sql);

		$this->resync_albums_of($photos);

		$this->log->add('mod', $user_id, $this->user->ip, 'LOG_PORTFOLIO_TRASHED', false, [
			'forum_id'	=> 0,
			'topic_id'	=> 0,
			count($ids),
		]);

		$recent = $this->recent_deletions($user_id);
		$threshold = (int) $this->config['portfolio_alert_threshold'];
		if ($threshold && $recent >= $threshold)
		{
			$this->raise_alert($user_id, $recent, false);
		}

		return count($ids);
	}

	/**
	 * Alerte les administrateurs (une seule fois par fenêtre et par membre).
	 */
	protected function raise_alert($user_id, $count, $blocked)
	{
		$window = max(60, (int) $this->config['portfolio_alert_window']);
		$cache_key = '_portfolio_alert_' . (int) $user_id;
		if ($this->cache->get($cache_key))
		{
			return;
		}
		$this->cache->put($cache_key, 1, $window);

		$username = $this->user->data['username'];
		$this->log->add('admin', (int) $user_id, $this->user->ip, $blocked ? 'LOG_PORTFOLIO_MASS_DELETE_BLOCKED' : 'LOG_PORTFOLIO_MASS_DELETE', false, [
			$username,
			(int) $count,
			(int) ceil($window / 60),
		]);

		$this->notifications->add_notifications('verturin.portfolio.notification.type.mass_delete', [
			'alert_id'	=> time(),
			'user_id'	=> (int) $user_id,
			'count'		=> (int) $count,
			'minutes'	=> (int) ceil($window / 60),
			'blocked'	=> $blocked ? 1 : 0,
			'time'		=> time(),
		]);
	}

	/**
	 * Restaure des photos depuis la corbeille.
	 *
	 * @param int $target_album_id Album de destination si l'album d'origine n'existe plus (0 = album d'origine)
	 * @return int[] Photos restaurées
	 */
	public function restore(array $photo_ids, $target_album_id = 0)
	{
		$photos = $this->get_many($photo_ids);
		$restored = [];

		foreach ($photos as $photo)
		{
			if ((int) $photo['photo_status'] !== self::STATUS_TRASH)
			{
				continue;
			}

			$album_id = (int) $photo['album_id'];
			if (!$this->albums->get($album_id))
			{
				if (!$target_album_id || !$this->albums->get($target_album_id))
				{
					continue;
				}
				$album_id = (int) $target_album_id;
			}

			$status = (int) $photo['photo_prev_status'] === self::STATUS_PENDING ? self::STATUS_PENDING : self::STATUS_APPROVED;
			$this->db->sql_query('UPDATE ' . $this->photos_table . ' SET ' . $this->db->sql_build_array('UPDATE', [
				'album_id'			=> $album_id,
				'photo_status'		=> $status,
				'deleted_time'		=> 0,
				'deleted_by'		=> 0,
				'deleted_reason'	=> '',
			]) . ' WHERE photo_id = ' . (int) $photo['photo_id']);

			$restored[] = (int) $photo['photo_id'];
			$this->albums->resync($album_id);
		}

		if ($restored)
		{
			$this->log->add('mod', (int) $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_RESTORED', false, [
				'forum_id'	=> 0,
				'topic_id'	=> 0,
				count($restored),
			]);
		}

		return $restored;
	}

	/**
	 * Suppression DÉFINITIVE (réservée à l'administration, depuis la corbeille uniquement).
	 */
	public function purge(array $photo_ids)
	{
		$photos = $this->get_many($photo_ids);
		$ids = [];

		foreach ($photos as $photo)
		{
			if ((int) $photo['photo_status'] === self::STATUS_TRASH)
			{
				$ids[] = (int) $photo['photo_id'];
				$this->image->delete_files($photo['photo_file']);
			}
		}

		if (!$ids)
		{
			return 0;
		}

		$comment_ids = [];
		$sql = 'SELECT comment_id FROM ' . $this->comments_table . ' WHERE ' . $this->db->sql_in_set('photo_id', $ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$comment_ids[] = (int) $row['comment_id'];
		}
		$this->db->sql_freeresult($result);

		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->photos_table . ' WHERE ' . $this->db->sql_in_set('photo_id', $ids));
		$this->db->sql_query('DELETE FROM ' . $this->ratings_table . ' WHERE ' . $this->db->sql_in_set('photo_id', $ids));
		if ($this->reactions_table)
		{
			$this->db->sql_query('DELETE FROM ' . $this->reactions_table . ' WHERE ' . $this->db->sql_in_set('photo_id', $ids));
		}
		$this->db->sql_query('DELETE FROM ' . $this->comments_table . ' WHERE ' . $this->db->sql_in_set('photo_id', $ids));
		$this->db->sql_transaction('commit');

		if ($comment_ids)
		{
			$this->notifications->delete_notifications('verturin.portfolio.notification.type.comment', $comment_ids);
		}
		$this->notifications->delete_notifications('verturin.portfolio.notification.type.approved', $ids);

		return count($ids);
	}

	/**
	 * Photos en corbeille depuis plus de $days jours.
	 */
	public function trash_older_than($days, $limit = 200)
	{
		$ids = [];
		$sql = 'SELECT photo_id FROM ' . $this->photos_table . '
			WHERE photo_status = ' . self::STATUS_TRASH . '
				AND deleted_time < ' . (time() - (int) $days * 86400);
		$result = $this->db->sql_query_limit($sql, (int) $limit);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['photo_id'];
		}
		$this->db->sql_freeresult($result);

		return $ids;
	}

	/* ------------------------------------------------------------------
	 * Vues, notes, commentaires
	 * ------------------------------------------------------------------ */

	public function add_view($photo_id)
	{
		$this->db->sql_query('UPDATE ' . $this->photos_table . ' SET photo_views = photo_views + 1 WHERE photo_id = ' . (int) $photo_id);
	}

	public function get_user_rating($photo_id, $user_id)
	{
		$sql = 'SELECT rating FROM ' . $this->ratings_table . ' WHERE photo_id = ' . (int) $photo_id . ' AND user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$rating = (int) $this->db->sql_fetchfield('rating');
		$this->db->sql_freeresult($result);

		return $rating;
	}

	/**
	 * Enregistre (ou remplace, ou retire si $rating = 0) la note d'un membre.
	 *
	 * @return array ['avg' => float, 'count' => int]
	 */
	/* ------------------------------------------------------------------
	 * Réactions (v1.5.0)
	 * ------------------------------------------------------------------ */

	/**
	 * Réactions actives (réglage ACP), dans l'ordre choisi.
	 *
	 * @return array code => émoji
	 */
	public function active_reactions()
	{
		if (!$this->config['portfolio_reactions_enabled'])
		{
			return [];
		}

		$active = [];
		foreach (explode(',', (string) $this->config['portfolio_reactions_list']) as $code)
		{
			$code = trim($code);
			if (isset(self::REACTIONS[$code]))
			{
				$active[$code] = self::REACTIONS[$code];
			}
		}

		return $active;
	}

	/**
	 * Résumé des réactions de plusieurs photos (cartes des grilles, v1.6.0).
	 *
	 * @return array [photo_id => ['total' => n, 'top' => [emoji, …]]]
	 */
	public function reaction_summary(array $photo_ids)
	{
		$photo_ids = array_values(array_unique(array_filter(array_map('intval', $photo_ids))));
		$active = $this->active_reactions();
		if (!$photo_ids || !$active || !$this->reactions_table)
		{
			return [];
		}

		$counts = [];
		$sql = 'SELECT photo_id, reaction, COUNT(user_id) AS n FROM ' . $this->reactions_table . '
			WHERE ' . $this->db->sql_in_set('photo_id', $photo_ids) . '
			GROUP BY photo_id, reaction';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			if (isset($active[$row['reaction']]))
			{
				$counts[(int) $row['photo_id']][$row['reaction']] = (int) $row['n'];
			}
		}
		$this->db->sql_freeresult($result);

		$out = [];
		foreach ($counts as $photo_id => $list)
		{
			arsort($list);
			$top = [];
			foreach (array_slice(array_keys($list), 0, 3) as $code)
			{
				$top[] = $active[$code];
			}
			$out[$photo_id] = ['total' => array_sum($list), 'top' => $top];
		}

		return $out;
	}

	/**
	 * @return array ['counts' => [code => n], 'mine' => code|'', 'users' => [code => [user_id, …]]]
	 */
	public function get_reactions($photo_id, $user_id)
	{
		$out = ['counts' => [], 'mine' => '', 'users' => []];
		if (!$this->reactions_table)
		{
			return $out;
		}

		$sql = 'SELECT user_id, reaction FROM ' . $this->reactions_table . ' WHERE photo_id = ' . (int) $photo_id . ' ORDER BY reaction_time ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$code = $row['reaction'];
			$out['counts'][$code] = isset($out['counts'][$code]) ? $out['counts'][$code] + 1 : 1;
			$out['users'][$code][] = (int) $row['user_id'];
			if ((int) $row['user_id'] === (int) $user_id)
			{
				$out['mine'] = $code;
			}
		}
		$this->db->sql_freeresult($result);

		return $out;
	}

	/**
	 * Une réaction par membre et par photo : cliquer la même la retire, une autre la remplace.
	 *
	 * @return string Réaction enregistrée ('' si retirée)
	 */
	public function react($photo_id, $user_id, $code)
	{
		$current = $this->get_reactions($photo_id, $user_id)['mine'];
		$this->db->sql_query('DELETE FROM ' . $this->reactions_table . ' WHERE photo_id = ' . (int) $photo_id . ' AND user_id = ' . (int) $user_id);

		if ($code === '' || $code === $current)
		{
			return '';
		}

		$this->db->sql_query('INSERT INTO ' . $this->reactions_table . ' ' . $this->db->sql_build_array('INSERT', [
			'photo_id'		=> (int) $photo_id,
			'user_id'		=> (int) $user_id,
			'reaction'		=> $code,
			'reaction_time'	=> time(),
		]));

		return $code;
	}

	public function rate($photo_id, $user_id, $rating)
	{
		$photo_id = (int) $photo_id;
		$user_id = (int) $user_id;
		$rating = max(0, min(5, (int) $rating));

		$this->db->sql_query('DELETE FROM ' . $this->ratings_table . ' WHERE photo_id = ' . $photo_id . ' AND user_id = ' . $user_id);
		if ($rating)
		{
			$this->db->sql_query('INSERT INTO ' . $this->ratings_table . ' ' . $this->db->sql_build_array('INSERT', [
				'photo_id'		=> $photo_id,
				'user_id'		=> $user_id,
				'rating'		=> $rating,
				'rating_time'	=> time(),
			]));
		}

		$sql = 'SELECT COUNT(user_id) AS total, SUM(rating) AS sum_rating FROM ' . $this->ratings_table . ' WHERE photo_id = ' . $photo_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$count = (int) $row['total'];
		$sum = (int) $row['sum_rating'];
		$avg = $count ? (int) round($sum * 100 / $count) : 0;

		$this->db->sql_query('UPDATE ' . $this->photos_table . ' SET ' . $this->db->sql_build_array('UPDATE', [
			'photo_rating_sum'		=> $sum,
			'photo_rating_count'	=> $count,
			'photo_rating_avg'		=> $avg,
		]) . ' WHERE photo_id = ' . $photo_id);

		return ['avg' => $avg / 100, 'count' => $count];
	}

	public function list_comments($photo_id, $start, $limit)
	{
		$rows = [];
		$sql = 'SELECT * FROM ' . $this->comments_table . ' WHERE photo_id = ' . (int) $photo_id . ' ORDER BY comment_time ASC, comment_id ASC';
		$result = $this->db->sql_query_limit($sql, (int) $limit, (int) $start);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function get_comment($comment_id)
	{
		$sql = 'SELECT * FROM ' . $this->comments_table . ' WHERE comment_id = ' . (int) $comment_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	public function add_comment(array $photo, $text)
	{
		$uid = $bitfield = '';
		$flags = 0;
		generate_text_for_storage($text, $uid, $bitfield, $flags, true, true, true);

		$this->db->sql_query('INSERT INTO ' . $this->comments_table . ' ' . $this->db->sql_build_array('INSERT', [
			'photo_id'			=> (int) $photo['photo_id'],
			'user_id'			=> (int) $this->user->data['user_id'],
			'comment_text'		=> $text,
			'comment_uid'		=> $uid,
			'comment_bitfield'	=> $bitfield,
			'comment_options'	=> $flags,
			'comment_time'		=> time(),
		]));
		$comment_id = (int) $this->db->sql_nextid();

		$this->sync_comments((int) $photo['photo_id']);

		if ((int) $photo['user_id'] !== (int) $this->user->data['user_id'])
		{
			$this->notifications->add_notifications('verturin.portfolio.notification.type.comment', [
				'comment_id'	=> $comment_id,
				'photo_id'		=> (int) $photo['photo_id'],
				'photo_title'	=> $photo['photo_title'],
				'user_id'		=> (int) $photo['user_id'],
				'actor_id'		=> (int) $this->user->data['user_id'],
				'time'			=> time(),
			]);
		}

		return $comment_id;
	}

	public function delete_comment(array $comment)
	{
		$this->db->sql_query('DELETE FROM ' . $this->comments_table . ' WHERE comment_id = ' . (int) $comment['comment_id']);
		$this->sync_comments((int) $comment['photo_id']);
		$this->notifications->delete_notifications('verturin.portfolio.notification.type.comment', (int) $comment['comment_id']);
	}

	protected function sync_comments($photo_id)
	{
		$sql = 'SELECT COUNT(comment_id) AS total FROM ' . $this->comments_table . ' WHERE photo_id = ' . (int) $photo_id;
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$this->db->sql_query('UPDATE ' . $this->photos_table . ' SET photo_comments = ' . $total . ' WHERE photo_id = ' . (int) $photo_id);
	}

	protected function resync_albums_of(array $photos, array $extra = [])
	{
		$album_ids = $extra;
		foreach ($photos as $photo)
		{
			$album_ids[] = (int) $photo['album_id'];
		}

		foreach (array_unique($album_ids) as $album_id)
		{
			if ($this->albums->get($album_id))
			{
				$this->albums->resync($album_id);
			}
		}
	}
}
