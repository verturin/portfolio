<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\notification\type;

/**
 * Notifications regroupées (v1.10.0) : une par photo et par envoi, avec le nombre de notes,
 * commentaires et réactions reçus depuis le précédent, et le dernier membre concerné.
 */
class digest extends base_type
{
	public static $notification_option = [
		'lang'	=> 'NOTIFICATION_TYPE_PORTFOLIO_DIGEST',
		'group'	=> 'NOTIFICATION_GROUP_MISCELLANEOUS',
	];

	public function get_type()
	{
		return 'verturin.portfolio.notification.type.digest';
	}

	public function is_available()
	{
		return $this->activity_available('', true);
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
		$counts = (array) $this->get_data('counts');
		$parts = [];
		foreach (['rating' => 'PORTFOLIO_NOTIFY_DIGEST_RATINGS', 'comment' => 'PORTFOLIO_NOTIFY_DIGEST_COMMENTS', 'reaction' => 'PORTFOLIO_NOTIFY_DIGEST_REACTIONS'] as $type => $key)
		{
			if (!empty($counts[$type]))
			{
				$parts[] = $this->language->lang($key, (int) $counts[$type]);
			}
		}
		$what = implode($this->language->lang('PORTFOLIO_NOTIFY_DIGEST_SEP'), $parts);
		$others = max(0, (int) $this->get_data('actor_count') - 1);

		return $others
			? $this->language->lang('PORTFOLIO_NOTIFY_DIGEST_MANY', $what, $this->actor_name(), $others)
			: $this->language->lang('PORTFOLIO_NOTIFY_DIGEST_ONE', $what, $this->actor_name());
	}

	public function get_url()
	{
		$comment = (int) $this->get_data('comment_id');

		return $this->photo_url(false, $comment ? '#c' . $comment : '');
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
		$this->set_data('actor_count', (int) $data['actor_count']);
		$this->set_data('photo_id', (int) $data['photo_id']);
		$this->set_data('photo_title', $data['photo_title']);
		$this->set_data('counts', array_map('intval', (array) $data['counts']));
		$this->set_data('comment_id', (int) $data['comment_id']);

		parent::create_insert_array($data, $pre_create_data);
	}
}
