<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\notification\type;

/**
 * Alerte administrateur : un membre a supprimé beaucoup de photos en peu de temps.
 */
class mass_delete extends base_type
{
	public static $notification_option = [
		'lang'	=> 'NOTIFICATION_TYPE_PORTFOLIO_MASS_DELETE',
		'group'	=> 'NOTIFICATION_GROUP_ADMINISTRATION',
	];

	public function get_type()
	{
		return 'verturin.portfolio.notification.type.mass_delete';
	}

	public function is_available()
	{
		return (bool) $this->auth->acl_get('a_portfolio_manage');
	}

	public static function get_item_id($data)
	{
		return (int) $data['alert_id'];
	}

	public static function get_item_parent_id($data)
	{
		return (int) $data['user_id'];
	}

	public function find_users_for_notification($data, $options = [])
	{
		$options = array_merge(['ignore_users' => []], $options);

		$users = [];
		foreach ($this->auth->acl_get_list(false, ['a_portfolio_manage', 'a_portfolio_trash'], false) as $forum_permissions)
		{
			foreach ($forum_permissions as $user_ids)
			{
				$users = array_merge($users, $user_ids);
			}
		}

		return $this->check_user_notification_options(array_unique(array_map('intval', $users)), $options);
	}

	public function get_title()
	{
		return $this->language->lang(
			$this->get_data('blocked') ? 'PORTFOLIO_NOTIFY_MASS_DELETE_BLOCKED' : 'PORTFOLIO_NOTIFY_MASS_DELETE',
			$this->actor_name(),
			(int) $this->get_data('count'),
			(int) $this->get_data('minutes')
		);
	}

	public function get_reference()
	{
		return $this->language->lang('PORTFOLIO_NOTIFY_MASS_DELETE_REF');
	}

	public function get_url()
	{
		return $this->acp_url();
	}

	protected function acp_url()
	{
		return generate_board_url() . '/adm/index.php?i=-verturin-portfolio-acp-main_module&amp;mode=trash&amp;deleted_by=' . (int) $this->get_data('actor_id');
	}

	public function get_email_template()
	{
		return '@verturin_portfolio/portfolio_mass_delete';
	}

	public function get_email_template_variables()
	{
		return [
			'USERNAME_DELETER'	=> htmlspecialchars_decode($this->actor_name('username'), ENT_COMPAT),
			'PHOTO_COUNT'		=> (int) $this->get_data('count'),
			'MINUTES'			=> (int) $this->get_data('minutes'),
			'BLOCKED'			=> (bool) $this->get_data('blocked'),
			'U_ACP_TRASH'		=> str_replace('&amp;', '&', $this->acp_url()),
		];
	}

	public function create_insert_array($data, $pre_create_data = [])
	{
		$this->set_data('actor_id', (int) $data['user_id']);
		$this->set_data('count', (int) $data['count']);
		$this->set_data('minutes', (int) $data['minutes']);
		$this->set_data('blocked', (int) $data['blocked']);

		parent::create_insert_array($data, $pre_create_data);
	}
}
