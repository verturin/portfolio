<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\notification\type;

use verturin\portfolio\service\photo_manager;

/**
 * Un membre a réagi (👍 ❤️ 😀…) à une de mes photos (v1.10.0).
 * Le code de la réaction est enregistré, pas l'émoji : les anciennes bases MySQL en utf8 refusent les émojis.
 */
class reaction extends base_type
{
	public static $notification_option = [
		'lang'	=> 'NOTIFICATION_TYPE_PORTFOLIO_REACTION',
		'group'	=> 'NOTIFICATION_GROUP_MISCELLANEOUS',
	];

	public function get_type()
	{
		return 'verturin.portfolio.notification.type.reaction';
	}

	public function is_available()
	{
		return $this->activity_available('portfolio_notify_reaction', false);
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
		$code = (string) $this->get_data('value');
		$emoji = isset(photo_manager::REACTIONS[$code]) ? photo_manager::REACTIONS[$code] : '';

		return $this->language->lang('PORTFOLIO_NOTIFY_REACTION', $this->actor_name(), $emoji);
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
		$this->set_data('value', (string) $data['value']);

		parent::create_insert_array($data, $pre_create_data);
	}
}
