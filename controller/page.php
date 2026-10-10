<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

/**
 * Page Portfolio ouverte depuis un forum-lien (v1.8.0) : plus récentes, mieux notées, derniers commentaires,
 * au hasard, ou un album (par ex. un album « Présentations » protégé où chacun dépose sa photo).
 */
class page extends base
{
	/** @var \verturin\portfolio\service\page_manager */
	protected $pages;

	/** @var \verturin\portfolio\service\forum_link */
	protected $forum_link;

	public function set_pages(\verturin\portfolio\service\page_manager $pages, \verturin\portfolio\service\forum_link $forum_link)
	{
		$this->pages = $pages;
		$this->forum_link = $forum_link;
	}

	public function view($page_id)
	{
		$this->init();
		$page = $this->pages->get($page_id);
		if (!$page)
		{
			$this->not_found('PORTFOLIO_PAGE_NOT_FOUND');
		}

		// Droits : ceux du forum où la page est publiée (permission « Lire » du forum, par groupe)
		if (!$this->forum_link->can_read((int) $page['forum_id']))
		{
			if (!$this->access->is_registered())
			{
				login_box('', $this->language->lang('PORTFOLIO_PAGE_DENIED'));
			}
			$this->deny('PORTFOLIO_PAGE_DENIED');
		}

		$limit = min(100, max(1, (int) $page['page_limit']));
		$viewable = $this->access->viewable_album_ids();
		$vars = [
			'PF_PAGE'		=> [
				'title'		=> $page['page_title'],
				'type'		=> $page['page_type'],
				'desc'		=> $this->presenter->text($page['page_desc'], $page['page_desc_uid'], $page['page_desc_bitfield'], $page['page_desc_options']),
			],
			'PF_PHOTOS'		=> [],
			'PF_ALBUMS'		=> [],
			'PF_COMMENTS'	=> [],
		];
		$album = null;

		switch ($page['page_type'])
		{
			case 'top':
				$vars['PF_PHOTOS'] = $this->presenter->photos($this->photos->list_in_albums($viewable, 'rating', $limit));
			break;

			case 'random':
				$vars['PF_PHOTOS'] = $this->presenter->photos($this->photos->list_random($viewable, $limit));
			break;

			case 'comments':
				$rows = $this->photos->latest_comments($viewable, $limit);
				$this->presenter->load_users(array_column($rows, 'user_id'));
				foreach ($rows as $row)
				{
					$vars['PF_COMMENTS'][] = [
						'user'		=> $this->presenter->username($row['user_id']),
						'avatar'	=> $this->presenter->avatar($row['user_id']),
						'time'		=> $this->user->format_date((int) $row['comment_time']),
						'text'		=> $this->presenter->text($row['comment_text'], $row['comment_uid'], $row['comment_bitfield'], $row['comment_options']),
						'photo'		=> $this->presenter->photo($row['photo']),
						'url'		=> $this->route('photo', ['photo_id' => (int) $row['photo_id']]) . '#c' . (int) $row['comment_id'],
					];
				}
			break;

			case 'albums':
				// Albums de tous les membres (v1.8.4) : albums principaux visibles, les plus récemment mis à jour d'abord
				$list = array_filter($this->albums->get_all(), function ($a) {
					return (int) $a['owner_id'] && !(int) $a['parent_id'] && $this->access->can_view_album($a);
				});
				uasort($list, function ($a, $b) {
					return ((int) $b['album_last_time'] - (int) $a['album_last_time']) ?: strcasecmp($a['album_name'], $b['album_name']);
				});
				$start = $this->page_start($limit);
				$vars['PF_ALBUMS'] = $this->presenter->albums(array_slice($list, $start, $limit, true));
				$vars['PF_PAGINATION'] = $this->presenter->pagination('page', ['page_id' => (int) $page['page_id']], count($list), $limit, $start);
			break;

			case 'album':
				$album = $this->albums->get((int) $page['album_id']);
				if (!$album)
				{
					$this->not_found('PORTFOLIO_ALBUM_NOT_FOUND');
				}
				if (!$this->access->can_view_album($album))
				{
					$this->denied($album, null);
				}
				$per_page = max(6, (int) $this->config['portfolio_per_page']);
				$start = $this->page_start($per_page);
				list($rows, $total) = $this->photos->list_album((int) $album['album_id'], 'date_desc', $start, $per_page);
				$vars['PF_PHOTOS'] = $this->presenter->photos($rows);
				$vars['PF_PAGINATION'] = $this->presenter->pagination('page', ['page_id' => (int) $page['page_id']], $total, $per_page, $start);
				$vars['PF_PAGE_ALBUM'] = $this->presenter->album($album);
				$vars['PF_PROTECTED'] = $this->access->is_protected($album);
				$vars['U_PF_UPLOAD'] = $this->access->can_upload($album) ? $this->route('upload', ['album_id' => (int) $album['album_id']]) : '';
			break;

			default:
				$vars['PF_PHOTOS'] = $this->presenter->photos($this->photos->list_in_albums($viewable, 'date', $limit));
		}

		return $this->render('portfolio_page.html', $page['page_title'], $vars);
	}
}
