<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\notification\type;

/**
 * Un membre a noté une de mes photos (v1.10.0).
 */
class rating extends base_type
{
	public static $notification_option = [
		'lang'	=> 'NOTIFICATION_TYPE_PORTFOLIO_RATING',
		'group'	=> 'NOTIFICATION_GROUP_MISCELLANEOUS',
	];

	public function get_type()
	{
		return 'verturin.portfolio.notification.type.rating';
	}

	public function is_available()
	{
		return $this->activity_available('portfolio_notify_rating', false);
	}

	public static function get_item_id($data)
	{
		return (int) $data['activity_id'];
	}

	public static function get_item_parent_id($data)
	{
		return (int) $data['photo_id'];
	}

	public function find_users_for_notification($data, $options = [])
	{
		$options = array_merge(['ignore_users' => []], $options);

		return $this->check_user_notification_options([(int) $data['user_id']], $options);
	}

	public function get_title()
	{
		$stars = max(1, min(5, (int) $this->get_data('value')));

		return $this->language->lang('PORTFOLIO_NOTIFY_RATING', $this->actor_name(), str_repeat('★', $stars) . str_repeat('☆', 5 - $stars));
	}

	public function get_url()
	{
		return $this->photo_url();
	}

	public function get_email_template()
	{
		return false;
	}

	public function get_email_template_variables()
	{
		return [];
	}

	public function create_insert_array($data, $pre_create_data = [])
	{
		$this->set_data('actor_id', (int) $data['actor_id']);
		$this->set_data('photo_id', (int) $data['photo_id']);
		$this->set_data('photo_title', $data['photo_title']);
		$this->set_data('value', (int) $data['value']);

		parent::create_insert_array($data, $pre_create_data);
	}
}
