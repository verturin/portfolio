<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\notification\type;

/**
 * Nouveau commentaire sur une de mes photos.
 */
class comment extends base_type
{
	public static $notification_option = [
		'lang'	=> 'NOTIFICATION_TYPE_PORTFOLIO_COMMENT',
		'group'	=> 'NOTIFICATION_GROUP_MISCELLANEOUS',
	];

	public function get_type()
	{
		return 'verturin.portfolio.notification.type.comment';
	}

	public function is_available()
	{
		return $this->activity_available('portfolio_notify_comment', false);
	}

	public static function get_item_id($data)
	{
		return (int) $data['comment_id'];
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
		return $this->language->lang('PORTFOLIO_NOTIFY_COMMENT', $this->actor_name());
	}

	public function get_url()
	{
		return $this->photo_url(false, '#c' . (int) $this->get_data('comment_id'));
	}

	public function get_email_template()
	{
		return '@verturin_portfolio/portfolio_comment';
	}

	public function get_email_template_variables()
	{
		return [
			'AUTHOR_NAME'	=> htmlspecialchars_decode($this->actor_name('username'), ENT_COMPAT),
			'PHOTO_TITLE'	=> htmlspecialchars_decode(censor_text($this->get_data('photo_title')), ENT_COMPAT),
			'U_PHOTO'		=> $this->photo_url(true, '#c' . (int) $this->get_data('comment_id')),
		];
	}

	public function create_insert_array($data, $pre_create_data = [])
	{
		$this->set_data('actor_id', (int) $data['actor_id']);
		$this->set_data('photo_id', (int) $data['photo_id']);
		$this->set_data('comment_id', (int) $data['comment_id']);
		$this->set_data('photo_title', $data['photo_title']);

		parent::create_insert_array($data, $pre_create_data);
	}
}
