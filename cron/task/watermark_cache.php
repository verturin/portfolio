<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\cron\task;

/**
 * Nettoie le cache des images filigranées (une fois par jour).
 */
class watermark_cache extends \phpbb\cron\task\base
{
	/** @var \verturin\portfolio\service\settings */
	protected $settings;

	/** @var \verturin\portfolio\service\watermark */
	protected $watermark;

	public function __construct(\verturin\portfolio\service\settings $settings, \verturin\portfolio\service\watermark $watermark)
	{
		$this->settings = $settings;
		$this->watermark = $watermark;
	}

	public function should_run()
	{
		return (int) $this->settings['portfolio_wm_cache_gc'] < time() - 86400;
	}

	public function run()
	{
		$days = max(1, (int) $this->settings['portfolio_wm_cache_days']);
		$this->watermark->clear_cache($days * 86400);
		$this->settings->set('portfolio_wm_cache_gc', time());
	}
}
