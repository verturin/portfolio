<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use phpbb\exception\http_exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cible du BBCode [image]N[/image] repris de phpBB Gallery (v1.5.0).
 * Photo basculée => Portfolio ; sinon => phpBB Gallery tant qu'elle est installée ; sinon image « indisponible ».
 */
class legacy
{
	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \verturin\portfolio\service\gallery_migrator */
	protected $migrator;

	/** @var \Symfony\Component\HttpFoundation\Request */
	protected $symfony_request;

	public function __construct(\phpbb\request\request_interface $request, \phpbb\language\language $language, \verturin\portfolio\service\gallery_migrator $migrator, $symfony_request)
	{
		$this->symfony_request = $symfony_request;
		$this->request = $request;
		$this->language = $language;
		$this->migrator = $migrator;
	}

	public function image($image_id, $size = '')
	{
		$size = in_array($size, ['mini', 'medium', 'source'], true) ? $size : '';
		$base = $this->base_url();

		$target = $this->migrator->legacy_target('image', (int) $image_id, $size);
		if ($target !== '')
		{
			return new RedirectResponse($base . '/' . $target, 301);
		}

		if ($this->migrator->gallery_enabled())
		{
			return new RedirectResponse($base . '/gallery/image/' . (int) $image_id . ($size ? '/' . $size : ''), 302);
		}

		if ($size !== '')
		{
			// Vignette d'un message : petite image neutre plutôt qu'une image cassée
			$this->language->add_lang('common', 'verturin/portfolio');
			$text = htmlspecialchars($this->language->lang('PORTFOLIO_LEGACY_UNAVAILABLE'), ENT_QUOTES);
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="160" viewBox="0 0 240 160"><rect width="240" height="160" rx="12" fill="#eef1f5"/><path d="M95 62h50v36H95z" fill="none" stroke="#a5b4c8" stroke-width="4"/><circle cx="108" cy="74" r="5" fill="#a5b4c8"/><text x="120" y="128" text-anchor="middle" font-family="sans-serif" font-size="13" fill="#6b7684">' . $text . '</text></svg>';

			return new Response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=3600']);
		}

		throw new http_exception(404, 'PORTFOLIO_PHOTO_NOT_FOUND');
	}

	public function album($album_id)
	{
		$target = $this->migrator->legacy_target('album', (int) $album_id);
		if ($target === '')
		{
			throw new http_exception(404, 'PORTFOLIO_ALBUM_NOT_FOUND');
		}

		return new RedirectResponse($this->base_url() . '/' . $target, 301);
	}

	protected function base_url()
	{
		return $this->symfony_request->getBaseUrl();
	}
}
