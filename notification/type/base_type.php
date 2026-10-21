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

	/** @var \verturin\portfolio\service\settings|null */
	protected $pf_settings;

	public function set_pf_settings(\verturin\portfolio\service\settings $settings)
	{
		$this->pf_settings = $settings;
	}

	public function is_available()
	{
		return true;
	}

	/**
	 * Type proposé dans le panneau de l'utilisateur selon la page ACP « Notifications » (v1.10.0) :
	 * notifications « une par action » ou « regroupées ».
	 */
	protected function activity_available($setting, $digest)
	{
		if (!$this->pf_settings)
		{
			return true;
		}
		$is_digest = $this->pf_settings['portfolio_notify_mode'] === 'digest';
		if ($digest)
		{
			return $is_digest && ($this->pf_settings['portfolio_notify_rating'] || $this->pf_settings['portfolio_notify_comment'] || $this->pf_settings['portfolio_notify_reaction']);
		}

		return !$is_digest && (bool) $this->pf_settings[$setting];
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
