<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Accueil du portfolio, liste des membres, portfolio d'un membre.
 */
class main extends base
{
	public function index()
	{
		$this->init();

		$viewable = $this->access->viewable_album_ids();
		$limit = max(4, (int) $this->config['portfolio_index_recent']);

		// Albums publics (racine)
		$public = array_filter($this->albums->get_public_roots(), function ($album) {
			return $this->access->can_view_album($album);
		});

		// Albums de membres mis à jour récemment
		$member_albums = array_filter($this->albums->get_all(), function ($album) {
			return (int) $album['owner_id'] && !(int) $album['parent_id'] && $album['album_photos'] > 0 && $this->access->can_view_album($album);
		});
		uasort($member_albums, function ($a, $b) {
			return (int) $b['album_last_time'] - (int) $a['album_last_time'];
		});
		$member_albums = array_slice($member_albums, 0, 8, true);

		$recent = $this->photos->list_in_albums($viewable, 'date', $limit);
		$top = $this->photos->list_in_albums($viewable, 'rating', min(8, $limit));
		$contributors = $this->photos->top_contributors($viewable, 6);
		$this->presenter->load_users(array_keys($contributors));

		$top_users = [];
		foreach ($contributors as $user_id => $total)
		{
			$top_users[] = [
				'name'		=> $this->presenter->username($user_id),
				'avatar'	=> $this->presenter->avatar($user_id),
				'photos'	=> $total,
				'url'		=> $this->route('user', ['user_id' => $user_id]),
			];
		}

		$stats = $this->photos->stats($viewable);

		// Mes albums et albums partagés avec moi, en tête de l'accueil (v1.6.0)
		$mine = $shared = [];
		if ($this->access->is_registered())
		{
			$me = $this->access->user_id();
			$mine = $this->albums->get_user_roots($me);
			$shared = $this->shared_with($me);
		}

		return $this->render('portfolio_index.html', $this->language->lang('PORTFOLIO'), [
			'PF_MY_ALBUMS'		=> $this->presenter->albums($mine),
			'PF_SHARED'			=> $this->presenter->albums($shared),
			'PF_PUBLIC_ALBUMS'	=> $this->presenter->albums($public),
			'PF_MEMBER_ALBUMS'	=> $this->presenter->albums($member_albums),
			'PF_RECENT'			=> $this->presenter->photos($recent),
			'PF_TOP'			=> $this->presenter->photos($top),
			'PF_TOP_USERS'		=> $top_users,
			'PF_STATS'			=> [
				'albums'	=> count($viewable),
				'photos'	=> $stats['photos'],
				'ratings'	=> $stats['ratings'],
				'comments'	=> $stats['comments'],
			],
		]);
	}

	/**
	 * Albums d'autres membres partagés avec ce membre (co-gestion ou simple accès).
	 */
	protected function shared_with($user_id)
	{
		$shared = [];
		foreach ($this->albums->get_shared_with($user_id) as $row)
		{
			if ((int) $row['album_id'])
			{
				$album = $this->albums->get($row['album_id']);
				if ($album)
				{
					$shared[(int) $album['album_id']] = $album;
				}
			}
		}

		return array_filter($shared, function ($album) {
			return $this->access->can_view_album($album);
		});
	}

	public function members()
	{
		$this->init();

		$owners = [];
		foreach ($this->albums->get_all() as $album)
		{
			$owner_id = (int) $album['owner_id'];
			if (!$owner_id || !$this->access->can_view_album($album))
			{
				continue;
			}
			if (!isset($owners[$owner_id]))
			{
				$owners[$owner_id] = ['albums' => 0, 'photos' => 0, 'last' => 0];
			}
			$owners[$owner_id]['albums']++;
			$owners[$owner_id]['photos'] += (int) $album['album_photos'];
			$owners[$owner_id]['last'] = max($owners[$owner_id]['last'], (int) $album['album_last_time']);
		}

		uasort($owners, function ($a, $b) {
			return $b['last'] - $a['last'];
		});

		$per_page = 30;
		$start = $this->page_start($per_page);
		$total = count($owners);
		$slice = array_slice($owners, $start, $per_page, true);
		$this->presenter->load_users(array_keys($slice));

		$list = [];
		foreach ($slice as $user_id => $info)
		{
			$list[] = [
				'name'		=> $this->presenter->username($user_id),
				'avatar'	=> $this->presenter->avatar($user_id),
				'albums'	=> $info['albums'],
				'photos'	=> $info['photos'],
				'last'		=> $info['last'] ? $this->user->format_date($info['last']) : '',
				'url'		=> $this->route('user', ['user_id' => $user_id]),
			];
		}

		return $this->render('portfolio_members.html', $this->language->lang('PORTFOLIO_MEMBERS'), [
			'PF_MEMBERS'		=> $list,
			'PF_MEMBERS_TOTAL'	=> $total,
			'PF_PAGINATION'		=> $this->presenter->pagination('members', [], $total, $per_page, $start),
		]);
	}

	public function mine()
	{
		$this->init();
		$this->require_login();

		return new RedirectResponse($this->helper->route('verturin_portfolio_user', ['user_id' => $this->access->user_id()], false));
	}

	public function user($user_id)
	{
		$this->init();
		$user_id = (int) $user_id;

		if (!$this->presenter->user_exists($user_id))
		{
			// Membre parti : ses albums ont été rattachés à un repreneur (v1.9.0)
			$album_id = $this->presenter->departed_album($user_id);
			if ($album_id && $this->albums->get($album_id))
			{
				return new RedirectResponse($this->helper->route('verturin_portfolio_album', ['album_id' => $album_id], false), 301);
			}
			$this->not_found('PORTFOLIO_USER_NOT_FOUND');
		}
		$username = $this->presenter->username($user_id, 'username');

		$is_self = $this->access->is_registered() && $user_id === $this->access->user_id();

		$roots = array_filter($this->albums->get_user_roots($user_id), function ($album) {
			return $this->access->can_view_album($album);
		});

		// Albums d'autres membres que je co-gère
		$shared = $is_self ? $this->shared_with($user_id) : [];

		$viewable = [];
		foreach ($this->albums->get_all() as $album)
		{
			if ((int) $album['owner_id'] === $user_id && $this->access->can_view_album($album))
			{
				$viewable[] = (int) $album['album_id'];
			}
		}
		$recent = $this->photos->list_in_albums($viewable, 'date', 12);

		$max_albums = (int) $this->config['portfolio_user_max_albums'];
		$can_create = $is_self && $this->access->can_create_root_album()
			&& (!$max_albums || $this->access->acl('u_portfolio_unlimited') || $this->albums->count_user_albums($user_id) < $max_albums);

		return $this->render('portfolio_user.html', $this->language->lang('PORTFOLIO_OF', $username), [
			'PF_OWNER_NAME'		=> $this->presenter->username($user_id),
			'PF_OWNER_USERNAME'	=> $username,
			'PF_OWNER_AVATAR'	=> $this->presenter->avatar($user_id),
			'PF_IS_SELF'		=> $is_self,
			'PF_ALBUMS'			=> $this->presenter->albums($roots),
			'PF_SHARED'			=> $this->presenter->albums($shared),
			'PF_RECENT'			=> $this->presenter->photos($recent),
			'PF_PHOTO_COUNT'	=> $this->photos->count_user_photos($user_id),
			'U_PF_NEW_ALBUM'	=> $can_create ? $this->route('album_create') : '',
			'U_PF_EXPORT_ALL'	=> ($is_self && $roots && ($this->access->acl('u_portfolio_export') || $this->access->is_admin())) ? $this->route('export') : '',
		]);
	}
}
