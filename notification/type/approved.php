<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\notification\type;

/**
 * Ma photo a été validée par un modérateur.
 */
class approved extends base_type
{
	public static $notification_option = [
		'lang'	=> 'NOTIFICATION_TYPE_PORTFOLIO_APPROVED',
		'group'	=> 'NOTIFICATION_GROUP_MISCELLANEOUS',
	];

	public function get_type()
	{
		return 'verturin.portfolio.notification.type.approved';
	}

	public static function get_item_id($data)
	{
		return (int) $data['photo_id'];
	}

	public static function get_item_parent_id($data)
	{
		return 0;
	}

	public function find_users_for_notification($data, $options = [])
	{
		$options = array_merge(['ignore_users' => []], $options);

		return $this->check_user_notification_options([(int) $data['user_id']], $options);
	}

	public function get_title()
	{
		return $this->language->lang('PORTFOLIO_NOTIFY_APPROVED');
	}

	public function get_url()
	{
		return $this->photo_url();
	}

	public function get_email_template()
	{
		return '@verturin_portfolio/portfolio_approved';
	}

	public function get_email_template_variables()
	{
		return [
			'PHOTO_TITLE'	=> htmlspecialchars_decode(censor_text($this->get_data('photo_title')), ENT_COMPAT),
			'U_PHOTO'		=> $this->photo_url(true),
		];
	}

	public function create_insert_array($data, $pre_create_data = [])
	{
		$this->set_data('actor_id', (int) $data['actor_id']);
		$this->set_data('photo_id', (int) $data['photo_id']);
		$this->set_data('photo_title', $data['photo_title']);

		parent::create_insert_array($data, $pre_create_data);
	}
}
