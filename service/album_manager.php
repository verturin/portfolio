<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Albums, sous-albums, co-gestionnaires et droits de groupe.
 */
class album_manager
{
	const SORTS = ['date_desc', 'date_asc', 'title', 'rating', 'views'];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \verturin\portfolio\service\settings Réglages du Portfolio */
	protected $config;

	/** @var \phpbb\event\dispatcher_interface */
	protected $dispatcher;

	protected $albums_table;
	protected $photos_table;
	protected $members_table;
	protected $album_groups_table;

	/** @var array|null */
	protected $all;

	public function __construct(\phpbb\db\driver\driver_interface $db, \verturin\portfolio\service\settings $config, \phpbb\event\dispatcher_interface $dispatcher, $albums_table, $photos_table, $members_table, $album_groups_table)
	{
		$this->db = $db;
		$this->config = $config;
		$this->dispatcher = $dispatcher;
		$this->albums_table = $albums_table;
		$this->photos_table = $photos_table;
		$this->members_table = $members_table;
		$this->album_groups_table = $album_groups_table;
	}

	/* ------------------------------------------------------------------
	 * Lecture
	 * ------------------------------------------------------------------ */

	/**
	 * Tous les albums, indexés par identifiant, triés pour l'affichage.
	 */
	public function get_all()
	{
		if ($this->all === null)
		{
			$this->all = [];
			$sql = 'SELECT *
				FROM ' . $this->albums_table . '
				ORDER BY parent_id ASC, album_order ASC, album_name ASC';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->all[(int) $row['album_id']] = $row;
			}
			$this->db->sql_freeresult($result);
		}

		return $this->all;
	}

	public function reset()
	{
		$this->all = null;
		$this->member_map = [];
	}

	/** @var array [owner_id => [album_id => [user_id => true]]] */
	protected $member_map = [];

	/**
	 * Membres avec qui un album est partagé, partages hérités des albums parents compris (v1.8.1).
	 *
	 * @return int[] identifiants des membres
	 */
	public function shared_users(array $album)
	{
		$owner_id = (int) $album['owner_id'];
		if (!$owner_id)
		{
			return [];
		}

		if (!isset($this->member_map[$owner_id]))
		{
			$this->member_map[$owner_id] = [];
			$sql = 'SELECT album_id, user_id FROM ' . $this->members_table . ' WHERE owner_id = ' . $owner_id;
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->member_map[$owner_id][(int) $row['album_id']][(int) $row['user_id']] = true;
			}
			$this->db->sql_freeresult($result);
		}

		$users = [];
		foreach ($this->get_chain((int) $album['album_id']) as $node)
		{
			$users += isset($this->member_map[$owner_id][(int) $node['album_id']]) ? $this->member_map[$owner_id][(int) $node['album_id']] : [];
		}

		return array_keys($users);
	}

	public function get($album_id)
	{
		$all = $this->get_all();

		return isset($all[(int) $album_id]) ? $all[(int) $album_id] : null;
	}

	/**
	 * Tous les sous-albums (enfants, petits-enfants…) d'un album, sans boucle possible.
	 *
	 * @return int[]
	 */
	public function descendants($album_id)
	{
		$by_parent = [];
		foreach ($this->get_all() as $id => $album)
		{
			$by_parent[(int) $album['parent_id']][] = (int) $id;
		}

		$found = [];
		$queue = isset($by_parent[(int) $album_id]) ? $by_parent[(int) $album_id] : [];
		while ($queue)
		{
			$id = array_shift($queue);
			if (isset($found[$id]) || $id === (int) $album_id)
			{
				continue;
			}
			$found[$id] = true;
			if (isset($by_parent[$id]))
			{
				$queue = array_merge($queue, $by_parent[$id]);
			}
		}

		return array_keys($found);
	}

	/**
	 * Album qui coupe le filigrane pour cet album : lui-même ou l'un de ses parents (v1.9.9).
	 * Retourne 0 si le filigrane s'applique normalement.
	 *
	 * @param array|int $album Ligne d'album ou identifiant
	 */
	public function no_watermark_from($album)
	{
		$all = $this->get_all();
		$id = is_array($album) ? (int) $album['album_id'] : (int) $album;
		$seen = [];
		while ($id && isset($all[$id]) && !isset($seen[$id]))
		{
			$seen[$id] = true;
			if (!empty($all[$id]['album_no_watermark']))
			{
				return $id;
			}
			$id = (int) $all[$id]['parent_id'];
		}

		return 0;
	}

	/** Le filigrane est-il coupé pour les photos de cet album (réglage de l'album ou d'un parent) ? */
	public function watermark_off($album)
	{
		return $album ? $this->no_watermark_from($album) > 0 : false;
	}

	/**
	 * Même modification sur plusieurs albums en une requête (visibilité, téléchargement…).
	 */
	public function update_many(array $album_ids, array $data)
	{
		$album_ids = array_values(array_unique(array_filter(array_map('intval', $album_ids))));
		$row = $this->prepare_row($data);
		if (!$album_ids || !$row)
		{
			return 0;
		}

		$sql = 'UPDATE ' . $this->albums_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $row) . '
			WHERE ' . $this->db->sql_in_set('album_id', $album_ids);
		$this->db->sql_query($sql);
		$this->reset();

		return count($album_ids);
	}

	public function get_children($album_id)
	{
		$children = [];
		foreach ($this->get_all() as $id => $album)
		{
			if ((int) $album['parent_id'] === (int) $album_id)
			{
				$children[$id] = $album;
			}
		}

		return $children;
	}

	/**
	 * Chaîne de la racine jusqu'à l'album (inclus).
	 */
	public function get_chain($album_id)
	{
		$chain = [];
		$all = $this->get_all();
		$guard = 0;

		while ($album_id && isset($all[$album_id]) && $guard++ < 50)
		{
			array_unshift($chain, $all[$album_id]);
			$album_id = (int) $all[$album_id]['parent_id'];
		}

		return $chain;
	}

	public function depth($album_id)
	{
		return count($this->get_chain($album_id));
	}

	public function get_descendant_ids($album_id)
	{
		$ids = [];
		foreach ($this->get_children($album_id) as $id => $child)
		{
			$ids[] = $id;
			$ids = array_merge($ids, $this->get_descendant_ids($id));
		}

		return $ids;
	}

	/**
	 * Albums « frères » d'un album, dans l'ordre d'affichage (même parent ; à la racine : même propriétaire).
	 */
	public function get_siblings(array $album)
	{
		$siblings = [];
		foreach ($this->get_children((int) $album['parent_id']) as $id => $row)
		{
			if ((int) $album['parent_id'] || (int) $row['owner_id'] === (int) $album['owner_id'])
			{
				$siblings[$id] = $row;
			}
		}

		return $siblings;
	}

	public function get_user_roots($user_id)
	{
		$roots = [];
		foreach ($this->get_children(0) as $id => $album)
		{
			if ((int) $album['owner_id'] === (int) $user_id)
			{
				$roots[$id] = $album;
			}
		}

		return $roots;
	}

	public function get_public_roots()
	{
		return $this->get_user_roots(0);
	}

	public function count_user_albums($user_id)
	{
		$count = 0;
		foreach ($this->get_all() as $album)
		{
			if ((int) $album['owner_id'] === (int) $user_id)
			{
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Arbre à plat (pour les listes déroulantes) : [album_id => ['album' => row, 'depth' => n]]
	 *
	 * @param callable|null $filter       function(array $album): bool
	 * @param int           $exclude_from Exclut cet album et ses descendants (déplacement)
	 */
	public function flat_tree(?callable $filter = null, $exclude_from = 0, $parent_id = 0, $depth = 0)
	{
		$out = [];
		foreach ($this->get_children($parent_id) as $id => $album)
		{
			if ($exclude_from && $id === (int) $exclude_from)
			{
				continue;
			}

			if ($filter === null || $filter($album))
			{
				$out[$id] = ['album' => $album, 'depth' => $depth];
			}

			$out += $this->flat_tree($filter, $exclude_from, $id, $depth + 1);
		}

		return $out;
	}

	/**
	 * Nombre de photos actives (en attente ou publiées) d'un album.
	 */
	public function count_active_photos($album_id)
	{
		$sql = 'SELECT COUNT(photo_id) AS total
			FROM ' . $this->photos_table . '
			WHERE album_id = ' . (int) $album_id . '
				AND ' . $this->db->sql_in_set('photo_status', [photo_manager::STATUS_PENDING, photo_manager::STATUS_APPROVED]);
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	/* ------------------------------------------------------------------
	 * Écriture
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $data album_name, album_desc (texte brut), parent_id, owner_id, album_visibility, album_locked, album_sort
	 * @return int album_id
	 */
	public function create(array $data)
	{
		// MySQL en mode strict refuse un champ texte (MEDIUMTEXT) absent : il n'a pas de valeur par défaut
		$row = array_merge(['album_desc' => '', 'album_desc_uid' => '', 'album_desc_bitfield' => ''], $this->prepare_row($data));
		$row['album_time'] = time();
		$row['album_order'] = $this->next_order(isset($row['parent_id']) ? $row['parent_id'] : 0);

		/**
		 * @event verturin.portfolio.album_create_before
		 * @var array row Données de l'album à insérer
		 */
		$vars = ['row'];
		extract($this->dispatcher->trigger_event('verturin.portfolio.album_create_before', compact($vars)));

		$this->db->sql_query('INSERT INTO ' . $this->albums_table . ' ' . $this->db->sql_build_array('INSERT', $row));
		$album_id = (int) $this->db->sql_nextid();

		// Un sous-album hérite des droits de groupe de son parent
		if (!empty($row['parent_id']))
		{
			$groups = $this->get_groups($row['parent_id']);
			if ($groups)
			{
				$this->set_groups($album_id, $groups);
			}
		}

		$this->reset();

		return $album_id;
	}

	public function update($album_id, array $data)
	{
		$row = $this->prepare_row($data);
		if (!$row)
		{
			return;
		}

		$sql = 'UPDATE ' . $this->albums_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $row) . '
			WHERE album_id = ' . (int) $album_id;
		$this->db->sql_query($sql);
		$this->reset();
	}

	/**
	 * Supprime un album vide. Retourne une clé d'erreur ou chaîne vide.
	 */
	public function delete($album_id)
	{
		$album_id = (int) $album_id;

		if ($this->get_children($album_id))
		{
			return 'PORTFOLIO_ALBUM_HAS_CHILDREN';
		}

		if ($this->count_active_photos($album_id))
		{
			return 'PORTFOLIO_ALBUM_NOT_EMPTY';
		}

		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->albums_table . ' WHERE album_id = ' . $album_id);
		$this->db->sql_query('DELETE FROM ' . $this->members_table . ' WHERE album_id = ' . $album_id);
		$this->db->sql_query('DELETE FROM ' . $this->album_groups_table . ' WHERE album_id = ' . $album_id);
		$this->db->sql_transaction('commit');

		$this->reset();

		return '';
	}

	/**
	 * Monte ou descend un album parmi ses frères.
	 */
	public function move_order($album_id, $direction)
	{
		$album = $this->get($album_id);
		if (!$album)
		{
			return;
		}

		$siblings = array_values(array_keys($this->get_children((int) $album['parent_id'])));
		$pos = array_search((int) $album_id, $siblings, true);
		$swap = $direction === 'up' ? $pos - 1 : $pos + 1;
		if ($pos === false || !isset($siblings[$swap]))
		{
			return;
		}

		$tmp = $siblings[$swap];
		$siblings[$swap] = $siblings[$pos];
		$siblings[$pos] = $tmp;

		foreach ($siblings as $order => $id)
		{
			$this->db->sql_query('UPDATE ' . $this->albums_table . ' SET album_order = ' . ($order + 1) . ' WHERE album_id = ' . (int) $id);
		}
		$this->reset();
	}

	/**
	 * Recalcule compteurs, dernière photo et couverture.
	 */
	public function resync($album_id)
	{
		$album_id = (int) $album_id;
		if (!$album_id)
		{
			return;
		}

		$sql = 'SELECT COUNT(photo_id) AS total, MAX(photo_id) AS last_id, MAX(photo_time) AS last_time
			FROM ' . $this->photos_table . '
			WHERE album_id = ' . $album_id . '
				AND photo_status = ' . photo_manager::STATUS_APPROVED;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$update = [
			'album_photos'			=> (int) $row['total'],
			'album_last_photo_id'	=> (int) $row['last_id'],
			'album_last_time'		=> (int) $row['last_time'],
		];

		// La couverture doit rester une photo publiée de l'album
		$album = $this->get($album_id);
		if ($album && $album['album_cover_id'])
		{
			$sql = 'SELECT photo_id FROM ' . $this->photos_table . '
				WHERE photo_id = ' . (int) $album['album_cover_id'] . '
					AND album_id = ' . $album_id . '
					AND photo_status = ' . photo_manager::STATUS_APPROVED;
			$result = $this->db->sql_query($sql);
			if (!$this->db->sql_fetchrow($result))
			{
				$update['album_cover_id'] = 0;
			}
			$this->db->sql_freeresult($result);
		}

		$this->db->sql_query('UPDATE ' . $this->albums_table . ' SET ' . $this->db->sql_build_array('UPDATE', $update) . ' WHERE album_id = ' . $album_id);
		$this->reset();
	}

	public function set_cover($album_id, $photo_id)
	{
		$this->db->sql_query('UPDATE ' . $this->albums_table . ' SET album_cover_id = ' . (int) $photo_id . ' WHERE album_id = ' . (int) $album_id);
		$this->reset();
	}

	/* ------------------------------------------------------------------
	 * Droits par groupe
	 * ------------------------------------------------------------------ */

	public function get_groups($album_id)
	{
		$rows = [];
		$sql = 'SELECT * FROM ' . $this->album_groups_table . ' WHERE album_id = ' . (int) $album_id;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['group_id']] = [
				'can_view'		=> (int) $row['can_view'],
				'can_upload'	=> (int) $row['can_upload'],
				'can_rate'		=> (int) $row['can_rate'],
				'can_comment'	=> (int) $row['can_comment'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * @param array $groups [group_id => ['can_view' => 0|1, ...]] — tableau vide = aucune restriction
	 */
	public function set_groups($album_id, array $groups)
	{
		$album_id = (int) $album_id;
		$this->db->sql_query('DELETE FROM ' . $this->album_groups_table . ' WHERE album_id = ' . $album_id);

		$insert = [];
		foreach ($groups as $group_id => $flags)
		{
			$insert[] = [
				'album_id'		=> $album_id,
				'group_id'		=> (int) $group_id,
				'can_view'		=> !empty($flags['can_view']) ? 1 : 0,
				'can_upload'	=> !empty($flags['can_upload']) ? 1 : 0,
				'can_rate'		=> !empty($flags['can_rate']) ? 1 : 0,
				'can_comment'	=> !empty($flags['can_comment']) ? 1 : 0,
			];
		}

		if ($insert)
		{
			$this->db->sql_multi_insert($this->album_groups_table, $insert);
		}
	}

	/* ------------------------------------------------------------------
	 * Co-gestionnaires
	 * ------------------------------------------------------------------ */

	/**
	 * @param int $owner_id
	 * @param int $album_id 0 = tous les albums du propriétaire
	 */
	public function get_members($owner_id, $album_id)
	{
		$rows = [];
		$sql = 'SELECT m.*, u.username, u.user_colour
			FROM ' . $this->members_table . ' m
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = m.user_id)
			WHERE m.owner_id = ' . (int) $owner_id . '
				AND m.album_id = ' . (int) $album_id . '
			ORDER BY u.username_clean ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function add_member($owner_id, $album_id, $user_id, $role)
	{
		$this->remove_member($owner_id, $album_id, $user_id);
		$this->db->sql_query('INSERT INTO ' . $this->members_table . ' ' . $this->db->sql_build_array('INSERT', [
			'owner_id'		=> (int) $owner_id,
			'album_id'		=> (int) $album_id,
			'user_id'		=> (int) $user_id,
			'member_role'	=> (int) $role,
			'member_time'	=> time(),
		]));
	}

	public function set_member_role($owner_id, $album_id, $user_id, $role)
	{
		$this->db->sql_query('UPDATE ' . $this->members_table . '
			SET member_role = ' . (int) $role . '
			WHERE owner_id = ' . (int) $owner_id . '
				AND album_id = ' . (int) $album_id . '
				AND user_id = ' . (int) $user_id);
	}

	public function remove_member($owner_id, $album_id, $user_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->members_table . '
			WHERE owner_id = ' . (int) $owner_id . '
				AND album_id = ' . (int) $album_id . '
				AND user_id = ' . (int) $user_id);
	}

	/**
	 * Propriétaires dont l'utilisateur gère des albums (pour « Albums partagés avec moi »).
	 */
	public function get_shared_with($user_id)
	{
		$rows = [];
		$sql = 'SELECT owner_id, album_id, member_role
			FROM ' . $this->members_table . '
			WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/* ------------------------------------------------------------------
	 * Interne
	 * ------------------------------------------------------------------ */

	protected function prepare_row(array $data)
	{
		$row = [];

		foreach (['parent_id', 'owner_id', 'album_visibility', 'album_order', 'album_cover_id'] as $key)
		{
			if (isset($data[$key]))
			{
				$row[$key] = (int) $data[$key];
			}
		}

		if (isset($data['album_locked']))
		{
			$row['album_locked'] = $data['album_locked'] ? 1 : 0;
		}

		if (isset($data['album_friends']))
		{
			$row['album_friends'] = $data['album_friends'] ? 1 : 0;
		}

		if (isset($data['album_download']))
		{
			$row['album_download'] = $data['album_download'] ? 1 : 0;
		}

		if (isset($data['album_protected']))
		{
			$row['album_protected'] = $data['album_protected'] ? 1 : 0;
		}

		if (isset($data['album_no_watermark']))
		{
			$row['album_no_watermark'] = $data['album_no_watermark'] ? 1 : 0;
		}

		if (isset($data['album_owner_no_wm']))
		{
			$row['album_owner_no_wm'] = $data['album_owner_no_wm'] ? 1 : 0;
		}

		if (isset($data['album_sort']))
		{
			$row['album_sort'] = in_array($data['album_sort'], self::SORTS, true) ? $data['album_sort'] : 'date_desc';
		}

		if (isset($data['album_name']))
		{
			$row['album_name'] = truncate_string(trim($data['album_name']), 255, 255, false);
		}

		if (isset($data['album_desc']))
		{
			$text = $data['album_desc'];
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
			$row['album_desc'] = $text;
			$row['album_desc_uid'] = $uid;
			$row['album_desc_bitfield'] = $bitfield;
			$row['album_desc_options'] = $flags;
		}

		return $row;
	}

	protected function next_order($parent_id)
	{
		$sql = 'SELECT MAX(album_order) AS max_order FROM ' . $this->albums_table . ' WHERE parent_id = ' . (int) $parent_id;
		$result = $this->db->sql_query($sql);
		$max = (int) $this->db->sql_fetchfield('max_order');
		$this->db->sql_freeresult($result);

		return $max + 1;
	}
}
