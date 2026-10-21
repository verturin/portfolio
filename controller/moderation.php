<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use verturin\portfolio\service\photo_manager;

/**
 * Corbeille du membre (restauration) et file de validation des modérateurs.
 */
class moderation extends base
{
	/**
	 * Photos supprimées que je peux restaurer (les miennes et celles des albums que je gère).
	 */
	public function trash()
	{
		$this->init();
		$this->require_login();

		$managed = [];
		foreach ($this->albums->get_all() as $album)
		{
			if ($this->access->role($album) >= \verturin\portfolio\service\access::ROLE_MANAGER)
			{
				$managed[] = (int) $album['album_id'];
			}
		}

		$where = '(user_id = ' . $this->access->user_id() . ($managed ? ' OR ' . $this->photos_db_in('album_id', $managed) : '') . ')';

		$per_page = max(6, (int) $this->config['portfolio_per_page']);
		$start = $this->page_start($per_page);
		list($rows, $total) = $this->photos->list_by_status(photo_manager::STATUS_TRASH, $start, $per_page, $where);
		$this->presenter->load_users(array_column($rows, 'deleted_by'));

		$items = [];
		foreach ($rows as $row)
		{
			$album = $this->albums->get($row['album_id']);
			$item = $this->presenter->photo($row);
			$item['album_name'] = $album ? $album['album_name'] : '';
			$item['album_missing'] = !$album;
			$item['deleted_by'] = $this->presenter->username($row['deleted_by']);
			$item['deleted_time'] = $this->user->format_date((int) $row['deleted_time']);
			$item['reason'] = $row['deleted_reason'];
			$item['u_restore'] = $this->access->can_restore_photo($row, $album) ? $this->route('photo_restore', ['photo_id' => (int) $row['photo_id']]) : '';
			// Album disparu : on ne restaure que dans les albums de l'auteur de la photo (v1.9.1)
			$item['targets'] = !$album && $item['u_restore'] ? $this->upload_targets(0, (int) $row['user_id']) : [];
			$items[] = $item;
		}

		add_form_key('verturin_portfolio_restore');

		return $this->render('portfolio_trash.html', $this->language->lang('PORTFOLIO_TRASH'), [
			'PF_ITEMS'		=> $items,
			'PF_TOTAL'		=> $total,
			'PF_CAN_RESTORE' => $this->access->acl('u_portfolio_restore') || $this->access->can_admin_trash(),
			'PF_PAGINATION'	=> $this->presenter->pagination('trash', [], $total, $per_page, $start),
		]);
	}

	/**
	 * File de validation des photos en attente.
	 */
	public function queue()
	{
		$this->init();
		$this->require_login();

		// Validation rapide depuis la page photo (lien signé)
		$approve_id = $this->request->variable('approve', 0);
		if ($approve_id)
		{
			$photo = $this->photos->get($approve_id);
			$album = $photo ? $this->albums->get($photo['album_id']) : null;
			if (!$photo || !$album || !check_link_hash($this->request->variable('hash', ''), 'pf_approve_' . $approve_id)
				|| !$this->access->can_approve_album($album))
			{
				$this->deny();
			}
			$this->photos->approve([$approve_id]);

			return $this->done('PORTFOLIO_PHOTOS_APPROVED', $this->route('photo', ['photo_id' => $approve_id]), [1]);
		}

		if (!$this->access->can_approve())
		{
			$this->deny();
		}

		if ($this->request->is_set_post('action'))
		{
			if (!check_form_key('verturin_portfolio_queue'))
			{
				$this->deny('FORM_INVALID');
			}

			$ids = array_filter(array_map('intval', $this->request->variable('photo_ids', [0])));
			$action = $this->request->variable('action', '');
			if ($ids && $action === 'approve')
			{
				$count = $this->photos->approve($ids);

				return $this->done('PORTFOLIO_PHOTOS_APPROVED', $this->route('queue'), [$count]);
			}
			if ($ids && $action === 'delete')
			{
				$count = $this->photos->trash($ids, $this->language->lang('PORTFOLIO_REASON_DISAPPROVED'));

				return $this->done('PORTFOLIO_PHOTOS_TRASHED', $this->route('queue'), [$count]);
			}

			return new RedirectResponse($this->helper->route('verturin_portfolio_queue', [], false));
		}

		$per_page = max(6, (int) $this->config['portfolio_per_page']);
		$start = $this->page_start($per_page);
		list($rows, $total) = $this->photos->list_by_status(photo_manager::STATUS_PENDING, $start, $per_page);

		$items = [];
		foreach ($this->presenter->photos($rows) as $item)
		{
			$album = $this->albums->get($item['album_id']);
			$item['album_name'] = $album ? $album['album_name'] : '';
			$item['album_url'] = $album ? $this->route('album', ['album_id' => (int) $album['album_id']]) : '';
			$items[] = $item;
		}

		add_form_key('verturin_portfolio_queue');

		return $this->render('portfolio_queue.html', $this->language->lang('PORTFOLIO_QUEUE'), [
			'PF_ITEMS'		=> $items,
			'PF_TOTAL'		=> $total,
			'PF_PAGINATION'	=> $this->presenter->pagination('queue', [], $total, $per_page, $start),
			'U_PF_ACTION'	=> $this->route('queue'),
		]);
	}

	protected function photos_db_in($column, array $ids)
	{
		return $column . ' IN (' . implode(',', array_map('intval', $ids)) . ')';
	}
}
