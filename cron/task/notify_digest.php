<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\cron\task;

/**
 * Notifications regroupées (v1.10.0) : toutes les minutes (délai réglable), une notification par photo
 * pour les notes, commentaires et réactions reçus. Ménage quotidien des actions déjà notifiées.
 *
 * Comme toutes les tâches phpBB, elle se lance lors des visites du forum (ou par le cron système si
 * l'administrateur l'a configuré) : sans aucune visite, l'envoi attend la suivante.
 */
class notify_digest extends \phpbb\cron\task\base
{
	/** @var \verturin\portfolio\service\activity */
	protected $activity;

	public function __construct(\verturin\portfolio\service\activity $activity)
	{
		$this->activity = $activity;
	}

	public function should_run()
	{
		return ($this->activity->mode() === 'digest' && $this->activity->digest_due()) || $this->activity->cleanup_due();
	}

	public function run()
	{
		if ($this->activity->mode() === 'digest' && $this->activity->digest_due())
		{
			$this->activity->send_digest();
		}
		if ($this->activity->cleanup_due())
		{
			$this->activity->cleanup();
		}
	}
}
