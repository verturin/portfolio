<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Membres partis (v1.9.0).
 *
 * Quand un membre quitte le forum (suppression manuelle, élagage des inactifs, extension de nettoyage…) :
 * - son nom est conservé : ses photos et commentaires restent signés « par … », sans lien vers un profil ;
 * - ses albums sont rattachés au « membre repreneur » choisi dans l'administration, rangés dans
 *   un album « Photos de … » pour ne pas se mélanger aux siens ;
 * - selon le réglage, ses photos peuvent aussi partir dans la corbeille (l'administration valide ensuite).
 *
 * Les photos déjà orphelines (ancienne galerie, membres supprimés avant la v1.9.0) se rattachent
 * depuis l'ACP › Membres partis.
 */
class departed
{
	const MODE_TRANSFER	= 'transfer';	// Toujours rattacher au repreneur
	const MODE_FOLLOW	= 'follow';		// Suivre le choix fait à la suppression (conserver / supprimer les messages)
	const MODE_TRASH	= 'trash';		// Rattacher les albums, mettre les photos du membre à la corbeille

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var settings */
	protected $config;

	/** @var \phpbb\user */
	protected $user;

	/** @var album_manager */
	protected $albums;

	/** @var \phpbb\db\tools\tools_interface */
	protected $db_tools;

	/** @var array */
	protected $tables;

	/** @var string */
	protected $table_prefix;

	/** @var array|null [user_id => row] */
	protected $registry;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\db\tools\tools_interface $db_tools, settings $config, \phpbb\user $user, album_manager $albums, array $tables, $table_prefix)
	{
		$this->db = $db;
		$this->db_tools = $db_tools;
		$this->config = $config;
		$this->user = $user;
		$this->albums = $albums;
		$this->tables = $tables;
		$this->table_prefix = $table_prefix;
	}

	/* ------------------------------------------------------------------
	 * Registre des noms
	 * ------------------------------------------------------------------ */

	protected function load()
	{
		if ($this->registry === null)
		{
			$this->registry = [];
			$result = $this->db->sql_query('SELECT * FROM ' . $this->tables['departed']);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->registry[(int) $row['user_id']] = $row;
			}
			$this->db->sql_freeresult($result);
		}

		return $this->registry;
	}

	public function name($user_id)
	{
		$registry = $this->load();

		return isset($registry[(int) $user_id]) ? (string) $registry[(int) $user_id]['username'] : '';
	}

	/**
	 * Album « Photos de … » créé pour ce membre, s'il a été rattaché (les anciens liens vers son portfolio y mènent).
	 */
	public function album_id($user_id)
	{
		$registry = $this->load();

		return isset($registry[(int) $user_id]) ? (int) $registry[(int) $user_id]['album_id'] : 0;
	}

	public function record($user_id, $username, $heir_id = null, $album_id = null)
	{
		$user_id = (int) $user_id;
		if ($user_id <= ANONYMOUS)
		{
			return;
		}

		$registry = $this->load();
		$row = isset($registry[$user_id]) ? $registry[$user_id] : ['user_id' => $user_id, 'username' => '', 'heir_id' => 0, 'album_id' => 0, 'departed_time' => time()];
		if ($username !== '' && $username !== null)
		{
			$row['username'] = truncate_string((string) $username, 255, 255, false);
		}
		if ($heir_id !== null)
		{
			$row['heir_id'] = (int) $heir_id;
		}
		if ($album_id !== null)
		{
			$row['album_id'] = (int) $album_id;
		}

		$this->db->sql_query('DELETE FROM ' . $this->tables['departed'] . ' WHERE user_id = ' . $user_id);
		$this->db->sql_query('INSERT INTO ' . $this->tables['departed'] . ' ' . $this->db->sql_build_array('INSERT', $row));
		$this->registry[$user_id] = $row;
	}

	/* ------------------------------------------------------------------
	 * Membre repreneur
	 * ------------------------------------------------------------------ */

	/**
	 * Repreneur choisi dans l'ACP ; à défaut (ou s'il n'existe plus), le premier fondateur.
	 *
	 * @param array $exclude Membres en cours de suppression
	 */
	public function heir_id(array $exclude = [])
	{
		$exclude = array_map('intval', $exclude);
		$heir = (int) $this->config['portfolio_heir_id'];
		if ($heir > ANONYMOUS && !in_array($heir, $exclude, true) && $this->user_exists($heir))
		{
			return $heir;
		}

		$sql = 'SELECT user_id FROM ' . USERS_TABLE . '
			WHERE user_type = ' . USER_FOUNDER . ($exclude ? ' AND ' . $this->db->sql_in_set('user_id', $exclude, true) : '') . '
			ORDER BY user_id ASC';
		$result = $this->db->sql_query_limit($sql, 1);
		$founder = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		return $founder ?: 2;
	}

	public function user_exists($user_id)
	{
		$result = $this->db->sql_query('SELECT user_id FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id . ' AND user_type <> ' . USER_IGNORE);
		$found = (bool) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		return $found;
	}

	public function username_of($user_id)
	{
		$result = $this->db->sql_query('SELECT username FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id);
		$name = (string) $this->db->sql_fetchfield('username');
		$this->db->sql_freeresult($result);

		return $name;
	}

	/* ------------------------------------------------------------------
	 * Suppression d'un membre (événement core.delete_user_before)
	 * ------------------------------------------------------------------ */

	/**
	 * @param array    $users  [user_id => username]
	 * @param string   $mode   retain|remove (choix fait par l'administrateur à la suppression)
	 * @param callable $label  function ($username) : nom de l'album de rangement
	 * @param callable $reason function ($username) : motif de mise à la corbeille
	 */
	public function on_delete(array $users, $mode, callable $label, callable $reason)
	{
		$setting = (string) $this->config['portfolio_departed_mode'];
		$trash = $setting === self::MODE_TRASH || ($setting === self::MODE_FOLLOW && $mode === 'remove');
		$heir = $this->heir_id(array_keys($users));

		foreach ($users as $user_id => $username)
		{
			$user_id = (int) $user_id;
			$this->record($user_id, $username);
			$this->adopt($user_id, $heir, $label($username));
			if ($trash)
			{
				$this->trash_photos_of($user_id, $reason($username));
			}
			// Ses accès aux albums des autres disparaissent avec lui
			$this->db->sql_query('DELETE FROM ' . $this->tables['members'] . ' WHERE user_id = ' . $user_id);
		}
	}

	/**
	 * Rattache tous les albums d'un membre parti au repreneur, rangés dans un album « Photos de … ».
	 *
	 * Les photos gardent leur auteur : elles restent signées du nom du membre parti.
	 * Les albums « Mes amis » deviennent privés (les amis du repreneur ne sont pas ceux du membre parti) ;
	 * les partages explicites sont conservés.
	 *
	 * @return int Album de rangement (0 si le membre n'avait pas d'album)
	 */
	public function adopt($user_id, $heir_id, $container_name)
	{
		$user_id = (int) $user_id;
		$heir_id = (int) $heir_id;
		if ($user_id <= ANONYMOUS || $heir_id <= ANONYMOUS || $user_id === $heir_id)
		{
			return 0;
		}

		$sql = 'SELECT album_id, parent_id, album_visibility FROM ' . $this->tables['albums'] . ' WHERE owner_id = ' . $user_id;
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		if (!$rows)
		{
			return 0;
		}

		$ids = array_map('intval', array_column($rows, 'album_id'));
		$roots = [];
		$open = access::VIS_PRIVATE;
		foreach ($rows as $row)
		{
			// Racines : albums dont le parent n'appartient pas au membre parti
			if (!in_array((int) $row['parent_id'], $ids, true))
			{
				$roots[] = (int) $row['album_id'];
				$open = $this->more_open($open, (int) $row['album_visibility']);
			}
		}

		$container = $this->albums->create([
			'parent_id'			=> 0,
			'owner_id'			=> $heir_id,
			'album_name'		=> $container_name,
			'album_desc'		=> '',
			'album_visibility'	=> $open,
			'album_sort'		=> 'date_desc',
		]);

		// Les racines sont rangées dans l'album de rangement (au même niveau qu'avant, juste un cran plus bas)
		$this->db->sql_query('UPDATE ' . $this->tables['albums'] . ' SET parent_id = ' . (int) $container . ' WHERE ' . $this->db->sql_in_set('album_id', $roots));
		$this->db->sql_query('UPDATE ' . $this->tables['albums'] . ' SET album_visibility = ' . access::VIS_PRIVATE . ' WHERE album_visibility = ' . access::VIS_FRIENDS . ' AND ' . $this->db->sql_in_set('album_id', $ids));
		$this->db->sql_query('UPDATE ' . $this->tables['albums'] . ' SET album_friends = 0 WHERE ' . $this->db->sql_in_set('album_id', $ids));
		$this->db->sql_query('UPDATE ' . $this->tables['albums'] . ' SET owner_id = ' . $heir_id . ' WHERE owner_id = ' . $user_id);

		// Partages : le repreneur devient propriétaire, il n'a plus besoin de son propre partage
		$this->db->sql_query('DELETE FROM ' . $this->tables['members'] . ' WHERE owner_id = ' . $user_id . ' AND user_id = ' . $heir_id);
		$this->db->sql_query('UPDATE ' . $this->tables['members'] . ' SET owner_id = ' . $heir_id . ' WHERE owner_id = ' . $user_id);

		$this->record($user_id, null, $heir_id, $container);
		$this->albums->reset();

		return (int) $container;
	}

	/**
	 * Ordre d'ouverture : Tous > Membres inscrits > Groupes > Privé (les « amis » deviennent privés).
	 */
	protected function more_open($a, $b)
	{
		$rank = [access::VIS_ALL => 4, access::VIS_REGISTERED => 3, access::VIS_GROUPS => 2, access::VIS_FRIENDS => 1, access::VIS_PRIVATE => 1];
		$ra = isset($rank[$a]) ? $rank[$a] : 1;
		$rb = isset($rank[$b]) ? $rank[$b] : 1;
		$best = max($ra, $rb);

		// Un album de rangement « Groupes » sans groupe ne montrerait rien : on ouvre aux membres inscrits,
		// les sous-albums gardent leurs propres restrictions.
		return [4 => access::VIS_ALL, 3 => access::VIS_REGISTERED, 2 => access::VIS_REGISTERED, 1 => access::VIS_PRIVATE][$best];
	}

	/**
	 * Met à la corbeille toutes les photos publiées par ce membre (l'administration valide ou restaure).
	 *
	 * @return int
	 */
	public function trash_photos_of($user_id, $reason)
	{
		$sql = 'SELECT DISTINCT album_id FROM ' . $this->tables['photos'] . '
			WHERE user_id = ' . (int) $user_id . ' AND photo_status <> ' . photo_manager::STATUS_TRASH;
		$result = $this->db->sql_query($sql);
		$albums = array_map('intval', array_column($this->db->sql_fetchrowset($result), 'album_id'));
		$this->db->sql_freeresult($result);
		if (!$albums)
		{
			return 0;
		}

		$sql = 'UPDATE ' . $this->tables['photos'] . '
			SET photo_prev_status = photo_status,
				photo_status = ' . photo_manager::STATUS_TRASH . ',
				deleted_time = ' . time() . ',
				deleted_by = ' . (int) $this->user->data['user_id'] . ",
				deleted_reason = '" . $this->db->sql_escape(truncate_string($reason, 255, 255, false)) . "'
			WHERE user_id = " . (int) $user_id . '
				AND photo_status <> ' . photo_manager::STATUS_TRASH;
		$this->db->sql_query($sql);
		$count = (int) $this->db->sql_affectedrows();

		foreach ($albums as $album_id)
		{
			$this->albums->resync($album_id);
		}

		return $count;
	}

	/* ------------------------------------------------------------------
	 * Données orphelines (ACP › Membres partis)
	 * ------------------------------------------------------------------ */

	/**
	 * Membres disparus qui possèdent encore des albums ou des photos.
	 *
	 * @return array [['user_id', 'username', 'albums', 'photos', 'heir_id', 'album_id']]
	 */
	public function scan()
	{
		$found = [];

		$sql = 'SELECT a.owner_id AS user_id, COUNT(a.album_id) AS n
			FROM ' . $this->tables['albums'] . ' a
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = a.owner_id)
			WHERE a.owner_id > ' . ANONYMOUS . ' AND u.user_id IS NULL
			GROUP BY a.owner_id';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$found[(int) $row['user_id']]['albums'] = (int) $row['n'];
		}
		$this->db->sql_freeresult($result);

		$sql = 'SELECT p.user_id, COUNT(p.photo_id) AS n
			FROM ' . $this->tables['photos'] . ' p
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = p.user_id)
			WHERE p.user_id > ' . ANONYMOUS . ' AND u.user_id IS NULL
			GROUP BY p.user_id';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$found[(int) $row['user_id']]['photos'] = (int) $row['n'];
		}
		$this->db->sql_freeresult($result);

		$this->harvest_gallery_names(array_keys($found));
		$registry = $this->load();

		$out = [];
		foreach ($found as $user_id => $counts)
		{
			$out[] = [
				'user_id'	=> $user_id,
				'username'	=> isset($registry[$user_id]) ? (string) $registry[$user_id]['username'] : '',
				'albums'	=> isset($counts['albums']) ? $counts['albums'] : 0,
				'photos'	=> isset($counts['photos']) ? $counts['photos'] : 0,
			];
		}
		usort($out, function ($a, $b) {
			return ($b['albums'] <=> $a['albums']) ?: ($b['photos'] <=> $a['photos']);
		});

		return $out;
	}

	/**
	 * Membres déjà rattachés (historique).
	 */
	public function history()
	{
		$rows = array_filter($this->load(), function ($row) {
			return (int) $row['heir_id'] > 0;
		});
		usort($rows, function ($a, $b) {
			return (int) $b['departed_time'] <=> (int) $a['departed_time'];
		});

		return $rows;
	}

	/**
	 * Retrouve le nom des membres partis dans les tables de phpBB Gallery (image_username, comment_username),
	 * pour que leurs photos migrées restent signées.
	 */
	public function harvest_gallery_names(array $user_ids)
	{
		$registry = $this->load();
		$missing = array_values(array_filter(array_map('intval', $user_ids), function ($id) use ($registry) {
			return $id > ANONYMOUS && (!isset($registry[$id]) || $registry[$id]['username'] === '');
		}));
		if (!$missing)
		{
			return;
		}

		$sources = [
			['gallery_images', 'image_user_id', 'image_username'],
			['gallery_comments', 'comment_user_id', 'comment_username'],
		];
		foreach ($sources as $source)
		{
			list($table, $id_col, $name_col) = $source;
			$table = $this->table_prefix . $table;
			if (!$missing || !$this->table_exists($table))
			{
				continue;
			}

			$sql = 'SELECT ' . $id_col . ' AS user_id, MAX(' . $name_col . ') AS username
				FROM ' . $table . '
				WHERE ' . $this->db->sql_in_set($id_col, $missing) . "
					AND $name_col <> ''
				GROUP BY " . $id_col;
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->record((int) $row['user_id'], (string) $row['username']);
				$missing = array_values(array_diff($missing, [(int) $row['user_id']]));
			}
			$this->db->sql_freeresult($result);
		}
	}

	protected function table_exists($table)
	{
		return $this->db_tools->sql_table_exists($table);
	}

	/**
	 * Attribue les photos d'un membre parti à un autre membre : elles seront signées de son nom (v1.9.5).
	 *
	 * @return int Nombre de photos
	 */
	public function assign_photos($user_id, $to_id)
	{
		$user_id = (int) $user_id;
		$to_id = (int) $to_id;
		if ($user_id <= ANONYMOUS || $to_id <= ANONYMOUS || $user_id === $to_id)
		{
			return 0;
		}
		$this->db->sql_query('UPDATE ' . $this->tables['photos'] . ' SET user_id = ' . $to_id . ' WHERE user_id = ' . $user_id);

		return (int) $this->db->sql_affectedrows();
	}
}
