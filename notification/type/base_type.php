<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\notification\type;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Socle commun des notifications du portfolio.
 */
abstract class base_type extends \phpbb\notification\type\base
{
	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\user_loader */
	protected $user_loader;

	public function set_helper(\phpbb\controller\helper $helper)
	{
		$this->helper = $helper;
	}

	public function set_user_loader(\phpbb\user_loader $user_loader)
	{
		$this->user_loader = $user_loader;
	}

	public function is_available()
	{
		return true;
	}

	public function users_to_query()
	{
		return [(int) $this->get_data('actor_id')];
	}

	public function get_avatar()
	{
		return $this->user_loader->get_avatar((int) $this->get_data('actor_id'), false, true);
	}

	protected function actor_name($mode = 'no_profile')
	{
		return $this->user_loader->get_username((int) $this->get_data('actor_id'), $mode);
	}

	protected function photo_url($absolute = false, $anchor = '')
	{
		$params = ['photo_id' => (int) $this->get_data('photo_id')];

		return $absolute
			? $this->helper->route('verturin_portfolio_photo', $params, false, false, UrlGeneratorInterface::ABSOLUTE_URL) . $anchor
			: $this->helper->route('verturin_portfolio_photo', $params) . $anchor;
	}

	public function get_reference()
	{
		$title = (string) $this->get_data('photo_title');

		return $title !== '' ? $this->language->lang('NOTIFICATION_REFERENCE', censor_text($title)) : '';
	}
}
