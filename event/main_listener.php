<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	/** @var \verturin\portfolio\service\settings Réglages du Portfolio */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $photos_table;

	public function __construct(\verturin\portfolio\service\settings $config, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\language\language $language, \phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, $photos_table)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->template = $template;
		$this->language = $language;
		$this->auth = $auth;
		$this->db = $db;
		$this->photos_table = $photos_table;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'						=> 'load_language',
			'core.permissions'						=> 'add_permissions',
			'core.page_header'						=> 'add_navigation',
			'core.viewonline_overwrite_location'	=> 'viewonline',
			'core.memberlist_view_profile'			=> 'profile',
			'core.viewtopic_modify_post_row'		=> 'miniprofile',
			'core.delete_user_before'				=> 'user_deleted',
		];
	}

	public function load_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'verturin/portfolio',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function add_permissions($event)
	{
		$categories = $event['categories'];
		$categories['portfolio'] = 'ACL_CAT_PORTFOLIO';
		$event['categories'] = $categories;

		$permissions = $event['permissions'];
		foreach ([
			'u_portfolio_view', 'u_portfolio_create_album', 'u_portfolio_upload', 'u_portfolio_noapprove',
			'u_portfolio_rate', 'u_portfolio_comment', 'u_portfolio_share', 'u_portfolio_restore',
			'u_portfolio_download', 'u_portfolio_unlimited', 'u_portfolio_export', 'u_portfolio_react', 'u_portfolio_profile_link', 'u_portfolio_vis_groups',
			'm_portfolio_approve', 'm_portfolio_edit', 'm_portfolio_delete', 'm_portfolio_comments',
			'a_portfolio_manage', 'a_portfolio_trash',
		] as $permission)
		{
			$permissions[$permission] = ['lang' => 'ACL_' . strtoupper($permission), 'cat' => 'portfolio'];
		}
		$event['permissions'] = $permissions;
	}

	public function add_navigation()
	{
		$this->template->assign_vars([
			'S_PORTFOLIO_NAV'	=> $this->config['portfolio_navbar'] && $this->auth->acl_get('u_portfolio_view'),
			'U_PORTFOLIO'		=> $this->helper->route('verturin_portfolio_index'),
		]);
	}

	public function viewonline($event)
	{
		if ($event['on_page'][1] === 'app' && strpos($event['row']['session_page'], '/portfolio') !== false)
		{
			$event['location'] = $this->language->lang('PORTFOLIO_VIEWONLINE');
			$event['location_url'] = $this->helper->route('verturin_portfolio_index');
		}
	}

	/** @var \verturin\portfolio\service\access|null */
	protected $access;

	/** @var \verturin\portfolio\service\album_manager|null */
	protected $albums;

	/** @var array|null [owner_id => photos visibles] */
	protected $visible_counts;

	public function set_portfolio(\verturin\portfolio\service\access $access, \verturin\portfolio\service\album_manager $albums)
	{
		$this->access = $access;
		$this->albums = $albums;
	}

	/**
	 * Nombre de photos que le visiteur peut voir, par propriétaire (albums visibles uniquement).
	 *
	 * @return array [owner_id => ['albums' => n, 'photos' => n]]
	 */
	protected function visible_counts()
	{
		if ($this->visible_counts === null)
		{
			$this->visible_counts = [];
			foreach ($this->albums->get_all() as $album)
			{
				$owner = (int) $album['owner_id'];
				if (!$owner || !$this->access->can_view_album($album))
				{
					continue;
				}
				if (!isset($this->visible_counts[$owner]))
				{
					$this->visible_counts[$owner] = ['albums' => 0, 'photos' => 0];
				}
				$this->visible_counts[$owner]['albums']++;
				$this->visible_counts[$owner]['photos'] += (int) $album['album_photos'];
			}
		}

		return $this->visible_counts;
	}

	/** @var \verturin\portfolio\service\photo_manager|null */
	protected $photos;

	/** @var \verturin\portfolio\service\presenter|null */
	protected $presenter;

	public function set_display(\verturin\portfolio\service\photo_manager $photos, \verturin\portfolio\service\presenter $presenter)
	{
		$this->photos = $photos;
		$this->presenter = $presenter;
	}

	protected function link_allowed($setting)
	{
		return $this->access
			&& $this->config[$setting]
			&& $this->auth->acl_get('u_portfolio_view')
			&& $this->auth->acl_get('u_portfolio_profile_link');
	}

	/**
	 * Lien vers la galerie dans le profil (v1.5.0 : réglage ACP + permission par groupe, albums visibles uniquement).
	 */
	public function profile($event)
	{
		if (!$this->link_allowed('portfolio_profile_link'))
		{
			return;
		}

		$user_id = (int) $event['member']['user_id'];
		$counts = $this->visible_counts();
		if (empty($counts[$user_id]))
		{
			return;
		}

		$this->template->assign_vars([
			'PORTFOLIO_PROFILE_PHOTOS'	=> $counts[$user_id]['photos'],
			'PORTFOLIO_PROFILE_ALBUMS'	=> $counts[$user_id]['albums'],
			'U_PORTFOLIO_PROFILE'		=> $this->helper->route('verturin_portfolio_user', ['user_id' => $user_id]),
		]);

		// Dernières photos du membre en bas du profil (v1.6.0)
		$limit = (int) $this->config['portfolio_profile_photos'];
		if ($limit > 0 && $this->photos && $this->presenter)
		{
			$album_ids = [];
			foreach ($this->albums->get_all() as $album)
			{
				if ((int) $album['owner_id'] === $user_id && $this->access->can_view_album($album))
				{
					$album_ids[] = (int) $album['album_id'];
				}
			}
			$rows = $this->photos->list_in_albums($album_ids, 'date', $limit);
			$this->template->assign_vars([
				'PORTFOLIO_PROFILE_RECENT'	=> $this->presenter->photos($rows),
				'PORTFOLIO_PROFILE_NAME'	=> $event['member']['username'],
			]);
		}
	}

	/**
	 * Lien vers la galerie dans le mini-profil des messages.
	 */
	public function miniprofile($event)
	{
		if (!$this->link_allowed('portfolio_miniprofile_link'))
		{
			return;
		}

		$poster_id = (int) $event['poster_id'];
		$counts = $this->visible_counts();
		if ($poster_id === ANONYMOUS || empty($counts[$poster_id]))
		{
			return;
		}

		$post_row = $event['post_row'];
		$post_row['PORTFOLIO_PHOTOS'] = $counts[$poster_id]['photos'];
		$post_row['U_PORTFOLIO'] = $this->helper->route('verturin_portfolio_user', ['user_id' => $poster_id]);
		$event['post_row'] = $post_row;
	}

	/** @var \verturin\portfolio\service\departed */
	protected $departed;

	public function set_departed(\verturin\portfolio\service\departed $departed)
	{
		$this->departed = $departed;
	}

	/**
	 * Un membre quitte le forum (ACP, élagage des inactifs, extension de nettoyage…) : ses albums sont
	 * rattachés au membre repreneur et ses photos restent signées de son nom (v1.9.0).
	 */
	public function user_deleted($event)
	{
		if (!$this->departed)
		{
			return;
		}

		$users = [];
		foreach ((array) $event['user_rows'] as $user_id => $row)
		{
			$users[(int) $user_id] = isset($row['username']) ? (string) $row['username'] : '';
		}
		if (!$users)
		{
			return;
		}

		$this->language->add_lang('common', 'verturin/portfolio');
		$language = $this->language;
		$this->departed->on_delete(
			$users,
			(string) $event['mode'],
			function ($username) use ($language) {
				return $language->lang('PORTFOLIO_DEPARTED_ALBUM', $username);
			},
			function ($username) use ($language) {
				return $language->lang('PORTFOLIO_DEPARTED_REASON', $username);
			}
		);
	}
}
