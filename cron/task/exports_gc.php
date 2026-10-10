<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\cron\task;

/**
 * Supprime les exports ZIP expirés (toutes les heures).
 */
class exports_gc extends \phpbb\cron\task\base
{
	/** @var \verturin\portfolio\service\settings */
	protected $settings;

	/** @var \verturin\portfolio\service\archive */
	protected $archive;

	public function __construct(\verturin\portfolio\service\settings $settings, \verturin\portfolio\service\archive $archive)
	{
		$this->settings = $settings;
		$this->archive = $archive;
	}

	public function should_run()
	{
		return (int) $this->settings['portfolio_export_gc'] < time() - 3600;
	}

	public function run()
	{
		$this->archive->purge_expired_exports();
		$this->settings->set('portfolio_export_gc', time());
	}
}
