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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use verturin\portfolio\service\archive;

/**
 * Export ZIP d'un album (avec sous-albums) ou de tout son portfolio, lien de téléchargement unique (v1.4.0).
 */
class export extends base
{
	const FORM_KEY = 'verturin_portfolio_export';

	/** @var archive */
	protected $archive;

	public function set_archive(archive $archive)
	{
		$this->archive = $archive;
	}

	public function index()
	{
		$this->init();
		$this->require_login();
		if (!$this->access->acl('u_portfolio_export') && !$this->access->is_admin())
		{
			$this->deny('PORTFOLIO_NO_EXPORT');
		}

		$album = null;
		$album_id = $this->request->variable('album', 0);
		if ($album_id)
		{
			$album = $this->load_album($album_id);
			if (!$this->access->can_manage_album($album))
			{
				$this->deny('PORTFOLIO_NO_EXPORT');
			}
		}

		$errors = [];
		if ($this->request->is_set_post('create'))
		{
			$errors = $this->create($album);
			if (!$errors)
			{
				return new RedirectResponse($this->helper->route('verturin_portfolio_export', ['done' => 1], false));
			}
		}

		if ($this->request->is_set_post('delete'))
		{
			if (check_form_key(self::FORM_KEY))
			{
				$export = $this->archive->get_export($this->request->variable('delete', ''));
				if ($export && (int) $export['user_id'] === $this->access->user_id())
				{
					$this->archive->delete_export($export);
				}
			}

			return new RedirectResponse($this->helper->route('verturin_portfolio_export', [], false));
		}

		add_form_key(self::FORM_KEY);

		// Photos concernées (aperçu)
		$ids = $album ? array_merge([(int) $album['album_id']], $this->albums->get_descendant_ids((int) $album['album_id'])) : $this->own_album_ids();
		$photo_count = 0;
		foreach ($ids as $id)
		{
			$row = $this->albums->get($id);
			$photo_count += $row ? (int) $row['album_photos'] : 0;
		}

		$exports = [];
		foreach ($this->archive->list_exports($this->access->user_id()) as $row)
		{
			$source = $row['album_id'] ? $this->albums->get($row['album_id']) : null;
			$exports[] = [
				'token'		=> $row['export_token'],
				'name'		=> $row['album_id'] ? ($source ? $source['album_name'] : '—') : $this->language->lang('PORTFOLIO_EXPORT_ALL'),
				'photos'	=> (int) $row['export_photos'],
				'size'		=> get_formatted_filesize((int) $row['export_size']),
				'created'	=> $this->user->format_date((int) $row['export_time']),
				'expires'	=> $this->user->format_date((int) $row['export_expire']),
				'downloads'	=> (int) $row['export_downloads'],
				'url'		=> $this->route('export_download', ['token' => $row['export_token']]),
			];
		}

		$daily = (int) $this->config['portfolio_export_daily'];

		return $this->render('portfolio_export.html', $this->language->lang('PORTFOLIO_EXPORT'), [
			'PF_EXPORT_ALBUM'	=> $album ? $album['album_name'] : '',
			'PF_EXPORT_ALBUMS'	=> count($ids),
			'PF_EXPORT_PHOTOS'	=> $photo_count,
			'PF_EXPORTS'		=> $exports,
			'PF_ERRORS'			=> $errors,
			'PF_DONE'			=> (bool) $this->request->variable('done', 0),
			'PF_TTL'			=> (int) $this->config['portfolio_export_ttl'],
			'PF_DAILY_LEFT'		=> $daily && !$this->access->is_admin() ? max(0, $daily - $this->archive->count_recent_exports($this->access->user_id(), 86400)) : -1,
			'PF_ZIP_OK'			=> archive::zip_available(),
			'U_PF_ACTION'		=> $this->route('export', $album ? ['album' => (int) $album['album_id']] : []),
			'U_PF_ACTION_ALL'	=> $this->route('export'),
			'U_PF_BACK'			=> $album ? $this->route('album', ['album_id' => (int) $album['album_id']]) : $this->route('mine'),
		], $album);
	}

	/**
	 * @return string[] erreurs
	 */
	protected function create(?array $album)
	{
		if (!check_form_key(self::FORM_KEY))
		{
			return [$this->language->lang('FORM_INVALID')];
		}
		if (!archive::zip_available())
		{
			return [$this->language->lang('PORTFOLIO_ZIP_MISSING')];
		}

		$daily = (int) $this->config['portfolio_export_daily'];
		if ($daily && !$this->access->is_admin() && $this->archive->count_recent_exports($this->access->user_id(), 86400) >= $daily)
		{
			return [$this->language->lang('PORTFOLIO_EXPORT_LIMIT', $daily)];
		}

		$roots = $album ? [(int) $album['album_id']] : array_keys($this->albums->get_user_roots($this->access->user_id()));
		if (!$roots)
		{
			return [$this->language->lang('PORTFOLIO_EXPORT_NOTHING')];
		}

		try
		{
			$this->archive->export_user($roots, $this->user->data['username']);
		}
		catch (\RuntimeException $e)
		{
			return [$this->language->lang('PORTFOLIO_ARCHIVE_WRITE')];
		}

		return [];
	}

	protected function own_album_ids()
	{
		$ids = [];
		foreach ($this->albums->get_all() as $id => $row)
		{
			if ((int) $row['owner_id'] === $this->access->user_id())
			{
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Téléchargement par lien unique (réservé au membre qui a créé l'export, ou à un administrateur).
	 */
	public function download($token)
	{
		$this->user->update_session_page = false;
		$this->init();
		$this->require_login();

		$export = $this->archive->get_export($token);
		if (!$export || ((int) $export['user_id'] !== $this->access->user_id() && !$this->access->is_admin()))
		{
			throw new http_exception(404, 'PORTFOLIO_EXPORT_NOT_FOUND');
		}
		if ((int) $export['export_expire'] < time())
		{
			throw new http_exception(410, 'PORTFOLIO_EXPORT_EXPIRED');
		}

		$path = $this->archive->export_path($export);
		if (!is_file($path))
		{
			throw new http_exception(404, 'PORTFOLIO_EXPORT_NOT_FOUND');
		}

		$this->archive->mark_downloaded($export);

		$album = $export['album_id'] ? $this->albums->get($export['album_id']) : null;
		$label = $album ? $album['album_name'] : $this->user->data['username'];
		$label = htmlspecialchars_decode($label, ENT_QUOTES);
		if (function_exists('iconv'))
		{
			$label = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
		}
		$label = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $label), '-') ?: 'portfolio';
		$filename = 'portfolio-' . $label . '-' . gmdate('Y-m-d', (int) $export['export_time']) . '.zip';

		$response = new BinaryFileResponse($path);
		$response->headers->set('Content-Type', 'application/zip');
		$response->setPrivate();
		$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

		return $response;
	}
}
