<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Anciens liens phpBB Gallery (v1.5.0).
 *
 * Intercepte /gallery/image/N, /gallery/image/N/mini|medium|source et /gallery/album/N AVANT le routeur :
 * dès qu'une photo ou un album a été basculé, le lien redirige (301) vers le Portfolio, que phpBB Gallery
 * soit encore installée ou non. Les éléments non basculés restent servis par phpBB Gallery.
 * Quand phpBB Gallery est désactivée, /gallery renvoie vers l'accueil du Portfolio.
 *
 * Le BBCode [image]N[/image] des messages est repris par le Portfolio (voir gallery_migrator::install_bbcode()).
 */
class legacy_listener implements EventSubscriberInterface
{
	/** @var \verturin\portfolio\service\settings */
	protected $settings;

	/** @var \verturin\portfolio\service\gallery_migrator */
	protected $migrator;

	public function __construct(\verturin\portfolio\service\settings $settings, \verturin\portfolio\service\gallery_migrator $migrator)
	{
		$this->settings = $settings;
		$this->migrator = $migrator;
	}

	public static function getSubscribedEvents()
	{
		return [
			// Le routeur de Symfony écoute avec la priorité 32 : on passe avant lui
			KernelEvents::REQUEST						=> ['on_request', 64],
			'core.acp_extensions_run_action_after'	=> 'after_extension_action',
		];
	}

	public function on_request($event)
	{
		$main = method_exists($event, 'isMainRequest') ? $event->isMainRequest() : $event->isMasterRequest();
		if (!$main || !$this->settings['portfolio_legacy_redirect'])
		{
			return;
		}

		$request = $event->getRequest();
		$path = rtrim((string) $request->getPathInfo(), '/');
		if (strpos($path, '/gallery') !== 0)
		{
			return;
		}

		$target = '';
		if (preg_match('#^/gallery/image/(\d+)(?:/(mini|medium|source|\d+))?$#', $path, $m))
		{
			$size = isset($m[2]) && !ctype_digit($m[2]) ? $m[2] : '';
			$target = $this->migrator->legacy_target('image', (int) $m[1], $size);
		}
		else if (preg_match('#^/gallery/album/(\d+)(?:/page/\d+)?$#', $path, $m))
		{
			$target = $this->migrator->legacy_target('album', (int) $m[1]);
		}
		else if (($path === '/gallery' || strpos($path, '/gallery/') === 0) && !$this->migrator->gallery_enabled())
		{
			// phpBB Gallery désactivée ou supprimée : toute autre page de l'ancienne galerie mène au Portfolio
			$target = 'portfolio';
		}

		if ($target === '')
		{
			return;
		}

		$event->setResponse(new RedirectResponse($request->getBaseUrl() . '/' . $target, 301));
	}

	/**
	 * La suppression des données de phpBB Gallery efface le BBCode [image] : on le recrée aussitôt.
	 */
	public function after_extension_action($event)
	{
		if ($event['ext_name'] === 'phpbbgallery/core')
		{
			$this->migrator->ensure_bbcode();
		}
	}
}
