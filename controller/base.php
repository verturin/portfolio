<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use phpbb\exception\http_exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use verturin\portfolio\service\access;
use verturin\portfolio\service\photo_manager;

/**
 * Socle commun des contrôleurs publics.
 */
abstract class base
{
	/** @var \verturin\portfolio\service\settings Réglages du Portfolio */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \verturin\portfolio\service\access */
	protected $access;

	/** @var \verturin\portfolio\service\album_manager */
	protected $albums;

	/** @var \verturin\portfolio\service\photo_manager */
	protected $photos;

	/** @var \verturin\portfolio\service\presenter */
	protected $presenter;

	/** @var \verturin\portfolio\service\image_processor */
	protected $image;

	public function __construct(
		\verturin\portfolio\service\settings $config,
		\phpbb\controller\helper $helper,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\verturin\portfolio\service\access $access,
		\verturin\portfolio\service\album_manager $albums,
		\verturin\portfolio\service\photo_manager $photos,
		\verturin\portfolio\service\presenter $presenter,
		\verturin\portfolio\service\image_processor $image
	)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
		$this->language = $language;
		$this->request = $request;
		$this->access = $access;
		$this->albums = $albums;
		$this->photos = $photos;
		$this->presenter = $presenter;
		$this->image = $image;
	}

	/**
	 * Charge la langue et vérifie le droit d'accès au portfolio.
	 */
	protected function init()
	{
		$this->language->add_lang('common', 'verturin/portfolio');
		$this->presenter->set_quickview($this->config['portfolio_quickview']);
		$this->presenter->set_show_visibility($this->access->is_admin() && $this->config['portfolio_vis_badges']);

		if (!$this->access->can_use())
		{
			if (!$this->access->is_registered())
			{
				login_box('', $this->language->lang('PORTFOLIO_LOGIN_REQUIRED'));
			}
			throw new http_exception(403, 'PORTFOLIO_NO_ACCESS');
		}
	}

	protected function require_login()
	{
		if (!$this->access->is_registered())
		{
			login_box('', $this->language->lang('PORTFOLIO_LOGIN_REQUIRED'));
		}
	}

	protected function deny($key = 'PORTFOLIO_NOT_AUTHORISED')
	{
		throw new http_exception(403, $key);
	}

	protected function not_found($key)
	{
		throw new http_exception(404, $key);
	}

	/**
	 * Album visible ou 404.
	 */
	protected function load_album($album_id)
	{
		$album = $this->albums->get($album_id);
		if (!$album)
		{
			$this->not_found('PORTFOLIO_ALBUM_NOT_FOUND');
		}
		if (!$this->access->can_view_album($album))
		{
			$this->denied($album, null);
		}

		return $album;
	}

	/**
	 * L'album ou la photo existe mais le visiteur n'a pas le droit de le voir :
	 * page claire « Vous n'êtes pas autorisé… » avec la raison et la marche à suivre
	 * (connexion, demande d'ajout aux amis, demande d'accès au propriétaire).
	 *
	 * @param array      $album
	 * @param array|null $photo Photo demandée (null = album)
	 */
	protected function denied(?array $album, ?array $photo, $for_file = false)
	{
		if ($for_file)
		{
			throw new http_exception(403, 'PORTFOLIO_DENIED_PHOTO');
		}

		$info = $album ? $this->access->deny_reason($album) : ['reason' => 'access', 'album' => null];

		if ($info['reason'] === 'login')
		{
			login_box('', $this->language->lang($photo ? 'PORTFOLIO_DENIED_LOGIN_PHOTO' : 'PORTFOLIO_DENIED_LOGIN_ALBUM'));
		}

		$owner_id = $info['album'] ? (int) $info['album']['owner_id'] : 0;
		$owner_name = '';
		$owner_full = '';
		if ($owner_id)
		{
			$this->presenter->load_users([$owner_id]);
			$owner_name = $this->presenter->username($owner_id, 'username');
			$owner_full = $this->presenter->username($owner_id, 'full');
		}

		$reason = $info['reason'];
		if (!$owner_id && in_array($reason, ['friends', 'groups_friends', 'private'], true))
		{
			// Album public de l'administration : pas de propriétaire à contacter
			$reason = $reason === 'private' ? 'reserved' : 'groups';
		}

		$board = generate_board_url();
		$can_pm = $owner_id && $this->config_core('allow_privmsg') && $this->access->acl('u_sendpm');

		$response = $this->render('portfolio_denied.html', $this->language->lang($photo ? 'PORTFOLIO_DENIED_PHOTO' : 'PORTFOLIO_DENIED_ALBUM'), [
			'PF_DENIED'			=> [
				'is_photo'		=> (bool) $photo,
				'reason'		=> $reason,
				'owner'			=> $owner_full,
				'owner_name'	=> $owner_name,
				'album_name'	=> $info['album'] ? $info['album']['album_name'] : '',
				'u_pm'			=> $can_pm ? append_sid($board . '/ucp.php', 'i=pm&amp;mode=compose&amp;u=' . $owner_id) : '',
				'u_profile'		=> $owner_id ? append_sid($board . '/memberlist.php', 'mode=viewprofile&amp;u=' . $owner_id) : '',
				'u_owner'		=> $owner_id ? $this->route('user', ['user_id' => $owner_id]) : '',
				'u_friends'		=> append_sid($board . '/ucp.php', 'i=zebra&amp;mode=friends'),
			],
		], null, 403);

		$response->send();
		garbage_collection();
		exit_handler();
	}

	/**
	 * Lecture d'une option de configuration du forum (et non de l'extension).
	 */
	protected function config_core($key)
	{
		global $config;

		return isset($config[$key]) ? $config[$key] : null;
	}

	/**
	 * Photo + album, visibilité selon le statut.
	 *
	 * @return array [photo, album|null]
	 */
	protected function load_photo($photo_id, $for_file = false)
	{
		$photo = $this->photos->get($photo_id);
		if (!$photo)
		{
			$this->not_found('PORTFOLIO_PHOTO_NOT_FOUND');
		}

		$album = $this->albums->get($photo['album_id']);
		if (!$this->access->can_view_photo($album, $photo))
		{
			// Album non visible => raison précise ; sinon photo en attente / en corbeille d'un autre membre
			$this->denied($album && !$this->access->can_view_album($album) ? $album : null, $photo, $for_file);
		}

		return [$photo, $album];
	}

	protected function route($name, array $params = [])
	{
		return $this->presenter->route($name, $params);
	}

	protected function page_start($per_page)
	{
		$page = max(1, $this->request->variable('page', 1));

		return ($page - 1) * (int) $per_page;
	}

	protected function json(array $data, $status = 200)
	{
		return new JsonResponse($data, $status);
	}

	/**
	 * Message de confirmation avec lien de retour et redirection automatique.
	 */
	protected function done($key, $url, array $args = [])
	{
		if ($this->request->is_ajax())
		{
			return $this->json(['success' => true, 'message' => $this->language->lang($key, ...$args), 'redirect' => $url]);
		}

		meta_refresh(3, $url);

		return $this->helper->message($this->language->lang($key, ...$args) . '<br><br>' . $this->language->lang('PORTFOLIO_RETURN_LINK', '<a href="' . $url . '">', '</a>'));
	}

	/**
	 * Pages internes : variables communes, fil d'Ariane du forum, rendu.
	 */
	protected function render($template, $title, array $vars = [], ?array $album = null, $status = 200)
	{
		$crumbs = $this->presenter->breadcrumbs($album);
		foreach ($crumbs as $crumb)
		{
			$this->template->assign_block_vars('navlinks', [
				'BREADCRUMB_NAME'	=> $crumb['name'],
				'U_BREADCRUMB'		=> $crumb['url'],
				'FORUM_NAME'		=> $crumb['name'],
				'U_VIEW_FORUM'		=> $crumb['url'],
			]);
		}

		$registered = $this->access->is_registered();
		$queue = $this->access->can_approve() ? $this->photos->count_by_status(photo_manager::STATUS_PENDING) : 0;

		$this->template->assign_vars(array_merge([
			'PF_CRUMBS'			=> $crumbs,
			'PF_TITLE'			=> $title,
			'PF_IS_REGISTERED'	=> $registered,
			'PF_CAN_CREATE'		=> $this->access->can_create_root_album(),
			'PF_CAN_ADMIN'		=> $this->access->is_admin(),
			'PF_QUEUE_COUNT'	=> $queue,
			'U_PF_INDEX'		=> $this->route('index'),
			'PF_GRID_SIZE'		=> min(400, max(100, (int) $this->config['portfolio_grid_size'] ?: 210)),
			'U_PF_MEMBERS'		=> $this->route('members'),
			'U_PF_MINE'			=> $registered ? $this->route('mine') : '',
			'U_PF_TRASH'		=> $registered ? $this->route('trash') : '',
			'U_PF_QUEUE'		=> $this->access->can_approve() ? $this->route('queue') : '',
			'U_PF_SHARING'		=> $registered && $this->access->acl('u_portfolio_share') ? $this->route('sharing') : '',
			'U_PF_CREATE'		=> $this->access->can_create_root_album() ? $this->route('album_create') : '',
			'U_PF_CREATE_PUBLIC' => $this->access->can_create_public_album() ? $this->route('album_create', ['public' => 1]) : '',
		], $vars));

		return $this->helper->render('@verturin_portfolio/' . $template, $title, $status);
	}

	/**
	 * Navigation entre albums : parent, précédent, suivant (albums visibles uniquement)
	 * et arborescence complète de l'album racine.
	 */
	protected function album_navigation(array $album)
	{
		$album_id = (int) $album['album_id'];
		$siblings = array_values(array_filter($this->albums->get_siblings($album), function ($row) {
			return $this->access->can_view_album($row);
		}));
		$ids = array_map('intval', array_column($siblings, 'album_id'));
		$pos = array_search($album_id, $ids, true);

		$link = function ($row) {
			return $row ? ['name' => $row['album_name'], 'url' => $this->route('album', ['album_id' => (int) $row['album_id']])] : null;
		};

		$parent = (int) $album['parent_id'] ? $this->albums->get($album['parent_id']) : null;
		if (!$parent && (int) $album['owner_id'])
		{
			$this->presenter->load_users([(int) $album['owner_id']]);
			$up = ['name' => $this->language->lang('PORTFOLIO_OF', $this->presenter->username($album['owner_id'], 'username')), 'url' => $this->route('user', ['user_id' => (int) $album['owner_id']])];
		}
		else
		{
			$up = $parent ? $link($parent) : ['name' => $this->language->lang('PORTFOLIO'), 'url' => $this->route('index')];
		}

		// Arborescence de l'album racine (visible seulement s'il y a des sous-albums)
		$chain = $this->albums->get_chain($album_id);
		$root = $chain ? $chain[0] : $album;
		$chain_ids = array_map('intval', array_column($chain, 'album_id'));
		$tree = [];
		$nodes = [(int) $root['album_id'] => ['album' => $root, 'depth' => 0]] + $this->albums->flat_tree(function ($row) {
			return $this->access->can_view_album($row);
		}, 0, (int) $root['album_id'], 1);
		if (count($nodes) > 1)
		{
			foreach ($nodes as $id => $node)
			{
				$tree[] = [
					'name'		=> $node['album']['album_name'],
					'url'		=> $this->route('album', ['album_id' => $id]),
					'depth'		=> $node['depth'],
					'photos'	=> (int) $node['album']['album_photos'],
					'current'	=> $id === $album_id,
					'open'		=> in_array($id, $chain_ids, true),
				];
			}
		}

		return [
			'up'		=> $up,
			'prev'		=> ($pos !== false && $pos > 0) ? $link($siblings[$pos - 1]) : null,
			'next'		=> ($pos !== false && isset($siblings[$pos + 1])) ? $link($siblings[$pos + 1]) : null,
			'position'	=> $pos !== false ? $pos + 1 : 0,
			'count'		=> count($siblings),
			'tree'		=> $tree,
		];
	}

	/**
	 * Options de liste déroulante pour les albums où l'utilisateur peut envoyer des photos.
	 */
	/**
	 * @param int      $exclude  Album à ne pas proposer
	 * @param int|array|null $owner_id Pour un déplacement : seulement les albums de ce(s) propriétaire(s) (v1.9.1)
	 */
	protected function upload_targets($exclude = 0, $owner_id = null)
	{
		$options = [];
		$owners = $owner_id === null ? null : array_unique(array_map('intval', (array) $owner_id));
		$tree = $this->albums->flat_tree(function ($album) use ($owners) {
			return ($owners === null || in_array((int) $album['owner_id'], $owners, true)) && $this->access->can_upload($album);
		}, 0);
		$this->presenter->load_users(array_map(function ($node) {
			return (int) $node['album']['owner_id'];
		}, $tree));
		foreach ($tree as $id => $node)
		{
			if ($id === (int) $exclude)
			{
				continue;
			}
			$options[] = [
				'id'		=> $id,
				'name'		=> str_repeat('— ', $node['depth']) . $node['album']['album_name'],
				'owner'		=> ($owners === null || count($owners) > 1) && (int) $node['album']['owner_id'] ? $this->presenter->username($node['album']['owner_id'], 'username') : '',
			];
		}

		return $options;
	}

	/**
	 * Une photo ne se déplace que dans le même portfolio (albums et sous-albums du propriétaire de l'album),
	 * ou vers les albums de la personne qui l'a postée. Jamais vers l'album d'un autre membre (v1.9.1).
	 */
	protected function same_owner(array $from, array $to, ?array $photo = null)
	{
		return (int) $from['owner_id'] === (int) $to['owner_id']
			|| ($photo && (int) $photo['user_id'] > ANONYMOUS && (int) $to['owner_id'] === (int) $photo['user_id']);
	}

	/**
	 * Propriétaires dont les albums sont proposés pour déplacer cette photo.
	 */
	protected function move_owners(array $album, ?array $photo = null)
	{
		$owners = [(int) $album['owner_id']];
		if ($photo && (int) $photo['user_id'] > ANONYMOUS)
		{
			$owners[] = (int) $photo['user_id'];
		}

		return $owners;
	}

	protected function visibility_options($selected, $personal = true, array $allowed = [])
	{
		$choices = [
			access::VIS_ALL			=> 'PORTFOLIO_VIS_ALL',
			access::VIS_REGISTERED	=> 'PORTFOLIO_VIS_REGISTERED',
			access::VIS_GROUPS		=> 'PORTFOLIO_VIS_GROUPS',
			access::VIS_FRIENDS		=> 'PORTFOLIO_VIS_FRIENDS',
			access::VIS_PRIVATE		=> 'PORTFOLIO_VIS_PRIVATE',
		];
		if (!$personal)
		{
			// Album public : pas de propriétaire, donc pas de liste d'amis
			unset($choices[access::VIS_FRIENDS]);
		}

		$out = [];
		foreach ($choices as $value => $key)
		{
			if ($allowed && !in_array($value, $allowed, true))
			{
				continue;
			}
			$out[] = [
				'value'		=> $value,
				'label'		=> $this->language->lang($key),
				'explain'	=> $this->language->lang($key . '_EXPLAIN'),
				'icon'		=> access::VIS_ICONS[$value],
				'selected'	=> (int) $selected === $value,
			];
		}

		return $out;
	}
}
