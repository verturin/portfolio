<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\cron\task;

/**
 * Purge automatique OPTIONNELLE de la corbeille (désactivée par défaut : 0 jour).
 * Par défaut, seule la validation manuelle d'un administrateur efface définitivement une photo.
 */
class purge_trash extends \phpbb\cron\task\base
{
	/** @var \verturin\portfolio\service\settings Réglages du Portfolio */
	protected $config;

	/** @var \verturin\portfolio\service\photo_manager */
	protected $photos;

	public function __construct(\verturin\portfolio\service\settings $config, \verturin\portfolio\service\photo_manager $photos)
	{
		$this->config = $config;
		$this->photos = $photos;
	}

	public function is_runnable()
	{
		return (int) $this->config['portfolio_trash_autopurge'] > 0;
	}

	public function should_run()
	{
		return (int) $this->config['portfolio_purge_last_gc'] < time() - 86400;
	}

	public function run()
	{
		$ids = $this->photos->trash_older_than((int) $this->config['portfolio_trash_autopurge'], 500);
		if ($ids)
		{
			$this->photos->purge($ids);
		}

		$this->config->set('portfolio_purge_last_gc', time(), false);
	}
}
