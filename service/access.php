<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Toutes les décisions de droits passent par ce service.
 *
 * Deux niveaux se combinent :
 *  1. les permissions phpBB (u_portfolio_*, m_portfolio_*, a_portfolio_*), attribuées
 *     par groupe dans l'ACP (Permissions > Permissions des groupes) ;
 *  2. les droits par album et par groupe (table portfolio_album_groups) et la co-gestion
 *     (table portfolio_members), qui restreignent ou étendent l'accès à un album donné.
 */
class access
{
	const VIS_ALL			= 0;
	const VIS_REGISTERED	= 1;
	const VIS_GROUPS		= 2;
	const VIS_PRIVATE		= 3;
	const VIS_FRIENDS		= 4;

	/** Icône et code couleur de chaque visibilité (badges des cartes d'album, v1.6.0) */
	const VIS_ICONS = [0 => 'globe', 1 => 'user', 2 => 'users', 3 => 'lock', 4 => 'heart'];
	const VIS_CODES = [0 => 'all', 1 => 'registered', 2 => 'groups', 3 => 'private', 4 => 'friends'];

	const ROLE_NONE			= 0;
	const ROLE_VIEWER		= 1;	// Invité : voit l'album, rien d'autre (v1.3.0)
	const ROLE_CONTRIBUTOR	= 2;
	const ROLE_MANAGER		= 3;
	const ROLE_OWNER		= 4;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\user */
	protected $user;

	/** @var \verturin\portfolio\service\settings Réglages du Portfolio */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var album_manager */
	protected $albums;

	/** @var string */
	protected $members_table;

	/** @var string */
	protected $album_groups_table;

	/** @var array|null [owner_id => [album_id => role]] */
	protected $memberships;

	/** @var array|null */
	protected $user_groups;

	/** @var array|null [album_id => [group_id => row]] */
	protected $album_groups;

	/** @var array */
	protected $view_cache = [];

	/** @var array [owner_id => bool] */
	protected $friend_of = [];

	public function __construct(\phpbb\auth\auth $auth, \phpbb\user $user, \verturin\portfolio\service\settings $config, \phpbb\db\driver\driver_interface $db, album_manager $albums, $members_table, $album_groups_table)
	{
		$this->auth = $auth;
		$this->user = $user;
		$this->config = $config;
		$this->db = $db;
		$this->albums = $albums;
		$this->members_table = $members_table;
		$this->album_groups_table = $album_groups_table;
	}

	/* ------------------------------------------------------------------
	 * Identité
	 * ------------------------------------------------------------------ */

	public function user_id()
	{
		return (int) $this->user->data['user_id'];
	}

	public function is_registered()
	{
		return !empty($this->user->data['is_registered']) && empty($this->user->data['is_bot']);
	}

	public function acl($permission)
	{
		return (bool) $this->auth->acl_get($permission);
	}

	/** @var array Membres autorisés à retirer le filigrane de leurs photos (cache de la page) */
	protected $wm_optout_cache = [];

	/**
	 * Le membre fait-il partie de ceux que l'administration autorise à retirer le filigrane
	 * de ses propres photos (page Filigrane, v1.10.0) ?
	 */
	public function wm_optout_allowed($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id <= ANONYMOUS || !$this->config['portfolio_wm_optout'])
		{
			return false;
		}
		if (isset($this->wm_optout_cache[$user_id]))
		{
			return $this->wm_optout_cache[$user_id];
		}
		// Le membre a renoncé lui-même à cette option dans son panneau de l'utilisateur (v1.10.2)
		if (in_array($user_id, array_map('intval', explode(',', (string) $this->config['portfolio_wm_optout_excluded'])), true))
		{
			return $this->wm_optout_cache[$user_id] = false;
		}

		$users = array_filter(array_map('intval', explode(',', (string) $this->config['portfolio_wm_optout_users'])));
		$allowed = in_array($user_id, $users, true);
		$groups = array_filter(array_map('intval', explode(',', (string) $this->config['portfolio_wm_optout_groups'])));
		if (!$allowed && $groups)
		{
			$sql = 'SELECT group_id FROM ' . USER_GROUP_TABLE . '
				WHERE user_id = ' . $user_id . '
					AND user_pending = 0
					AND ' . $this->db->sql_in_set('group_id', $groups);
			$result = $this->db->sql_query_limit($sql, 1);
			$allowed = (bool) $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
		}

		return $this->wm_optout_cache[$user_id] = $allowed;
	}

	public function reset_wm_optout_cache()
	{
		$this->wm_optout_cache = [];
	}

	/**
	 * Photo affichée sans filigrane parce que son auteur l'a demandé pour cet album (v1.10.0) :
	 * seulement pour les photos du propriétaire de l'album, et tant qu'il reste autorisé.
	 */
	public function owner_removed_watermark(array $photo, ?array $album)
	{
		return $album && !empty($album['album_owner_no_wm'])
			&& (int) $album['owner_id'] > 0
			&& (int) $photo['user_id'] === (int) $album['owner_id']
			&& $this->wm_optout_allowed((int) $album['owner_id']);
	}

	public function is_admin()
	{
		return $this->acl('a_portfolio_manage');
	}

	public function can_admin_trash()
	{
		return $this->acl('a_portfolio_trash');
	}

	public function can_use()
	{
		return $this->acl('u_portfolio_view');
	}

	public function is_moderator()
	{
		return $this->acl('m_portfolio_approve') || $this->acl('m_portfolio_edit') || $this->acl('m_portfolio_delete') || $this->acl('m_portfolio_comments');
	}

	/* ------------------------------------------------------------------
	 * Rôle sur un album (propriétaire, co-gestionnaire, contributeur)
	 * ------------------------------------------------------------------ */

	public function role(array $album)
	{
		if (!$this->is_registered())
		{
			return self::ROLE_NONE;
		}

		$owner_id = (int) $album['owner_id'];
		if ($owner_id && $owner_id === $this->user_id())
		{
			return self::ROLE_OWNER;
		}

		$memberships = $this->load_memberships();
		if (empty($memberships[$owner_id]))
		{
			return self::ROLE_NONE;
		}

		$role = self::ROLE_NONE;
		$rows = $memberships[$owner_id];

		// Partage sur l'album ou l'un de ses parents (hérité par les sous-albums)
		foreach ($this->albums->get_chain((int) $album['album_id']) as $ancestor)
		{
			if (isset($rows[(int) $ancestor['album_id']]))
			{
				$role = max($role, $rows[(int) $ancestor['album_id']]);
			}
		}

		return $role;
	}

	/* ------------------------------------------------------------------
	 * Albums
	 * ------------------------------------------------------------------ */

	public function can_view_album(array $album)
	{
		$album_id = (int) $album['album_id'];
		if (isset($this->view_cache[$album_id]))
		{
			return $this->view_cache[$album_id];
		}

		$allowed = $this->can_use();
		// Partage explicite (v1.7.0) : un membre invité sur un album le voit, ainsi que ses sous-albums,
		// même si l'album parent reste privé.
		if ($allowed && !$this->is_admin() && !$this->acl('m_portfolio_edit') && $this->role($album) < self::ROLE_VIEWER)
		{
			foreach ($this->albums->get_chain($album_id) as $node)
			{
				if (!$this->can_view_single($node))
				{
					$allowed = false;
					break;
				}
			}
		}

		return $this->view_cache[$album_id] = $allowed;
	}

	protected function can_view_single(array $album)
	{
		if ($this->role($album) >= self::ROLE_VIEWER)
		{
			return true;
		}

		switch ((int) $album['album_visibility'])
		{
			case self::VIS_ALL:
				return true;

			case self::VIS_REGISTERED:
				return $this->is_registered();

			case self::VIS_GROUPS:
				// Groupes choisis, et éventuellement aussi les amis du propriétaire
				return $this->group_allows($album, 'can_view', false)
					|| (!empty($album['album_friends']) && $this->is_friend_of((int) $album['owner_id']));

			case self::VIS_FRIENDS:
				return $this->is_friend_of((int) $album['owner_id']);

			case self::VIS_PRIVATE:
			default:
				return false;
		}
	}

	/**
	 * Le visiteur figure-t-il dans la liste d'amis phpBB du propriétaire ?
	 * (Dans phpBB, c'est le propriétaire qui ajoute ses amis : UCP › Amis et ignorés.)
	 */
	public function is_friend_of($owner_id)
	{
		if (!$owner_id || !$this->is_registered())
		{
			return false;
		}
		if ($owner_id === $this->user_id())
		{
			return true;
		}

		if (!isset($this->friend_of[$owner_id]))
		{
			$sql = 'SELECT user_id FROM ' . ZEBRA_TABLE . '
				WHERE user_id = ' . (int) $owner_id . '
					AND zebra_id = ' . $this->user_id() . '
					AND friend = 1';
			$result = $this->db->sql_query_limit($sql, 1);
			$this->friend_of[$owner_id] = (bool) $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
		}

		return $this->friend_of[$owner_id];
	}

	/**
	 * Pourquoi l'album n'est-il pas visible ? Utilisé pour un message clair au lieu de « introuvable ».
	 *
	 * @return array ['reason' => login|registered|groups|friends|private|access, 'album' => album bloquant]
	 */
	public function deny_reason(array $album)
	{
		if (!$this->can_use())
		{
			return ['reason' => $this->is_registered() ? 'access' : 'login', 'album' => $album];
		}

		foreach ($this->albums->get_chain((int) $album['album_id']) as $node)
		{
			if ($this->can_view_single($node))
			{
				continue;
			}

			if (!$this->is_registered())
			{
				return ['reason' => 'login', 'album' => $node];
			}

			switch ((int) $node['album_visibility'])
			{
				case self::VIS_GROUPS:
					$reason = !empty($node['album_friends']) ? 'groups_friends' : 'groups';
				break;
				case self::VIS_FRIENDS:
					$reason = 'friends';
				break;
				default:
					$reason = 'private';
			}

			return ['reason' => $reason, 'album' => $node];
		}

		return ['reason' => 'access', 'album' => $album];
	}

	/**
	 * Identifiants de tous les albums visibles par l'utilisateur courant.
	 */
	public function viewable_album_ids()
	{
		$ids = [];
		foreach ($this->albums->get_all() as $album)
		{
			if ($this->can_view_album($album))
			{
				$ids[] = (int) $album['album_id'];
			}
		}

		return $ids;
	}

	public function can_create_root_album()
	{
		return $this->is_registered() && ($this->acl('u_portfolio_create_album') || $this->is_admin());
	}

	/**
	 * Visibilités qu'un membre peut choisir à la création / modification d'un album (v1.6.0).
	 * La liste est réglée dans l'ACP ; « Groupes choisis » demande en plus la permission
	 * u_portfolio_vis_groups. Les administrateurs du Portfolio ont toujours tous les choix.
	 *
	 * @param bool     $personal Album d'un membre (false = album public sans propriétaire)
	 * @param int|null $current  Visibilité actuelle de l'album (toujours conservable)
	 * @return int[]
	 */
	public function allowed_visibilities($personal = true, $current = null)
	{
		$order = [self::VIS_ALL, self::VIS_REGISTERED, self::VIS_GROUPS, self::VIS_FRIENDS, self::VIS_PRIVATE];

		if ($this->is_admin())
		{
			$allowed = $order;
		}
		else
		{
			$allowed = array_map('intval', array_filter(explode(',', (string) $this->config['portfolio_vis_choices']), 'strlen'));
			if ($this->acl('u_portfolio_vis_groups'))
			{
				$allowed[] = self::VIS_GROUPS;
			}
			else
			{
				$allowed = array_diff($allowed, [self::VIS_GROUPS]);
			}
			if (!$allowed)
			{
				$allowed = [$this->default_visibility()];
			}
		}

		if (!$personal)
		{
			$allowed = array_diff($allowed, [self::VIS_FRIENDS]);
		}
		if ($current !== null && $current !== '')
		{
			$allowed[] = (int) $current;
		}

		return array_values(array_intersect($order, $allowed));
	}

	public function default_visibility()
	{
		$default = (int) $this->config['portfolio_vis_default'];

		return in_array($default, [self::VIS_ALL, self::VIS_REGISTERED, self::VIS_GROUPS, self::VIS_FRIENDS, self::VIS_PRIVATE], true) ? $default : self::VIS_REGISTERED;
	}

	/**
	 * Option de création d'album laissée au choix du membre ? (verrouillage, téléchargement, tri)
	 */
	public function album_choice($name)
	{
		return $this->is_admin() || (bool) $this->config['portfolio_album_' . $name . '_choice'];
	}

	public function can_create_public_album()
	{
		return $this->is_admin();
	}

	public function can_create_subalbum(array $parent)
	{
		if ($this->is_admin())
		{
			return true;
		}

		return $this->acl('u_portfolio_create_album') && $this->role($parent) >= self::ROLE_MANAGER;
	}

	public function can_manage_album(array $album)
	{
		return $this->is_admin() || ($this->is_registered() && $this->role($album) >= self::ROLE_MANAGER);
	}

	public function can_manage_members(array $album)
	{
		if ($this->is_admin())
		{
			return true;
		}

		return $this->role($album) === self::ROLE_OWNER && $this->acl('u_portfolio_share');
	}

	public function can_delete_album(array $album)
	{
		return $this->is_admin() || (!$this->is_protected($album) && $this->role($album) === self::ROLE_OWNER && $this->can_delete_own());
	}

	public function can_upload(array $album)
	{
		if (!$this->is_registered() || !$this->can_view_album($album))
		{
			return false;
		}

		if ($this->is_admin())
		{
			return true;
		}

		if (!$this->acl('u_portfolio_upload'))
		{
			return false;
		}

		$role = $this->role($album);
		if (!empty($album['album_locked']) && $role < self::ROLE_MANAGER)
		{
			return false;
		}

		if ($role >= self::ROLE_CONTRIBUTOR)
		{
			return true;
		}

		// Album personnel d'un autre membre : seuls propriétaire et co-gestionnaires envoient
		if ((int) $album['owner_id'] !== 0)
		{
			return false;
		}

		return $this->group_allows($album, 'can_upload', true);
	}

	/**
	 * Les photos envoyées doivent-elles être validées par un modérateur ?
	 */
	public function needs_approval(array $album)
	{
		if ($this->approval_forced())
		{
			return true;
		}

		return !$this->acl('u_portfolio_noapprove')
			&& !$this->can_manage_album($album)
			&& !$this->acl('m_portfolio_approve');
	}

	/** @var group_limits|null Quotas et validation par groupe (v1.10.3) */
	protected $group_limits;

	public function set_group_limits(group_limits $group_limits)
	{
		$this->group_limits = $group_limits;
	}

	/**
	 * Un des groupes du membre impose la validation de toutes ses photos, y compris dans
	 * ses propres albums (v1.10.3). Modérateurs et administrateurs n'y sont pas soumis.
	 */
	public function approval_forced()
	{
		if (!$this->group_limits || !$this->is_registered() || $this->acl('m_portfolio_approve') || $this->is_admin())
		{
			return false;
		}
		$limits = $this->group_limits->for_user($this->user_id());

		return $limits['approval'];
	}

	/**
	 * Peut valider les photos en attente de cet album : modérateur, ou gestionnaire de l'album
	 * qui n'est pas lui-même soumis à la validation obligatoire.
	 */
	public function can_approve_album(array $album)
	{
		return $this->can_approve() || ($this->can_manage_album($album) && !$this->approval_forced());
	}

	/**
	 * Nombre maximum de photos du membre (0 = illimité) : réglage de ses groupes, sinon réglage général.
	 */
	public function photo_quota()
	{
		return $this->resolve_quota('photos', 'portfolio_user_quota');
	}

	/**
	 * Nombre maximum d'albums du membre (0 = illimité).
	 */
	public function album_quota()
	{
		return $this->resolve_quota('albums', 'portfolio_user_max_albums');
	}

	protected function resolve_quota($key, $setting)
	{
		if ($this->acl('u_portfolio_unlimited') || $this->is_admin())
		{
			return 0;
		}
		if ($this->group_limits)
		{
			$limits = $this->group_limits->for_user($this->user_id());
			if ($limits[$key] >= 0)
			{
				return $limits[$key];
			}
		}

		return max(0, (int) $this->config[$setting]);
	}

	/**
	 * Quota de photos applicable, ou -1 si illimité.
	 */
	public function upload_quota_left()
	{
		$quota = $this->photo_quota();

		return $quota > 0 ? $quota : -1;
	}

	/* ------------------------------------------------------------------
	 * Photos
	 * ------------------------------------------------------------------ */

	public function is_uploader(array $photo)
	{
		return $this->is_registered() && (int) $photo['user_id'] === $this->user_id();
	}

	/**
	 * Visibilité d'une photo selon son statut (en attente / publiée / corbeille).
	 */
	public function can_view_photo(?array $album, array $photo)
	{
		$status = (int) $photo['photo_status'];

		if ($status === photo_manager::STATUS_TRASH)
		{
			return $this->can_admin_trash()
				|| $this->is_uploader($photo)
				|| ($album && $this->can_manage_album($album));
		}

		if (!$album || !$this->can_view_album($album))
		{
			return false;
		}

		if ($status === photo_manager::STATUS_PENDING)
		{
			return $this->is_uploader($photo) || $this->can_manage_album($album) || $this->acl('m_portfolio_approve');
		}

		return true;
	}

	/**
	 * Côté « propriétaire » d'une photo (v1.6.0) : la personne qui l'a postée, ou un membre
	 * qu'elle a autorisé (propriétaire de l'album / co-gestionnaire). Les administrateurs n'en font
	 * pas partie : ils passent par les droits de modération.
	 */
	public function is_photo_owner_side(array $album, array $photo)
	{
		return $this->is_registered() && ($this->is_uploader($photo) || $this->role($album) >= self::ROLE_MANAGER);
	}

	/** Rotation, déplacement, couverture : réservés au posteur et aux membres qu'il a autorisés. */
	public function can_tweak_photo(array $album, array $photo)
	{
		return $this->is_photo_owner_side($album, $photo);
	}

	/** Couverture de l'album : propriétaire et co-gestionnaires uniquement. */
	public function can_set_cover(array $album)
	{
		return $this->is_registered() && $this->role($album) >= self::ROLE_MANAGER;
	}

	public function can_edit_photo(array $album, array $photo)
	{
		return $this->is_photo_owner_side($album, $photo) || $this->acl('m_portfolio_edit');
	}

	public function can_delete_photo(array $album, array $photo)
	{
		if ($this->is_protected($album))
		{
			// Album protégé (page Portfolio) : seuls administrateurs et modérateurs suppriment
			return $this->is_admin() || $this->acl('m_portfolio_delete');
		}

		// Permission « Peut supprimer ses photos » (v1.10.5), accordée par défaut
		return ($this->is_photo_owner_side($album, $photo) && $this->can_delete_own())
			|| $this->acl('m_portfolio_delete');
	}

	/**
	 * Le membre peut-il mettre ses propres photos (et albums) à la corbeille ?
	 */
	public function can_delete_own()
	{
		return $this->is_admin() || $this->acl('u_portfolio_delete');
	}

	/** Déplacer une photo hors de son album : interdit aux membres dans un album protégé. */
	public function can_move_photo(array $album, array $photo)
	{
		if ($this->is_protected($album))
		{
			return $this->is_admin() || $this->acl('m_portfolio_edit');
		}

		return $this->can_tweak_photo($album, $photo);
	}

	/**
	 * Album protégé (v1.8.0), ou sous-album d'un album protégé : les membres y déposent des photos
	 * mais ne peuvent ni les supprimer ni les déplacer.
	 */
	public function is_protected(array $album)
	{
		foreach ($this->albums->get_chain((int) $album['album_id']) as $node)
		{
			if (!empty($node['album_protected']))
			{
				return true;
			}
		}

		return false;
	}

	public function can_restore_photo(array $photo, ?array $album = null)
	{
		if ($this->can_admin_trash())
		{
			return true;
		}

		if (!$this->acl('u_portfolio_restore'))
		{
			return false;
		}

		return $this->is_uploader($photo) || ($album && $this->role($album) >= self::ROLE_MANAGER);
	}

	public function can_approve()
	{
		return $this->acl('m_portfolio_approve') || $this->is_admin();
	}

	public function can_rate(array $album, array $photo)
	{
		if (!$this->is_registered() || !$this->acl('u_portfolio_rate') || (int) $photo['photo_status'] !== photo_manager::STATUS_APPROVED)
		{
			return false;
		}

		if (!$this->config['portfolio_rate_own'] && $this->is_uploader($photo))
		{
			return false;
		}

		if (!empty($album['album_locked']) && !$this->can_manage_album($album))
		{
			return false;
		}

		return $this->can_view_album($album) && $this->group_allows($album, 'can_rate', true);
	}

	public function can_comment(array $album, array $photo)
	{
		if (!$this->is_registered() || !$this->acl('u_portfolio_comment') || (int) $photo['photo_status'] !== photo_manager::STATUS_APPROVED)
		{
			return false;
		}

		if (!empty($album['album_locked']) && !$this->can_manage_album($album))
		{
			return false;
		}

		return $this->can_view_album($album) && $this->group_allows($album, 'can_comment', true);
	}

	public function can_delete_comment(array $comment, array $album)
	{
		return $this->acl('m_portfolio_comments')
			|| $this->can_manage_album($album)
			|| ($this->is_registered() && (int) $comment['user_id'] === $this->user_id());
	}

	/**
	 * Téléchargement de l'original : permission du groupe ET accord du propriétaire de l'album (v1.5.0).
	 * Propriétaire, co-gestionnaires, contributeurs et administrateurs gardent toujours l'accès.
	 */
	public function can_download(array $album)
	{
		if ($this->role($album) >= self::ROLE_CONTRIBUTOR || $this->is_admin())
		{
			return true;
		}

		if (isset($album['album_download']) && !(int) $album['album_download'])
		{
			return false;
		}

		return $this->acl('u_portfolio_download');
	}

	public function can_react(array $album, array $photo)
	{
		return $this->is_registered()
			&& $this->acl('u_portfolio_react')
			&& (int) $photo['photo_status'] === photo_manager::STATUS_APPROVED
			&& $this->can_view_album($album)
			&& (empty($album['album_locked']) || $this->can_manage_album($album));
	}

	/* ------------------------------------------------------------------
	 * Droits par groupe sur un album
	 * ------------------------------------------------------------------ */

	/**
	 * @param array  $album
	 * @param string $flag            can_view | can_upload | can_rate | can_comment
	 * @param bool   $default_if_none Valeur si aucun droit de groupe n'est défini pour l'album
	 */
	public function group_allows(array $album, $flag, $default_if_none)
	{
		$rows = $this->get_album_groups((int) $album['album_id']);
		if (empty($rows))
		{
			return $default_if_none;
		}

		foreach ($this->load_user_groups() as $group_id)
		{
			if (!empty($rows[$group_id][$flag]))
			{
				return true;
			}
		}

		return false;
	}

	public function get_album_groups($album_id)
	{
		if ($this->album_groups === null)
		{
			$this->album_groups = [];
			$sql = 'SELECT * FROM ' . $this->album_groups_table;
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->album_groups[(int) $row['album_id']][(int) $row['group_id']] = $row;
			}
			$this->db->sql_freeresult($result);
		}

		return isset($this->album_groups[$album_id]) ? $this->album_groups[$album_id] : [];
	}

	/**
	 * Vide les caches internes (après modification des droits).
	 */
	public function reset()
	{
		$this->album_groups = null;
		$this->memberships = null;
		$this->view_cache = [];
		$this->friend_of = [];
	}

	protected function load_user_groups()
	{
		if ($this->user_groups === null)
		{
			$this->user_groups = [];
			$sql = 'SELECT group_id
				FROM ' . USER_GROUP_TABLE . '
				WHERE user_id = ' . $this->user_id() . '
					AND user_pending = 0';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->user_groups[] = (int) $row['group_id'];
			}
			$this->db->sql_freeresult($result);
		}

		return $this->user_groups;
	}

	protected function load_memberships()
	{
		if ($this->memberships === null)
		{
			$this->memberships = [];
			if ($this->is_registered())
			{
				$sql = 'SELECT owner_id, album_id, member_role
					FROM ' . $this->members_table . '
					WHERE user_id = ' . $this->user_id();
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					$this->memberships[(int) $row['owner_id']][(int) $row['album_id']] = (int) $row['member_role'];
				}
				$this->db->sql_freeresult($result);
			}
		}

		return $this->memberships;
	}
}
