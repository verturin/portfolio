<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use verturin\portfolio\service\access;
use verturin\portfolio\service\album_manager;
use verturin\portfolio\service\presenter;
use verturin\portfolio\service\settings;

/**
 * Panneau de l'utilisateur › Portfolio › Filigrane de mes photos (v1.10.2).
 * Le membre autorisé à retirer le filigrane y voit ses albums concernés et peut renoncer à l'option :
 * le filigrane revient sur toutes ses photos et il sort de la sélection de l'administration.
 */
class ucp_controller
{
	protected $settings;
	protected $access;
	protected $albums;
	protected $presenter;
	protected $language;
	protected $log;
	protected $request;
	protected $template;
	protected $user;
	protected $u_action;

	public function __construct(settings $settings, access $access, album_manager $albums, presenter $presenter,
		\phpbb\language\language $language, \phpbb\log\log_interface $log, \phpbb\request\request $request,
		\phpbb\template\template $template, \phpbb\user $user)
	{
		$this->settings = $settings;
		$this->access = $access;
		$this->albums = $albums;
		$this->presenter = $presenter;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
	}

	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	public function watermark()
	{
		$user_id = (int) $this->user->data['user_id'];
		$form_key = 'verturin_portfolio_ucp_watermark';
		add_form_key($form_key);
		$allowed = $this->access->wm_optout_allowed($user_id);

		if ($allowed && $this->request->is_set_post('renounce'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . $this->back_link(), E_USER_WARNING);
			}
			if (!$this->request->variable('confirm_renounce', 0))
			{
				trigger_error($this->language->lang('UCP_PORTFOLIO_WM_CONFIRM_MISSING') . $this->back_link(), E_USER_WARNING);
			}

			$this->renounce($user_id);
			trigger_error($this->language->lang('UCP_PORTFOLIO_WM_RENOUNCED') . $this->back_link());
		}

		$albums = [];
		if ($allowed)
		{
			foreach ($this->albums->get_all() as $id => $row)
			{
				if ((int) $row['owner_id'] === $user_id && !empty($row['album_owner_no_wm']))
				{
					$albums[] = [
						'name'		=> $row['album_name'],
						'u_view'	=> $this->presenter->route_abs('album', ['album_id' => (int) $id]),
						'u_edit'	=> $this->presenter->route_abs('album_edit', ['album_id' => (int) $id]),
					];
				}
			}
		}

		$this->template->assign_vars([
			'PF_WM_ALLOWED'		=> $allowed,
			'PF_WM_ENABLED'		=> (bool) $this->settings['portfolio_wm_enabled'],
			'PF_WM_ALBUMS'		=> $albums,
			'U_PF_MINE'			=> $this->presenter->route_abs('mine', []),
			'U_ACTION'			=> $this->u_action,
		]);
	}

	/**
	 * Le filigrane revient sur toutes les photos du membre ; il sort de la liste des membres autorisés
	 * (et, s'il était autorisé par un groupe, il est mis de côté jusqu'à ce que l'administration le rajoute).
	 */
	protected function renounce($user_id)
	{
		$users = array_values(array_filter(array_map('intval', explode(',', (string) $this->settings['portfolio_wm_optout_users']))));
		$users = array_values(array_diff($users, [$user_id]));
		$this->settings->set('portfolio_wm_optout_users', implode(',', $users));

		$excluded = array_values(array_filter(array_map('intval', explode(',', (string) $this->settings['portfolio_wm_optout_excluded']))));
		if (!in_array($user_id, $excluded, true))
		{
			$excluded[] = $user_id;
		}
		$this->settings->set('portfolio_wm_optout_excluded', implode(',', $excluded));

		$ids = [];
		foreach ($this->albums->get_all() as $id => $row)
		{
			if ((int) $row['owner_id'] === $user_id && !empty($row['album_owner_no_wm']))
			{
				$ids[] = (int) $id;
			}
		}
		$this->albums->update_many($ids, ['album_owner_no_wm' => 0]);
		$this->settings->increment('portfolio_wm_revision', 1);
		$this->access->reset_wm_optout_cache();

		$this->log->add('admin', $user_id, $this->user->ip, 'LOG_PORTFOLIO_WM_OPTOUT_LEFT', false, [$this->user->data['username']]);
	}

	protected function back_link()
	{
		return '<br><br>' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
	}
}
