<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use phpbb\exception\http_exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Diffusion des images : chaque requête vérifie les droits (albums privés réellement privés).
 */
class file extends base
{
	/** @var \Symfony\Component\HttpFoundation\Request */
	protected $symfony_request;

	/** @var \verturin\portfolio\service\watermark */
	protected $watermark;

	public function set_symfony_request($symfony_request)
	{
		$this->symfony_request = $symfony_request;
	}

	public function set_watermark(\verturin\portfolio\service\watermark $watermark)
	{
		$this->watermark = $watermark;
	}

	public function serve($photo_id, $size)
	{
		// Ne pas enregistrer l'URL de l'image comme page courante de la session
		$this->user->update_session_page = false;

		$this->language->add_lang('common', 'verturin/portfolio');
		if (!$this->access->can_use())
		{
			throw new http_exception(403, 'PORTFOLIO_NO_ACCESS');
		}

		list($photo, $album) = $this->load_photo($photo_id, true);

		$download = (bool) $this->request->variable('dl', 0);
		if ($size === 'source' || $download)
		{
			$is_trash_reviewer = (int) $photo['photo_status'] === \verturin\portfolio\service\photo_manager::STATUS_TRASH
				&& $this->access->can_restore_photo($photo, $album);
			if (!$is_trash_reviewer && (!$album || !$this->access->can_download($album)))
			{
				throw new http_exception(403, 'PORTFOLIO_NO_DOWNLOAD');
			}
			$size = 'source';
		}

		$path = $this->image->path($photo['photo_file'], $size);
		if (!is_file($path))
		{
			throw new http_exception(404, 'PORTFOLIO_PHOTO_NOT_FOUND');
		}

		// Filigrane dynamique : nom du site + ID du visiteur (v1.1.0)
		$is_member = $album && $this->access->role($album) >= \verturin\portfolio\service\access::ROLE_CONTRIBUTOR;
		if ($this->watermark && $this->watermark->applies($size, $is_member))
		{
			$marked = $this->watermark->get($photo, $path, $size);
			if ($marked !== '')
			{
				$path = $marked;
			}
		}

		$response = new BinaryFileResponse($path);
		$response->headers->set('Content-Type', $photo['photo_mimetype']);
		$response->headers->set('X-Content-Type-Options', 'nosniff');
		$response->setPrivate();
		$response->setMaxAge(7 * 86400);
		$response->setAutoEtag();
		$response->setAutoLastModified();

		$filename = $this->download_name($photo);
		$response->setContentDisposition(
			$download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
			$filename,
			preg_replace('/[^A-Za-z0-9._-]/', '_', $filename)
		);

		// Réponse 304 si le navigateur possède déjà la bonne version
		if ($this->symfony_request)
		{
			$response->isNotModified($this->symfony_request);
		}

		return $response;
	}

	/**
	 * Image insérée dans un message du forum avec le BBCode [portfolio] (v1.6.0).
	 * mini = vignette, medium et large = image d'affichage (la taille d'affichage est gérée en CSS) :
	 * l'original n'est jamais exposé, le filigrane et les droits de l'album s'appliquent.
	 * Si le lecteur n'a pas le droit de voir la photo, une image neutre remplace l'image cassée.
	 */
	public function embed($photo_id, $size)
	{
		$size = $size === 'mini' ? 'thumb' : 'medium';

		try
		{
			$response = $this->serve($photo_id, $size);
			$response->headers->set('Cache-Control', 'private, max-age=3600');

			return $response;
		}
		catch (http_exception $e)
		{
			$this->language->add_lang('common', 'verturin/portfolio');
			$key = $e->getStatusCode() === 404 ? 'PORTFOLIO_EMBED_MISSING' : 'PORTFOLIO_EMBED_DENIED';
			$text = htmlspecialchars($this->language->lang($key), ENT_QUOTES);
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180" viewBox="0 0 320 180"><rect width="320" height="180" rx="14" fill="#eef1f5"/>'
				. '<g fill="none" stroke="#a5b4c8" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><rect x="132" y="48" width="56" height="44" rx="6"/><circle cx="148" cy="64" r="5"/><path d="m188 82-12-12-26 22"/></g>'
				. '<text x="160" y="132" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#5b6574">' . $text . '</text></svg>';

			return new \Symfony\Component\HttpFoundation\Response($svg, 200, [
				'Content-Type'		=> 'image/svg+xml',
				'Cache-Control'		=> 'private, max-age=300',
				'X-Content-Type-Options' => 'nosniff',
			]);
		}
	}

	protected function download_name(array $photo)
	{
		$base = $photo['photo_realname'] !== '' ? pathinfo($photo['photo_realname'], PATHINFO_FILENAME) : $photo['photo_title'];
		$base = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '', $base));

		return ($base !== '' ? $base : 'photo-' . (int) $photo['photo_id']) . '.' . $photo['photo_ext'];
	}
}
