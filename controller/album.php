<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use verturin\portfolio\service\access;
use verturin\portfolio\service\album_manager;
use verturin\portfolio\service\photo_manager;

/**
 * Albums : affichage, création, édition, suppression, co-gestion, actions groupées.
 */
class album extends base
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	public function set_db(\phpbb\db\driver\driver_interface $db)
	{
		$this->db = $db;
	}

	/* ------------------------------------------------------------------
	 * Affichage
	 * ------------------------------------------------------------------ */

	public function view($album_id)
	{
		$this->init();
		$album = $this->load_album($album_id);
		$album_id = (int) $album['album_id'];

		$can_manage = $this->access->can_manage_album($album);
		$sort = $this->request->variable('sort', $album['album_sort']);
		$sort = in_array($sort, album_manager::SORTS, true) ? $sort : 'date_desc';

		$statuses = ($can_manage || $this->access->can_approve())
			? [photo_manager::STATUS_PENDING, photo_manager::STATUS_APPROVED]
			: [photo_manager::STATUS_APPROVED];

		$per_page = max(6, (int) $this->config['portfolio_per_page']);
		$start = $this->page_start($per_page);
		list($rows, $total) = $this->photos->list_album($album_id, $sort, $start, $per_page, $statuses, $this->access->is_registered() ? $this->access->user_id() : 0);

		// Le tri choisi est conservé quand on passe de photo en photo
		$photos = $this->presenter->photos($rows, $sort !== $album['album_sort'] ? ['sort' => $sort] : []);
		foreach ($photos as &$photo)
		{
			$photo['can_delete'] = $this->access->can_delete_photo($album, $rows[$photo['id']]);
			$photo['can_select'] = $photo['can_delete'] || $this->access->can_move_photo($album, $rows[$photo['id']]);
		}
		unset($photo);

		$children = array_filter($this->albums->get_children($album_id), function ($child) {
			return $this->access->can_view_album($child);
		});

		$sorts = [];
		foreach (album_manager::SORTS as $key)
		{
			$sorts[] = [
				'key'		=> $key,
				'label'		=> $this->language->lang('PORTFOLIO_SORT_' . strtoupper($key)),
				'url'		=> $this->route('album', ['album_id' => $album_id, 'sort' => $key]),
				'active'	=> $key === $sort,
			];
		}

		$depth_ok = $this->albums->depth($album_id) < max(1, (int) $this->config['portfolio_max_depth']);
		$can_bulk = (bool) array_filter(array_column($photos, 'can_select'));
		$can_bulk_delete = (bool) array_filter(array_column($photos, 'can_delete'));
		$move_targets = $can_bulk ? $this->upload_targets($album_id, (int) $album['owner_id']) : [];
		// Barre de sélection seulement s'il reste une action possible (suppression, déplacement ou validation)
		$can_bulk = $can_bulk && ($can_bulk_delete || $move_targets || $this->access->can_approve_album($album));

		add_form_key('verturin_portfolio_bulk');

		$this->presenter->load_users([(int) $album['owner_id']]);
		$pagination_params = ['album_id' => $album_id];
		if ($sort !== $album['album_sort'])
		{
			$pagination_params['sort'] = $sort;
		}

		return $this->render('portfolio_album.html', $album['album_name'], [
			'PF_ALBUM'			=> $this->presenter->album($album),
			'PF_ALBUM_DESC'		=> $this->presenter->text($album['album_desc'], $album['album_desc_uid'], $album['album_desc_bitfield'], $album['album_desc_options']),
			'PF_CHILDREN'		=> $this->presenter->albums($children),
			'PF_NAV'			=> $this->album_navigation($album),
			'PF_PHOTOS'			=> $photos,
			'PF_PHOTO_TOTAL'	=> $total,
			'PF_SORTS'			=> $sorts,
			'PF_PAGINATION'		=> $this->presenter->pagination('album', $pagination_params, $total, $per_page, $start),
			'PF_CAN_MANAGE'		=> $can_manage,
			'PF_CAN_BULK'		=> $can_bulk,
			'PF_CAN_BULK_DELETE'	=> $can_bulk_delete,
			'PF_REASON_REQUIRED'	=> (bool) $this->config['portfolio_trash_reason_required'],
			'PF_CAN_APPROVE'	=> $this->access->can_approve_album($album),
			'PF_MOVE_TARGETS'	=> $move_targets,
			'U_PF_UPLOAD'		=> $this->access->can_upload($album) ? $this->route('upload', ['album_id' => $album_id]) : '',
			'U_PF_EDIT'			=> $can_manage ? $this->route('album_edit', ['album_id' => $album_id]) : '',
			'U_PF_DELETE'		=> $this->access->can_delete_album($album) ? $this->route('album_delete', ['album_id' => $album_id]) : '',
			'U_PF_EXPORT'		=> ($this->access->can_manage_album($album) && ($this->access->acl('u_portfolio_export') || $this->access->is_admin())) ? $this->route('export', ['album' => $album_id]) : '',
			'U_PF_MEMBERS_MANAGE' => $this->access->can_manage_members($album) ? $this->route('album_members', ['album_id' => $album_id]) : '',
			'U_PF_SUBALBUM'		=> ($depth_ok && $this->access->can_create_subalbum($album)) ? $this->route('album_create', ['parent' => $album_id]) : '',
			'U_PF_BULK'			=> $this->route('album_bulk', ['album_id' => $album_id]),
		], $album);
	}

	/* ------------------------------------------------------------------
	 * Création / édition
	 * ------------------------------------------------------------------ */

	public function create()
	{
		$this->init();
		$this->require_login();

		$parent_id = $this->request->variable('parent', 0);
		$public = $this->request->variable('public', 0) && $this->access->can_create_public_album();
		$parent = null;

		if ($parent_id)
		{
			$parent = $this->load_album($parent_id);
			if (!$this->access->can_create_subalbum($parent))
			{
				$this->deny();
			}
			if ($this->albums->depth($parent_id) >= max(1, (int) $this->config['portfolio_max_depth']))
			{
				$this->deny('PORTFOLIO_MAX_DEPTH_REACHED');
			}
			$owner_id = (int) $parent['owner_id'];
		}
		else if ($public)
		{
			$owner_id = 0;
		}
		else
		{
			if (!$this->access->can_create_root_album())
			{
				$this->deny();
			}
			$max = $this->access->album_quota();
			if ($max && $this->albums->count_user_albums($this->access->user_id()) >= $max)
			{
				$this->deny('PORTFOLIO_MAX_ALBUMS_REACHED');
			}
			$owner_id = $this->access->user_id();
		}

		$defaults = [
			'album_id'			=> 0,
			'album_name'		=> '',
			'album_desc'		=> '',
			'album_desc_uid'	=> '',
			'album_desc_options' => 7,
			'album_visibility'	=> $parent ? (int) $parent['album_visibility'] : $this->access->default_visibility(),
			'album_locked'		=> 0,
			'album_friends'		=> $parent ? (int) $parent['album_friends'] : 0,
			'album_download'	=> $parent ? (int) $parent['album_download'] : (int) $this->config['portfolio_album_download_default'],
			// Tout nouvel album démarre avec le filigrane : seul le membre décide de le retirer (v1.10.1)
			'album_owner_no_wm'	=> 0,
			'album_sort'		=> 'date_desc',
			'parent_id'			=> $parent_id,
			'owner_id'			=> $owner_id,
		];

		return $this->form($defaults, $parent, true);
	}

	public function edit($album_id)
	{
		$this->init();
		$album = $this->load_album($album_id);
		if (!$this->access->can_manage_album($album))
		{
			$this->deny();
		}

		$parent = $album['parent_id'] ? $this->albums->get($album['parent_id']) : null;

		return $this->form($album, $parent, false);
	}

	protected function form(array $album, ?array $parent, $is_new)
	{
		$errors = [];
		$form_key = 'verturin_portfolio_album';
		$album_id = (int) $album['album_id'];
		$groups = $this->selectable_groups();

		// Choix laissés au membre (réglés dans l'ACP, v1.6.0)
		$allowed_vis = $this->access->allowed_visibilities((int) $album['owner_id'] !== 0, $is_new ? ($parent ? (int) $parent['album_visibility'] : null) : (int) $album['album_visibility']);
		$can_groups = in_array(access::VIS_GROUPS, $allowed_vis, true);
		$choice = [
			'lock'		=> $this->access->album_choice('lock'),
			'download'	=> $this->access->album_choice('download'),
			'sort'		=> $this->access->album_choice('sort'),
		];
		$current_groups = $album_id ? $this->albums->get_groups($album_id) : ($parent ? $this->albums->get_groups($parent['album_id']) : []);

		// Filigrane retiré par le propriétaire sur ses propres photos, si l'administration l'y autorise (v1.10.0)
		$wm_owner = (int) $album['owner_id'];
		$can_wm_optout = $wm_owner > 0 && (bool) $this->config['portfolio_wm_enabled']
			&& $this->access->wm_optout_allowed($wm_owner)
			&& ($this->access->user_id() === $wm_owner || $this->access->is_admin());

		// Destinations possibles pour déplacer l'album (même propriétaire, albums gérés)
		$owner_id = (int) $album['owner_id'];
		$parents = [];
		if (!$is_new)
		{
			foreach ($this->albums->flat_tree(function ($a) use ($owner_id) {
				return (int) $a['owner_id'] === $owner_id && $this->access->can_manage_album($a);
			}, $album_id) as $id => $node)
			{
				$parents[] = ['id' => $id, 'name' => str_repeat('— ', $node['depth']) . $node['album']['album_name'], 'selected' => $id === (int) $album['parent_id']];
			}
		}

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			$data = [
				'album_name'		=> $this->request->variable('album_name', '', true),
				'album_desc'		=> $this->request->variable('album_desc', '', true),
				'album_visibility'	=> min(4, max(0, $this->request->variable('album_visibility', (int) $album['album_visibility']))),
				'album_locked'		=> $choice['lock'] ? $this->request->variable('album_locked', 0) : (int) $album['album_locked'],
				'album_friends'		=> $can_groups ? $this->request->variable('album_friends', 0) : (int) $album['album_friends'],
				'album_download'	=> $choice['download'] ? $this->request->variable('album_download', 0) : (int) $album['album_download'],
				'album_sort'		=> $choice['sort'] ? $this->request->variable('album_sort', 'date_desc') : $album['album_sort'],
			];
			if ($can_wm_optout)
			{
				// Case « Protéger mes photos par le filigrane », cochée par défaut : décochée = sans filigrane
				$data['album_owner_no_wm'] = $this->request->variable('album_wm_keep', 0) ? 0 : 1;
			}
			else if ($is_new)
			{
				$data['album_owner_no_wm'] = 0;
			}

			// Visibilité non proposée au membre : on garde le réglage par défaut du forum
			if (!in_array($data['album_visibility'], $allowed_vis, true))
			{
				$default = $this->access->default_visibility();
				$data['album_visibility'] = in_array($default, $allowed_vis, true) ? $default : (int) reset($allowed_vis);
			}

			if ($can_groups)
			{
				$view_groups = array_map('intval', $this->request->variable('view_groups', [0]));
				$view_groups = array_values(array_intersect($view_groups, array_column($groups, 'id')));
			}
			else
			{
				// Le membre ne gère pas les groupes : ceux définis par l'administration sont conservés
				$view_groups = array_keys(array_filter($current_groups, function ($g) {
					return !empty($g['can_view']);
				}));
			}

			if (trim($data['album_name']) === '')
			{
				$errors[] = $this->language->lang('PORTFOLIO_ERR_ALBUM_NAME');
			}
			if (!$owner_id)
			{
				// Album public : ni « amis » ni amis en plus des groupes
				$data['album_friends'] = 0;
				if ($data['album_visibility'] === access::VIS_FRIENDS)
				{
					$data['album_visibility'] = access::VIS_PRIVATE;
				}
			}
			if ($data['album_visibility'] === access::VIS_GROUPS && !$view_groups && !$data['album_friends'])
			{
				$errors[] = $this->language->lang('PORTFOLIO_ERR_NO_GROUP');
			}

			$new_parent = $parent ? (int) $parent['album_id'] : 0;
			if (!$is_new)
			{
				$new_parent = $this->request->variable('parent_id', (int) $album['parent_id']);
				if ($new_parent && !in_array($new_parent, array_column($parents, 'id'), true))
				{
					$new_parent = (int) $album['parent_id'];
				}
				if (!$new_parent && $owner_id && !$this->access->can_create_root_album())
				{
					$new_parent = (int) $album['parent_id'];
				}
				$data['parent_id'] = $new_parent;
			}

			if (!$errors)
			{
				if ($is_new)
				{
					$data['parent_id'] = $new_parent;
					$data['owner_id'] = $owner_id;
					$album_id = $this->albums->create($data);
				}
				else
				{
					$this->albums->update($album_id, $data);

					// Même choix pour les sous-albums du même propriétaire, si demandé
					if ($can_wm_optout && $this->request->variable('album_wm_children', 0))
					{
						$all = $this->albums->get_all();
						$children = array_values(array_filter($this->albums->descendants($album_id), function ($id) use ($all, $wm_owner) {
							return isset($all[$id]) && (int) $all[$id]['owner_id'] === $wm_owner;
						}));
						$this->albums->update_many($children, ['album_owner_no_wm' => $data['album_owner_no_wm']]);
					}
				}

				$this->save_view_groups($album_id, (int) $data['album_visibility'], $view_groups, $groups);
				$this->access->reset();

				return new RedirectResponse($this->helper->route('verturin_portfolio_album', ['album_id' => $album_id], false));
			}

			$album = array_merge($album, $data);
			$album['album_desc_uid'] = '';
			$current_groups = array_fill_keys($view_groups, ['can_view' => 1]);
		}

		add_form_key($form_key);

		$desc = $album['album_desc'];
		if ($album_id && !$this->request->is_set_post('submit'))
		{
			$decoded = generate_text_for_edit($album['album_desc'], $album['album_desc_uid'], (int) $album['album_desc_options']);
			$desc = $decoded['text'];
		}

		$group_options = [];
		foreach ($groups as $group)
		{
			$group['checked'] = !empty($current_groups[$group['id']]['can_view']);
			$group_options[] = $group;
		}

		$sorts = [];
		foreach (album_manager::SORTS as $key)
		{
			$sorts[] = ['key' => $key, 'label' => $this->language->lang('PORTFOLIO_SORT_' . strtoupper($key)), 'selected' => $key === $album['album_sort']];
		}

		$title = $this->language->lang($is_new ? ($parent ? 'PORTFOLIO_NEW_SUBALBUM' : 'PORTFOLIO_NEW_ALBUM') : 'PORTFOLIO_EDIT_ALBUM');

		return $this->render('portfolio_album_form.html', $title, [
			'PF_FORM'			=> [
				'is_new'		=> $is_new,
				'name'			=> $album['album_name'],
				'desc'			=> $desc,
				'locked'		=> (bool) $album['album_locked'],
				'download'		=> !isset($album['album_download']) || (bool) $album['album_download'],
				'owner_no_wm'	=> !empty($album['album_owner_no_wm']),
				'has_children'	=> $album_id && (bool) $this->albums->descendants($album_id),
				'is_public'		=> $owner_id === 0,
				'parent_name'	=> $parent ? $parent['album_name'] : '',
			],
			'PF_ERRORS'			=> $errors,
			'PF_VISIBILITY'		=> $this->visibility_options($album['album_visibility'], $owner_id !== 0, $allowed_vis),
			'PF_CAN_GROUPS'		=> $can_groups,
			'PF_CAN_FRIENDS'	=> in_array(access::VIS_FRIENDS, $allowed_vis, true) || $can_groups,
			'PF_CHOICE'			=> $choice,
			'PF_CAN_WM_OPTOUT'	=> $can_wm_optout,
			'PF_GROUPS_SELECTED' => (int) $album['album_visibility'] === access::VIS_GROUPS,
			'PF_ALLOW_FRIENDS'	=> !empty($album['album_friends']),
			'PF_PERSONAL'		=> $owner_id !== 0,
			'U_PF_ZEBRA'		=> append_sid(generate_board_url() . '/ucp.php', 'i=zebra&amp;mode=friends'),
			'PF_GROUPS'			=> $group_options,
			'PF_SORT_OPTIONS'	=> $sorts,
			'PF_PARENTS'		=> $parents,
			'PF_CAN_ROOT'		=> !$is_new && ($owner_id === 0 ? $this->access->is_admin() : $this->access->can_create_root_album()),
			'PF_ROOT_SELECTED'	=> !(int) $album['parent_id'],
			'U_PF_ACTION'		=> $is_new
				? $this->route('album_create', array_filter(['parent' => $parent ? (int) $parent['album_id'] : 0, 'public' => $owner_id === 0 && !$parent ? 1 : 0]))
				: $this->route('album_edit', ['album_id' => $album_id]),
			'U_PF_CANCEL'		=> $album_id ? $this->route('album', ['album_id' => $album_id]) : ($parent ? $this->route('album', ['album_id' => (int) $parent['album_id']]) : $this->route('mine')),
		], $album_id ? $this->albums->get($album_id) : $parent);
	}

	/**
	 * Enregistre la liste des groupes autorisés à voir l'album (en conservant les autres droits définis par l'administration).
	 */
	protected function save_view_groups($album_id, $visibility, array $view_groups, array $groups)
	{
		$existing = $this->albums->get_groups($album_id);

		if ($visibility !== access::VIS_GROUPS)
		{
			// Hors mode « groupes », la colonne « voir » est neutre : on ne touche pas aux autres droits
			return;
		}

		$rows = $existing;
		foreach ($groups as $group)
		{
			$gid = (int) $group['id'];
			$checked = in_array($gid, $view_groups, true);
			if (isset($rows[$gid]))
			{
				$rows[$gid]['can_view'] = $checked ? 1 : 0;
			}
			else if ($checked)
			{
				$rows[$gid] = ['can_view' => 1, 'can_upload' => 0, 'can_rate' => 1, 'can_comment' => 1];
			}
		}

		$this->albums->set_groups($album_id, $rows);
	}

	/**
	 * Groupes proposés : groupes non cachés + groupes dont le membre fait partie.
	 */
	protected function selectable_groups()
	{
		$sql = 'SELECT g.group_id, g.group_name, g.group_type
			FROM ' . GROUPS_TABLE . ' g
			LEFT JOIN ' . USER_GROUP_TABLE . ' ug ON (ug.group_id = g.group_id AND ug.user_id = ' . $this->access->user_id() . ' AND ug.user_pending = 0)
			WHERE g.group_name <> \'BOTS\'
				AND (g.group_type <> ' . GROUP_HIDDEN . ' OR ug.user_id IS NOT NULL' . ($this->access->is_admin() ? ' OR 1 = 1' : '') . ')
			ORDER BY g.group_type DESC, g.group_name ASC';
		$result = $this->db->sql_query($sql);
		$groups = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[(int) $row['group_id']] = [
				'id'	=> (int) $row['group_id'],
				'name'	=> (int) $row['group_type'] === GROUP_SPECIAL ? $this->language->lang('G_' . $row['group_name']) : $row['group_name'],
			];
		}
		$this->db->sql_freeresult($result);

		return array_values($groups);
	}

	/* ------------------------------------------------------------------
	 * Suppression
	 * ------------------------------------------------------------------ */

	public function delete($album_id)
	{
		$this->init();
		$album = $this->load_album($album_id);
		if (!$this->access->can_delete_album($album))
		{
			$this->deny();
		}

		$album_id = (int) $album['album_id'];
		$back = $this->route('album', ['album_id' => $album_id]);

		// Règle de sécurité : un album doit être vidé (photos et sous-albums) avant d'être supprimé
		$active = $this->albums->count_active_photos($album_id);
		$children = count($this->albums->get_children($album_id));
		if ($active || $children)
		{
			return $this->render('portfolio_album_delete.html', $this->language->lang('PORTFOLIO_DELETE_ALBUM'), [
				'PF_ALBUM'		=> $this->presenter->album($album),
				'PF_ACTIVE'		=> $active,
				'PF_CHILDREN_COUNT' => $children,
				'U_PF_BACK'		=> $back,
			], $album);
		}

		if (confirm_box(true))
		{
			$error = $this->albums->delete($album_id);
			if ($error)
			{
				$this->deny($error);
			}

			$redirect = $album['parent_id']
				? $this->route('album', ['album_id' => (int) $album['parent_id']])
				: ((int) $album['owner_id'] ? $this->route('user', ['user_id' => (int) $album['owner_id']]) : $this->route('index'));

			return $this->done('PORTFOLIO_ALBUM_DELETED', $redirect, [$album['album_name']]);
		}

		confirm_box(false, $this->language->lang('PORTFOLIO_DELETE_ALBUM_CONFIRM', $album['album_name']), build_hidden_fields(['album_id' => $album_id]));

		return new RedirectResponse($back);
	}

	/* ------------------------------------------------------------------
	 * Co-gestion (mari et femme, famille, équipe…)
	 * ------------------------------------------------------------------ */

	public function members($album_id)
	{
		$this->init();
		$album = $this->load_album($album_id);
		if (!$this->access->can_manage_members($album))
		{
			$this->deny();
		}

		return $this->manage_members((int) $album['owner_id'], (int) $album['album_id'], $album);
	}

	/**
	 * Partage (v1.7.0) : vue d'ensemble de mes albums, partagés album par album.
	 * Un partage s'applique à l'album choisi et à tous ses sous-albums (héritage).
	 */
	public function sharing()
	{
		$this->init();
		$this->require_login();
		if (!$this->access->acl('u_portfolio_share'))
		{
			$this->deny();
		}

		$owner_id = $this->access->user_id();
		$form_key = 'verturin_portfolio_sharing';
		$errors = [];
		$tree = $this->albums->flat_tree(function ($a) use ($owner_id) {
			return (int) $a['owner_id'] === $owner_id;
		});

		if ($this->request->is_set_post('add') || $this->request->is_set_post('remove') || $this->request->is_set_post('update'))
		{
			if (!check_form_key($form_key))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}
			else if ($this->request->is_set_post('remove'))
			{
				list($album_id, $user_id) = array_map('intval', explode('_', $this->request->variable('remove', '0_0')) + [0, 0]);
				if (isset($tree[$album_id]))
				{
					$this->albums->remove_member($owner_id, $album_id, $user_id);
				}

				return new RedirectResponse($this->route('sharing'));
			}
			else if ($this->request->is_set_post('update'))
			{
				// Changement du rôle d'un membre (v1.8.3)
				$key = $this->request->variable('update', '0_0');
				list($album_id, $user_id) = array_map('intval', explode('_', $key) + [0, 0]);
				$roles = $this->request->variable('roles', ['' => 0]);
				$role = isset($roles[$key]) ? (int) $roles[$key] : 0;
				if (isset($tree[$album_id]) && $user_id && in_array($role, [access::ROLE_VIEWER, access::ROLE_CONTRIBUTOR, access::ROLE_MANAGER], true))
				{
					$this->albums->set_member_role($owner_id, $album_id, $user_id, $role);
				}

				return new RedirectResponse($this->route('sharing'));
			}
			else
			{
				$album_id = $this->request->variable('album_id', 0);
				$username = $this->request->variable('username', '', true);
				$role = $this->request->variable('member_role', access::ROLE_VIEWER);
				$role = in_array($role, [access::ROLE_VIEWER, access::ROLE_CONTRIBUTOR, access::ROLE_MANAGER], true) ? $role : access::ROLE_VIEWER;
				$user_id = $this->find_user($username);

				if (!isset($tree[$album_id]))
				{
					$errors[] = $this->language->lang('PORTFOLIO_ERR_PICK_ALBUM');
				}
				else if (!$user_id)
				{
					$errors[] = $this->language->lang('PORTFOLIO_ERR_USER_UNKNOWN', $username);
				}
				else if ($user_id === $owner_id)
				{
					$errors[] = $this->language->lang('PORTFOLIO_ERR_USER_IS_OWNER');
				}
				else
				{
					$this->albums->add_member($owner_id, $album_id, $user_id, $role);

					return new RedirectResponse($this->route('sharing'));
				}
			}
		}

		add_form_key($form_key);

		$role_names = [access::ROLE_VIEWER => 'PORTFOLIO_ROLE_VIEWER', access::ROLE_CONTRIBUTOR => 'PORTFOLIO_ROLE_CONTRIBUTOR', access::ROLE_MANAGER => 'PORTFOLIO_ROLE_MANAGER'];
		$albums = [];
		$direct = [];
		foreach ($tree as $id => $node)
		{
			$direct[$id] = $this->albums->get_members($owner_id, $id);
		}
		foreach ($tree as $id => $node)
		{
			$members = [];
			foreach ($direct[$id] as $row)
			{
				$members[] = [
					'key'		=> $id . '_' . (int) $row['user_id'],
					'name'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
					'username'	=> $row['username'],
					'role'		=> $this->language->lang($role_names[(int) $row['member_role']] ?? 'PORTFOLIO_ROLE_VIEWER'),
					'role_id'	=> (int) $row['member_role'],
				];
			}
			$inherited = [];
			foreach ($this->albums->get_chain($id) as $ancestor)
			{
				$aid = (int) $ancestor['album_id'];
				if ($aid === $id || empty($direct[$aid]))
				{
					continue;
				}
				foreach ($direct[$aid] as $row)
				{
					$inherited[] = [
						'name'	=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
						'role'	=> $this->language->lang($role_names[(int) $row['member_role']] ?? 'PORTFOLIO_ROLE_VIEWER'),
						'from'	=> $ancestor['album_name'],
					];
				}
			}
			$albums[] = [
				'id'		=> $id,
				'name'		=> $node['album']['album_name'],
				'depth'		=> $node['depth'],
				'url'		=> $this->route('album', ['album_id' => $id]),
				'members'	=> $members,
				'inherited'	=> $inherited,
				'option'	=> str_repeat('— ', $node['depth']) . $node['album']['album_name'],
			];
		}

		return $this->render('portfolio_sharing.html', $this->language->lang('PORTFOLIO_SHARING'), [
			'PF_ALBUMS'			=> $albums,
			'PF_ERRORS'			=> $errors,
			'PF_PICK_ALBUM'		=> $this->request->variable('album', 0),
			'U_PF_ACTION'		=> $this->route('sharing'),
			'U_PF_BACK'			=> $this->route('mine'),
			'U_PF_FIND_USER'	=> append_sid(generate_board_url() . '/memberlist.php', 'mode=searchuser&amp;form=pf_members&amp;field=username&amp;select_single=true'),
		]);
	}

	protected function manage_members($owner_id, $album_id, ?array $album)
	{
		$form_key = 'verturin_portfolio_members';
		$errors = [];
		$self = $album_id ? $this->route('album_members', ['album_id' => $album_id]) : $this->route('sharing');

		if ($this->request->is_set_post('add') || $this->request->is_set_post('remove') || $this->request->is_set_post('update'))
		{
			if (!check_form_key($form_key))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}
			else if ($this->request->is_set_post('remove'))
			{
				$this->albums->remove_member($owner_id, $album_id, $this->request->variable('remove', 0));

				return new RedirectResponse($self);
			}
			else if ($this->request->is_set_post('update'))
			{
				$user_id = $this->request->variable('update', 0);
				$roles = $this->request->variable('roles', [0 => 0]);
				$role = isset($roles[$user_id]) ? (int) $roles[$user_id] : 0;
				if ($user_id && in_array($role, [access::ROLE_VIEWER, access::ROLE_CONTRIBUTOR, access::ROLE_MANAGER], true))
				{
					$this->albums->set_member_role($owner_id, $album_id, $user_id, $role);
				}

				return new RedirectResponse($self);
			}
			else
			{
				$username = $this->request->variable('username', '', true);
				$role = $this->request->variable('member_role', access::ROLE_MANAGER);
				$role = in_array($role, [access::ROLE_VIEWER, access::ROLE_CONTRIBUTOR, access::ROLE_MANAGER], true) ? $role : access::ROLE_VIEWER;
				$user_id = $this->find_user($username);

				if (!$user_id)
				{
					$errors[] = $this->language->lang('PORTFOLIO_ERR_USER_UNKNOWN', $username);
				}
				else if ($user_id === $owner_id)
				{
					$errors[] = $this->language->lang('PORTFOLIO_ERR_USER_IS_OWNER');
				}
				else
				{
					$this->albums->add_member($owner_id, $album_id, $user_id, $role);

					return new RedirectResponse($self);
				}
			}
		}

		add_form_key($form_key);

		$members = [];
		foreach ($this->albums->get_members($owner_id, $album_id) as $row)
		{
			$members[] = [
				'user_id'	=> (int) $row['user_id'],
				'name'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
				'role'		=> $this->language->lang([access::ROLE_VIEWER => 'PORTFOLIO_ROLE_VIEWER', access::ROLE_CONTRIBUTOR => 'PORTFOLIO_ROLE_CONTRIBUTOR', access::ROLE_MANAGER => 'PORTFOLIO_ROLE_MANAGER'][(int) $row['member_role']] ?? 'PORTFOLIO_ROLE_VIEWER'),
				'role_id'	=> (int) $row['member_role'],
				'username'	=> $row['username'],
				'since'		=> $this->user->format_date((int) $row['member_time']),
			];
		}

		// Partages hérités (rappel) : partage global ou albums parents
		$inherited = [];
		if ($album)
		{
			foreach ($this->albums->get_chain($album_id) as $node)
			{
				if ((int) $node['album_id'] === $album_id)
				{
					continue;
				}
				foreach ($this->albums->get_members($owner_id, $node['album_id']) as $row)
				{
					$inherited[] = ['name' => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']), 'from' => $node['album_name']];
				}
			}
		}

		return $this->render('portfolio_members_manage.html', $this->language->lang($album ? 'PORTFOLIO_ALBUM_MEMBERS' : 'PORTFOLIO_SHARING'), [
			'PF_MEMBERS'		=> $members,
			'PF_INHERITED'		=> $inherited,
			'PF_ERRORS'			=> $errors,
			'PF_ALBUM_NAME'		=> $album ? $album['album_name'] : '',
			'PF_IS_GLOBAL'		=> !$album,
			'U_PF_ACTION'		=> $self,
			'U_PF_BACK'			=> $album ? $this->route('album', ['album_id' => $album_id]) : $this->route('mine'),
			'U_PF_FIND_USER'	=> append_sid(generate_board_url() . '/memberlist.php', 'mode=searchuser&amp;form=pf_members&amp;field=username&amp;select_single=true'),
		], $album);
	}

	protected function find_user($username)
	{
		$username = trim($username);
		if ($username === '')
		{
			return 0;
		}

		$sql = 'SELECT user_id FROM ' . USERS_TABLE . "
			WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'
				AND user_type <> " . USER_IGNORE;
		$result = $this->db->sql_query($sql);
		$user_id = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		return $user_id;
	}

	/* ------------------------------------------------------------------
	 * Actions groupées (sélection de photos)
	 * ------------------------------------------------------------------ */

	public function bulk($album_id)
	{
		$this->init();
		$this->require_login();
		$album = $this->load_album($album_id);
		$back = $this->route('album', ['album_id' => (int) $album['album_id']]);

		if (!check_form_key('verturin_portfolio_bulk'))
		{
			$this->deny('FORM_INVALID');
		}

		$action = $this->request->variable('action', '');
		$ids = array_filter(array_map('intval', $this->request->variable('photo_ids', [0])));
		if (!$ids)
		{
			return $this->done('PORTFOLIO_NOTHING_SELECTED', $back);
		}

		$selected = [];
		foreach ($this->photos->get_many($ids) as $photo)
		{
			if ((int) $photo['album_id'] === (int) $album['album_id'])
			{
				$selected[(int) $photo['photo_id']] = $photo;
			}
		}

		switch ($action)
		{
			case 'delete':
				$allowed = array_keys(array_filter($selected, function ($photo) use ($album) {
					return $this->access->can_delete_photo($album, $photo);
				}));

				$exempt = $this->access->is_admin() || $this->access->acl('m_portfolio_delete');
				if (!$this->photos->deletion_allowed($this->access->user_id(), count($allowed), $exempt))
				{
					$this->deny('PORTFOLIO_DELETE_BLOCKED');
				}

				$reason = trim($this->request->variable('reason', '', true));
				if ($allowed && $reason === '' && $this->config['portfolio_trash_reason_required'])
				{
					return $this->done('PORTFOLIO_REASON_MISSING', $back);
				}
				$count = $this->photos->trash($allowed, $reason);

				return $this->done('PORTFOLIO_PHOTOS_TRASHED', $back, [$count]);

			case 'move':
				$target = $this->albums->get($this->request->variable('target_album', 0));
				if (!$target || !$this->access->can_upload($target))
				{
					$this->deny();
				}

				$allowed = array_keys(array_filter($selected, function ($photo) use ($album, $target) {
					return $this->access->can_move_photo($album, $photo) && $this->same_owner($album, $target, $photo);
				}));
				$count = $this->photos->move($allowed, (int) $target['album_id']);

				return $this->done('PORTFOLIO_PHOTOS_MOVED', $back, [$count, $target['album_name']]);

			case 'approve':
				if (!$this->access->can_approve_album($album))
				{
					$this->deny();
				}
				$count = $this->photos->approve(array_keys($selected));

				return $this->done('PORTFOLIO_PHOTOS_APPROVED', $back, [$count]);
		}

		return new RedirectResponse($back);
	}
}
