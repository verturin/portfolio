<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Envoi de photos (glisser-déposer multiple en AJAX, formulaire classique en repli).
 */
class upload extends base
{
	const FORM_KEY = 'verturin_portfolio_upload';

	/** @var \verturin\portfolio\service\pending_pm|null Message privé « photo en attente » (v1.10.4) */
	protected $pending_pm;

	public function set_pending_pm(\verturin\portfolio\service\pending_pm $pending_pm)
	{
		$this->pending_pm = $pending_pm;
	}

	public function upload($album_id)
	{
		$this->init();
		$this->require_login();

		$album = $this->load_album($album_id);
		if (!$this->access->can_upload($album))
		{
			$this->deny('PORTFOLIO_NO_UPLOAD');
		}

		if ($this->request->is_set_post('upload'))
		{
			return $this->handle($album);
		}

		// Envoi reçu mais vide : le fichier dépasse post_max_size, PHP a tout rejeté (v1.5.0)
		if ($this->request->is_ajax() && $this->request->server('REQUEST_METHOD') === 'POST')
		{
			return $this->json(['success' => false, 'message' => $this->language->lang('PORTFOLIO_ERR_SERVER_LIMIT', @ini_get('post_max_size'), @ini_get('upload_max_filesize'))], 413);
		}

		add_form_key(self::FORM_KEY);

		$quota = $this->access->upload_quota_left();
		$used = $this->photos->count_user_photos($this->access->user_id());
		$max_kb = (int) $this->config['portfolio_max_filesize'];
		$exts = $this->image->allowed_extensions();

		$targets = [];
		foreach ($this->upload_targets() as $target)
		{
			$target['url'] = $this->route('upload', ['album_id' => $target['id']]);
			$target['selected'] = $target['id'] === (int) $album['album_id'];
			$targets[] = $target;
		}

		return $this->render('portfolio_upload.html', $this->language->lang('PORTFOLIO_UPLOAD_TO', $album['album_name']), [
			'PF_ALBUM'			=> $this->presenter->album($album),
			'PF_TARGETS'		=> $targets,
			'PF_MAX_SIZE'		=> $max_kb ? get_formatted_filesize($max_kb * 1024) : '',
			'PF_MAX_BYTES'		=> $max_kb * 1024,
			'PF_EXTENSIONS'		=> implode(', ', array_unique(array_map('strtoupper', $exts))),
			'PF_ACCEPT'			=> implode(',', array_map(function ($e) {
				return '.' . $e;
			}, $exts)),
			'PF_QUOTA'			=> $quota,
			'PF_QUOTA_LEFT'		=> $quota > 0 ? max(0, $quota - $used) : -1,
			'PF_NEEDS_APPROVAL'	=> $this->access->needs_approval($album),
			'PF_STRIP_EXIF'		=> (bool) $this->config['portfolio_strip_exif'],
			'U_PF_ACTION'		=> $this->route('upload', ['album_id' => (int) $album['album_id']]),
			'U_PF_ALBUM'		=> $this->route('album', ['album_id' => (int) $album['album_id']]),
		], $album);
	}

	protected function handle(array $album)
	{
		$ajax = $this->request->is_ajax();

		// Une erreur PHP fatale (mémoire, extension manquante…) produirait une page illisible pour le navigateur :
		// on renvoie à la place un message JSON qui indique la vraie cause.
		if ($ajax)
		{
			ob_start();
			$language = $this->language;
			register_shutdown_function(function () use ($language) {
				$error = error_get_last();
				if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true))
				{
					while (ob_get_level())
					{
						ob_end_clean();
					}
					if (!headers_sent())
					{
						http_response_code(500);
						header('Content-Type: application/json');
					}
					echo json_encode(['success' => false, 'message' => $language->lang('PORTFOLIO_ERR_SERVER', $error['message'] . ' (' . basename($error['file']) . ':' . $error['line'] . ')')]);
				}
			});
		}

		try
		{
			return $this->process($album, $ajax);
		}
		catch (\phpbb\exception\http_exception $e)
		{
			throw $e;
		}
		catch (\Throwable $e)
		{
			if (!$ajax)
			{
				throw $e;
			}

			return $this->json(['success' => false, 'message' => $this->language->lang('PORTFOLIO_ERR_SERVER', $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')')], 500);
		}
	}

	protected function process(array $album, $ajax)
	{
		$error = $this->validate();

		if (!$error)
		{
			$file = $this->request->file('photo');
			$result = $this->image->process_upload(is_array($file) ? $file : []);

			if (isset($result['error']))
			{
				$error = $this->language->lang($result['error'], $this->config['portfolio_max_filesize'] ? get_formatted_filesize((int) $this->config['portfolio_max_filesize'] * 1024) : '');
			}
			else
			{
				$pending = $this->access->needs_approval($album);
				$photo_id = $this->photos->add($album, $result, trim($this->request->variable('title', '', true)), $pending);
				$photo = $this->photos->get($photo_id);
				if ($pending && $this->pending_pm && $photo)
				{
					$this->pending_pm->photo_pending($photo, $album);
				}

				if (!$ajax)
				{
					return new RedirectResponse($this->helper->route('verturin_portfolio_album', ['album_id' => (int) $album['album_id']], false));
				}

				return $this->json([
					'success'	=> true,
					'pending'	=> $pending,
					'photo'		=> $this->presenter->photo($photo),
					'message'	=> $this->language->lang($pending ? 'PORTFOLIO_UPLOAD_PENDING' : 'PORTFOLIO_UPLOAD_OK'),
				]);
			}
		}

		if ($ajax)
		{
			return $this->json(['success' => false, 'message' => $error], 400);
		}

		return $this->helper->message($error, [], 'PORTFOLIO_UPLOAD', 400);
	}

	/**
	 * @return string Message d'erreur ou chaîne vide
	 */
	protected function validate()
	{
		if (!check_form_key(self::FORM_KEY, -1))
		{
			return $this->language->lang('FORM_INVALID');
		}

		$quota = $this->access->upload_quota_left();
		if ($quota > 0 && $this->photos->count_user_photos($this->access->user_id()) >= $quota)
		{
			return $this->language->lang('PORTFOLIO_ERR_QUOTA', $quota);
		}

		return '';
	}
}
