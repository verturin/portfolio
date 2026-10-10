<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use verturin\portfolio\service\photo_manager;

/**
 * Photo : affichage, édition, corbeille, restauration, notation, commentaires.
 */
class photo extends base
{
	/** @var \verturin\portfolio\service\bbcode */
	protected $bbcode;

	/** @var \verturin\portfolio\service\watermark */
	protected $watermark;

	public function set_bbcode(\verturin\portfolio\service\bbcode $bbcode)
	{
		$this->bbcode = $bbcode;
	}

	public function set_watermark(\verturin\portfolio\service\watermark $watermark)
	{
		$this->watermark = $watermark;
	}

	/**
	 * Aperçu rapide (v1.6.0) : notes, réactions et derniers commentaires, chargés dans la visionneuse
	 * depuis les grilles de photos, sans quitter la page.
	 */
	public function quick($photo_id)
	{
		$this->init();
		list($photo, $album) = $this->load_photo($photo_id, true);
		$photo_id = (int) $photo['photo_id'];

		// Les visiteurs n'ont pas accès à la fiche depuis l'aperçu : on leur montre plus de commentaires
		$owner_side = $album && $this->access->is_photo_owner_side($album, $photo);
		$last = $owner_side ? 8 : 50;
		$total = (int) $photo['photo_comments'];
		$social = $this->social($photo, $album, max(0, $total - $last), $last);
		add_form_key('verturin_portfolio_comment', '_COMMENT');

		$card = $this->presenter->photo($photo);
		$card['my_rating'] = $social['PF_MY_RATING'];

		$this->template->assign_vars($social + [
			'PF_PHOTO'			=> $card,
			'PF_ALBUM'			=> $album ? $this->presenter->album($album) : null,
			'PF_MORE_COMMENTS'	=> $owner_side ? max(0, $total - $last) : 0,
			'PF_OWNER_SIDE'		=> $owner_side,
			'PF_EMBED'			=> $this->embed_codes($photo, $album),
			'U_PF_COMMENT'		=> $this->route('photo_comment', ['photo_id' => $photo_id]),
			'U_PF_PHOTO'		=> $this->route('photo', ['photo_id' => $photo_id]),
		]);
		$this->template->set_filenames(['pf_quick' => '@verturin_portfolio/portfolio_quick.html']);

		return $this->json([
			'success'	=> true,
			'html'		=> $this->template->assign_display('pf_quick'),
			'url'		=> $owner_side ? $this->route('photo', ['photo_id' => $photo_id]) : '',
		]);
	}

	/**
	 * Commentaire envoyé depuis l'aperçu rapide (AJAX) ; sans JavaScript, retour à la page photo.
	 */
	public function comment($photo_id)
	{
		$this->init();
		list($photo, $album) = $this->load_photo($photo_id);
		$photo_id = (int) $photo['photo_id'];
		$ajax = $this->request->is_ajax();

		if (!$album || !$this->access->can_comment($album, $photo))
		{
			return $this->json(['success' => false, 'message' => $this->language->lang('PORTFOLIO_NOT_AUTHORISED')], 403);
		}
		if (!check_form_key('verturin_portfolio_comment'))
		{
			return $this->json(['success' => false, 'message' => $this->language->lang('FORM_INVALID')], 400);
		}

		$text = $this->request->variable('comment', '', true);
		if (utf8_strlen(trim($text)) < 2)
		{
			return $this->json(['success' => false, 'message' => $this->language->lang('PORTFOLIO_ERR_COMMENT_EMPTY')], 400);
		}

		$comment_id = $this->photos->add_comment($photo, $text);
		if (!$ajax)
		{
			return new RedirectResponse($this->helper->route('verturin_portfolio_photo', ['photo_id' => $photo_id], false) . '#c' . $comment_id);
		}

		$row = $this->photos->get_comment($comment_id);
		$this->presenter->load_users([(int) $row['user_id']]);
		$this->template->assign_vars([
			'PF_COMMENTS'	=> [[
				'id'		=> $comment_id,
				'user'		=> $this->presenter->username($row['user_id']),
				'avatar'	=> $this->presenter->avatar($row['user_id']),
				'time'		=> $this->user->format_date((int) $row['comment_time']),
				'text'		=> $this->presenter->text($row['comment_text'], $row['comment_uid'], $row['comment_bitfield'], $row['comment_options']),
				'u_delete'	=> '',
			]],
		]);
		$this->template->set_filenames(['pf_comment' => '@verturin_portfolio/portfolio_comment_list.html']);
		$photo = $this->photos->get($photo_id);

		return $this->json([
			'success'	=> true,
			'html'		=> $this->template->assign_display('pf_comment'),
			'count'		=> (int) $photo['photo_comments'],
			'message'	=> $this->language->lang('PORTFOLIO_COMMENT_ADDED'),
		]);
	}
	public function view($photo_id)
	{
		$this->init();
		list($photo, $album) = $this->load_photo($photo_id);
		$photo_id = (int) $photo['photo_id'];
		$status = (int) $photo['photo_status'];
		$is_trash = $status === photo_manager::STATUS_TRASH;

		// Ajout de commentaire
		$comment_errors = [];
		if ($album && $this->request->is_set_post('post_comment'))
		{
			if (!$this->access->can_comment($album, $photo))
			{
				$this->deny();
			}
			$text = $this->request->variable('comment', '', true);
			if (!check_form_key('verturin_portfolio_comment'))
			{
				$comment_errors[] = $this->language->lang('FORM_INVALID');
			}
			else if (utf8_strlen(trim($text)) < 2)
			{
				$comment_errors[] = $this->language->lang('PORTFOLIO_ERR_COMMENT_EMPTY');
			}
			else
			{
				$comment_id = $this->photos->add_comment($photo, $text);

				return new RedirectResponse($this->helper->route('verturin_portfolio_photo', ['photo_id' => $photo_id], false) . '#c' . $comment_id);
			}
		}

		if (!$is_trash && $status === photo_manager::STATUS_APPROVED && !$this->access->is_uploader($photo) && !$this->request->is_ajax())
		{
			$this->photos->add_view($photo_id);
			$photo['photo_views']++;
		}

		$sort = $album ? $this->request->variable('sort', $album['album_sort']) : 'date_desc';
		$sort = in_array($sort, \verturin\portfolio\service\album_manager::SORTS, true) ? $sort : 'date_desc';
		$nav = $album ? $this->photos->navigation($photo, $sort, 5) : ['prev' => 0, 'next' => 0, 'first' => 0, 'last' => 0, 'position' => 0, 'count' => 0, 'strip' => []];
		$keep = ($album && $sort !== $album['album_sort']) ? ['sort' => $sort] : [];
		$photo_url = function ($id) use ($keep) {
			return $id ? $this->route('photo', array_merge(['photo_id' => (int) $id], $keep)) : '';
		};

		// Pellicule : vignettes autour de la photo courante
		$strip = [];
		if (count($nav['strip']) > 1)
		{
			$strip_rows = $this->photos->get_many($nav['strip']);
			foreach ($nav['strip'] as $id)
			{
				if (isset($strip_rows[$id]))
				{
					$strip[] = [
						'id'		=> $id,
						'title'		=> $strip_rows[$id]['photo_title'],
						'thumb'		=> $this->presenter->file_url($strip_rows[$id], 'thumb'),
						'url'		=> $photo_url($id),
						'current'	=> $id === $photo_id,
					];
				}
			}
		}

		// Album suivant / précédent proposé en fin ou début d'album
		$album_nav = $album ? $this->album_navigation($album) : null;
		$album_url = $album ? $this->route('album', array_merge(['album_id' => (int) $album['album_id']], $keep)) : '';
		if ($album && $nav['position'])
		{
			$page = (int) floor(($nav['position'] - 1) / max(6, (int) $this->config['portfolio_per_page'])) + 1;
			if ($page > 1)
			{
				$album_url = $this->route('album', array_merge(['album_id' => (int) $album['album_id'], 'page' => $page], $keep)) . '#p' . $photo_id;
			}
		}

		// Commentaires, notation, réactions
		$per_page = max(5, (int) $this->config['portfolio_comments_per_page']);
		$start = $this->page_start($per_page);
		$social = $this->social($photo, $album, $start, $per_page);

		$can_manage = $album && $this->access->can_manage_album($album);
		$owner_side = $album && $this->access->is_photo_owner_side($album, $photo);
		$can_tweak = $album && !$is_trash && $this->access->can_tweak_photo($album, $photo);
		$can_edit = $album && !$is_trash && $this->access->can_edit_photo($album, $photo);
		$can_delete = $album && !$is_trash && $this->access->can_delete_photo($album, $photo);
		$can_restore = $is_trash && $this->access->can_restore_photo($photo, $album);

		// Rotation en un clic et déplacement (v1.5.0)
		$rotate_urls = [];
		$move_targets = [];
		if ($can_tweak)
		{
			$rotate_hash = generate_link_hash('pf_rotate_' . $photo_id);
			foreach ([270 => 'left', 90 => 'right', 180 => 'flip'] as $degrees => $key)
			{
				$rotate_urls[$key] = $this->route('photo_rotate', ['photo_id' => $photo_id, 'degrees' => $degrees, 'hash' => $rotate_hash]);
			}
			if ($this->access->can_move_photo($album, $photo))
			{
				$move_targets = $this->upload_targets((int) $album['album_id'], $this->move_owners($album, $photo));
				add_form_key('verturin_portfolio_move', '_MOVE');
			}
		}

		add_form_key('verturin_portfolio_comment', '_COMMENT');
		if ($can_restore)
		{
			add_form_key('verturin_portfolio_restore', '_RESTORE');
		}

		$card = $this->presenter->photo($photo);

		// Codes d'insertion dans les messages du forum (v1.6.0)
		$embed = $this->embed_codes($photo, $album);

		// Le propriétaire ne voit pas le filigrane : on lui rappelle que les visiteurs le voient
		$wm_skipped = $owner_side && $this->watermark && $this->watermark->is_enabled()
			&& !$this->watermark->applies('medium', $this->access->role($album) >= \verturin\portfolio\service\access::ROLE_CONTRIBUTOR);

		return $this->render('portfolio_photo.html', $photo['photo_title'], [
			'PF_PHOTO'			=> array_merge($card, [
				'desc'			=> $this->presenter->text($photo['photo_desc'], $photo['photo_desc_uid'], $photo['photo_desc_bitfield'], $photo['photo_desc_options']),
				'avatar'		=> $this->presenter->avatar($photo['user_id']),
				'filesize'		=> $this->presenter->filesize($photo['photo_filesize']),
				'ext'			=> strtoupper($photo['photo_ext']),
				'exif_stripped'	=> (bool) $photo['photo_exif_stripped'],
				'deleted_by'	=> $is_trash ? $this->presenter->username($photo['deleted_by']) : '',
				'deleted_time'	=> $is_trash ? $this->user->format_date((int) $photo['deleted_time']) : '',
				'deleted_reason' => $photo['deleted_reason'],
				'my_rating'		=> $social['PF_MY_RATING'],
				'is_cover'		=> $album && (int) $album['album_cover_id'] === $photo_id,
			]),
			'PF_ALBUM'			=> $album ? $this->presenter->album($album) : null,
			'PF_COMMENT_ERRORS'	=> $comment_errors,
			'PF_COMMENTS_PAGINATION' => $this->presenter->pagination('photo', ['photo_id' => $photo_id], (int) $photo['photo_comments'], $per_page, $start),
			'PF_POSITION'		=> $nav['position'],
			'PF_COUNT'			=> $nav['count'],
			'PF_STRIP'			=> $strip,
			'PF_ALBUM_NAV'		=> $album_nav,
			'U_PF_FIRST'		=> $photo_url($nav['first']),
			'U_PF_LAST'			=> $photo_url($nav['last']),
			'U_PF_BACK_ALBUM'	=> $album_url,
			'PF_TARGETS'		=> $can_restore && !$album ? $this->upload_targets(0, (int) $photo['user_id']) : [],
			'U_PF_PREV'			=> $photo_url($nav['prev']),
			'U_PF_NEXT'			=> $photo_url($nav['next']),
			'U_PF_EDIT'			=> $can_edit ? $this->route('photo_edit', ['photo_id' => $photo_id]) : '',
			'U_PF_DELETE'		=> $can_delete ? $this->route('photo_delete', ['photo_id' => $photo_id]) : '',
			'U_PF_RESTORE'		=> $can_restore ? $this->route('photo_restore', ['photo_id' => $photo_id]) : '',
			'U_PF_COVER'		=> ($album && $this->access->can_set_cover($album) && !$is_trash && $status === photo_manager::STATUS_APPROVED) ? $this->route('photo_cover', ['photo_id' => $photo_id, 'hash' => generate_link_hash('pf_cover_' . $photo_id)]) : '',
			'U_PF_APPROVE'		=> ($status === photo_manager::STATUS_PENDING && ($this->access->can_approve() || $can_manage)) ? $this->route('queue', ['approve' => $photo_id, 'hash' => generate_link_hash('pf_approve_' . $photo_id)]) : '',
			'U_PF_DOWNLOAD'		=> ($album && $this->access->can_download($album)) || $can_restore ? $this->route('file', ['photo_id' => $photo_id, 'size' => 'source', 'dl' => 1]) : '',
			'U_PF_SOURCE'		=> ($album && $this->access->can_download($album)) ? $this->presenter->file_url($photo, 'source') : '',
			'U_PF_ACTION'		=> $this->route('photo', ['photo_id' => $photo_id]),
			'PF_OWNER_SIDE'		=> $owner_side,
			'PF_PROTECTED'		=> $album && $this->access->is_protected($album),
			'PF_EMBED'			=> $embed,
			'PF_WM_SKIPPED'		=> $wm_skipped,
			'PF_ROTATE'			=> $rotate_urls,
			'PF_MOVE_TARGETS'	=> $move_targets,
			'U_PF_MOVE'			=> $move_targets ? $this->route('photo_move', ['photo_id' => $photo_id]) : '',
			'PF_NO_DOWNLOAD'	=> $album && !$this->access->can_download($album) && isset($album['album_download']) && !(int) $album['album_download'] && $this->access->acl('u_portfolio_download'),
		] + $social, $album);
	}

	/**
	 * Lien et BBCode pour insérer la photo dans un message (mini, moyenne, grande).
	 */
	protected function embed_codes(array $photo, ?array $album)
	{
		$photo_id = (int) $photo['photo_id'];
		// Lien et BBCode réservés au posteur et aux membres qu'il a autorisés (v1.8.2)
		if (!$album || !$this->config['portfolio_embed'] || (int) $photo['photo_status'] !== photo_manager::STATUS_APPROVED
			|| !$this->access->is_photo_owner_side($album, $photo))
		{
			return [];
		}

		$page_url = $this->presenter->route_abs('photo', ['photo_id' => $photo_id]);
		$embed = [['key' => 'link', 'label' => $this->language->lang('PORTFOLIO_EMBED_LINK'), 'code' => $page_url]];
		$has_bbcode = $this->bbcode && $this->bbcode->is_installed();
		foreach (['mini', 'medium', 'large'] as $size)
		{
			$code = $has_bbcode
				? '[portfolio=' . $size . ']' . $photo_id . '[/portfolio]'
				: '[url=' . $page_url . '][img]' . $this->presenter->route_abs('embed', ['photo_id' => $photo_id, 'size' => $size]) . '[/img][/url]';
			$embed[] = ['key' => $size, 'label' => $this->language->lang('PORTFOLIO_EMBED_' . strtoupper($size)), 'code' => $code];
		}

		return $embed;
	}

	/**
	 * Commentaires, notation et réactions d'une photo (page photo et aperçu rapide).
	 *
	 * @return array Variables de template
	 */
	protected function social(array $photo, ?array $album, $start, $per_page)
	{
		$photo_id = (int) $photo['photo_id'];

		// Commentaires
		$comment_rows = $this->photos->list_comments($photo_id, $start, $per_page);
		$this->presenter->load_users(array_merge(array_column($comment_rows, 'user_id'), [(int) $photo['user_id'], (int) $photo['deleted_by']]));

		$comments = [];
		foreach ($comment_rows as $row)
		{
			$comments[] = [
				'id'		=> (int) $row['comment_id'],
				'user'		=> $this->presenter->username($row['user_id']),
				'avatar'	=> $this->presenter->avatar($row['user_id']),
				'time'		=> $this->user->format_date((int) $row['comment_time']),
				'text'		=> $this->presenter->text($row['comment_text'], $row['comment_uid'], $row['comment_bitfield'], $row['comment_options']),
				'u_delete'	=> $album && $this->access->can_delete_comment($row, $album) ? $this->route('comment_delete', ['comment_id' => (int) $row['comment_id']]) : '',
			];
		}

		// Notation
		$can_rate = $album && $this->access->can_rate($album, $photo);
		$my_rating = $this->access->is_registered() ? $this->photos->get_user_rating($photo_id, $this->access->user_id()) : 0;
		$rate_urls = [];
		if ($can_rate)
		{
			$hash = generate_link_hash('pf_rate_' . $photo_id);
			for ($i = 0; $i <= 5; $i++)
			{
				$rate_urls[$i] = $this->route('photo_rate', ['photo_id' => $photo_id, 'rating' => $i, 'hash' => $hash]);
			}
		}

		// Réactions (v1.5.0)
		$reactions = [];
		$can_react = $album && $this->access->can_react($album, $photo);
		$active = (int) $photo['photo_status'] === photo_manager::STATUS_APPROVED ? $this->photos->active_reactions() : [];
		if ($active)
		{
			$data = $this->photos->get_reactions($photo_id, $this->access->user_id());
			$this->presenter->load_users(array_merge(...array_values($data['users'] ?: [[]])));
			$react_hash = generate_link_hash('pf_react_' . $photo_id);
			foreach ($active as $code => $emoji)
			{
				$names = [];
				foreach (array_slice(isset($data['users'][$code]) ? $data['users'][$code] : [], 0, 15) as $uid)
				{
					$names[] = $this->presenter->username($uid, 'username');
				}
				$reactions[] = [
					'code'	=> $code,
					'emoji'	=> $emoji,
					'label'	=> $this->language->lang('PORTFOLIO_REACTION_' . strtoupper($code)),
					'count'	=> isset($data['counts'][$code]) ? $data['counts'][$code] : 0,
					'mine'	=> $data['mine'] === $code,
					'who'	=> implode(', ', $names),
					'url'	=> $can_react ? $this->route('photo_react', ['photo_id' => $photo_id, 'reaction' => $code, 'hash' => $react_hash]) : '',
				];
			}
		}

		return [
			'PF_COMMENTS'		=> $comments,
			'PF_CAN_COMMENT'	=> $album && $this->access->can_comment($album, $photo),
			'PF_CAN_RATE'		=> $can_rate,
			'PF_RATE_URLS'		=> $rate_urls,
			'PF_MY_RATING'		=> $my_rating,
			'PF_REACTIONS'		=> $reactions,
			'PF_CAN_REACT'		=> $can_react,
			'PF_IS_REGISTERED'	=> $this->access->is_registered(),
		];
	}

	/**
	 * Réaction à une photo (AJAX, sinon redirection).
	 */
	public function react($photo_id, $reaction)
	{
		$this->init();
		list($photo, $album) = $this->load_photo($photo_id);
		$photo_id = (int) $photo['photo_id'];
		$active = $this->photos->active_reactions();

		if (!$album || !isset($active[$reaction]) || !$this->access->can_react($album, $photo) || !check_link_hash($this->request->variable('hash', ''), 'pf_react_' . $photo_id))
		{
			return $this->json(['success' => false, 'message' => $this->language->lang('PORTFOLIO_NOT_AUTHORISED')], 403);
		}

		$mine = $this->photos->react($photo_id, $this->access->user_id(), $reaction);

		if (!$this->request->is_ajax())
		{
			return new RedirectResponse($this->helper->route('verturin_portfolio_photo', ['photo_id' => $photo_id], false));
		}

		$data = $this->photos->get_reactions($photo_id, $this->access->user_id());

		return $this->json(['success' => true, 'mine' => $mine, 'counts' => (object) $data['counts']]);
	}

	/**
	 * Rotation enregistrée en un clic (90° ↻, 270° ↺, 180°).
	 */
	public function rotate($photo_id, $degrees)
	{
		$this->init();
		$this->require_login();
		list($photo, $album) = $this->load_photo($photo_id);
		$photo_id = (int) $photo['photo_id'];

		if (!$album || (int) $photo['photo_status'] === photo_manager::STATUS_TRASH || !$this->access->can_tweak_photo($album, $photo)
			|| !check_link_hash($this->request->variable('hash', ''), 'pf_rotate_' . $photo_id))
		{
			return $this->request->is_ajax() ? $this->json(['success' => false, 'message' => $this->language->lang('PORTFOLIO_NOT_AUTHORISED')], 403) : $this->deny();
		}

		$info = $this->image->rotate($photo['photo_file'], (int) $degrees);
		if (!$info)
		{
			return $this->request->is_ajax() ? $this->json(['success' => false, 'message' => $this->language->lang('PORTFOLIO_ROTATE_FAILED')], 500) : $this->deny('PORTFOLIO_ROTATE_FAILED');
		}
		$this->photos->update($photo_id, $info);

		$url = $this->helper->route('verturin_portfolio_photo', ['photo_id' => $photo_id], false);
		if ($this->request->is_ajax())
		{
			$photo = array_merge($photo, $info);

			return $this->json([
				'success'	=> true,
				'medium'	=> $this->presenter->file_url($photo, 'medium'),
				'width'		=> (int) $info['photo_width'],
				'height'	=> (int) $info['photo_height'],
				'message'	=> $this->language->lang('PORTFOLIO_ROTATED'),
			]);
		}

		return new RedirectResponse($url);
	}

	/**
	 * Déplacer la photo vers un autre album ou sous-album.
	 */
	public function move($photo_id)
	{
		$this->init();
		$this->require_login();
		list($photo, $album) = $this->load_photo($photo_id);
		$photo_id = (int) $photo['photo_id'];

		if (!$album || !$this->access->can_move_photo($album, $photo) || !check_form_key('verturin_portfolio_move'))
		{
			$this->deny();
		}

		$target = $this->albums->get($this->request->variable('target_album', 0));
		if (!$target || (int) $target['album_id'] === (int) $album['album_id'] || !$this->same_owner($album, $target, $photo) || !$this->access->can_upload($target))
		{
			$this->deny();
		}

		$this->photos->move([$photo_id], (int) $target['album_id']);

		return $this->done('PORTFOLIO_PHOTOS_MOVED', $this->route('photo', ['photo_id' => $photo_id]), [1, $target['album_name']]);
	}

	public function edit($photo_id)
	{
		$this->init();
		$this->require_login();
		list($photo, $album) = $this->load_photo($photo_id);
		if (!$album || (int) $photo['photo_status'] === photo_manager::STATUS_TRASH || !$this->access->can_edit_photo($album, $photo))
		{
			$this->deny();
		}

		$photo_id = (int) $photo['photo_id'];
		$errors = [];

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('verturin_portfolio_photo_edit'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			$title = $this->request->variable('photo_title', '', true);
			if (trim($title) === '')
			{
				$errors[] = $this->language->lang('PORTFOLIO_ERR_TITLE');
			}

			if (!$errors)
			{
				$this->photos->update($photo_id, [
					'photo_title'	=> $title,
					'photo_desc'	=> $this->request->variable('photo_desc', '', true),
				]);

				$rotate = $this->request->variable('rotate', 0);
				if (in_array($rotate, [90, 180, 270], true))
				{
					$info = $this->image->rotate($photo['photo_file'], $rotate);
					if ($info)
					{
						$this->photos->update($photo_id, $info);
					}
				}

				$target_id = $this->request->variable('album_id', (int) $album['album_id']);
				if ($target_id !== (int) $album['album_id'] && $this->access->can_move_photo($album, $photo))
				{
					$target = $this->albums->get($target_id);
					if ($target && $this->same_owner($album, $target, $photo) && $this->access->can_upload($target))
					{
						$this->photos->move([$photo_id], $target_id);
					}
				}

				return new RedirectResponse($this->helper->route('verturin_portfolio_photo', ['photo_id' => $photo_id], false));
			}
		}

		add_form_key('verturin_portfolio_photo_edit');
		$decoded = generate_text_for_edit($photo['photo_desc'], $photo['photo_desc_uid'], (int) $photo['photo_desc_options']);

		$targets = $this->access->can_move_photo($album, $photo) ? $this->upload_targets(0, $this->move_owners($album, $photo)) : [];
		$has_current = false;
		foreach ($targets as &$target)
		{
			$target['selected'] = $target['id'] === (int) $album['album_id'];
			$has_current = $has_current || $target['selected'];
		}
		unset($target);
		if (!$has_current)
		{
			array_unshift($targets, ['id' => (int) $album['album_id'], 'name' => $album['album_name'], 'owner' => '', 'selected' => true]);
		}

		return $this->render('portfolio_photo_edit.html', $this->language->lang('PORTFOLIO_EDIT_PHOTO'), [
			'PF_PHOTO'		=> $this->presenter->photo($photo) + [
				'desc_edit'	=> $this->request->is_set_post('submit') ? $this->request->variable('photo_desc', '', true) : $decoded['text'],
			],
			'PF_ERRORS'		=> $errors,
			'PF_TARGETS'	=> $targets,
			'U_PF_ACTION'	=> $this->route('photo_edit', ['photo_id' => $photo_id]),
			'U_PF_CANCEL'	=> $this->route('photo', ['photo_id' => $photo_id]),
		], $album);
	}

	/**
	 * Suppression = mise en corbeille (validation définitive par un administrateur).
	 */
	public function delete($photo_id)
	{
		$this->init();
		$this->require_login();
		list($photo, $album) = $this->load_photo($photo_id);
		if (!$album || (int) $photo['photo_status'] === photo_manager::STATUS_TRASH || !$this->access->can_delete_photo($album, $photo))
		{
			$this->deny();
		}

		$back = $this->route('album', ['album_id' => (int) $album['album_id']]);

		if (confirm_box(true))
		{
			$exempt = $this->access->is_admin() || $this->access->acl('m_portfolio_delete');
			if (!$this->photos->deletion_allowed($this->access->user_id(), 1, $exempt))
			{
				$this->deny('PORTFOLIO_DELETE_BLOCKED');
			}

			$this->photos->trash([(int) $photo['photo_id']], $this->request->variable('reason', '', true));

			return $this->done('PORTFOLIO_PHOTO_TRASHED', $back);
		}

		$this->language->add_lang('common', 'verturin/portfolio');
		confirm_box(false, $this->language->lang('PORTFOLIO_TRASH_CONFIRM', $photo['photo_title']), build_hidden_fields(['photo_id' => (int) $photo['photo_id']]), '@verturin_portfolio/portfolio_confirm_trash.html');

		return new RedirectResponse($back);
	}

	public function restore($photo_id)
	{
		$this->init();
		$this->require_login();
		$photo = $this->photos->get($photo_id);
		if (!$photo || (int) $photo['photo_status'] !== photo_manager::STATUS_TRASH)
		{
			$this->not_found('PORTFOLIO_PHOTO_NOT_FOUND');
		}

		$album = $this->albums->get($photo['album_id']);
		if (!$this->access->can_restore_photo($photo, $album))
		{
			$this->deny();
		}

		if (!$this->request->is_set_post('restore') || !check_form_key('verturin_portfolio_restore'))
		{
			$this->deny('FORM_INVALID');
		}

		$target = 0;
		if (!$album)
		{
			$target_album = $this->albums->get($this->request->variable('target_album', 0));
			if (!$target_album || (int) $target_album['owner_id'] !== (int) $photo['user_id'] || !$this->access->can_upload($target_album))
			{
				$this->deny('PORTFOLIO_RESTORE_NEED_ALBUM');
			}
			$target = (int) $target_album['album_id'];
		}

		$restored = $this->photos->restore([(int) $photo['photo_id']], $target);
		if (!$restored)
		{
			$this->deny('PORTFOLIO_RESTORE_FAILED');
		}

		return $this->done('PORTFOLIO_PHOTO_RESTORED', $this->route('photo', ['photo_id' => (int) $photo['photo_id']]));
	}

	public function rate($photo_id, $rating)
	{
		$this->init();
		list($photo, $album) = $this->load_photo($photo_id);

		if (!$album || !$this->access->can_rate($album, $photo) || !check_link_hash($this->request->variable('hash', ''), 'pf_rate_' . (int) $photo['photo_id']))
		{
			return $this->json(['success' => false, 'message' => $this->language->lang('PORTFOLIO_NOT_AUTHORISED')], 403);
		}

		$result = $this->photos->rate((int) $photo['photo_id'], $this->access->user_id(), (int) $rating);
		$display = $this->presenter->rating((int) round($result['avg'] * 100), $result['count']);

		if (!$this->request->is_ajax())
		{
			return new RedirectResponse($this->helper->route('verturin_portfolio_photo', ['photo_id' => (int) $photo['photo_id']], false));
		}

		return $this->json([
			'success'	=> true,
			'mine'		=> (int) $rating,
			'avg'		=> $display['avg_txt'],
			'avg_num'	=> $display['avg'],
			'percent'	=> $display['percent'],
			'count'		=> $display['count'],
			'label'		=> $this->language->lang('PORTFOLIO_RATINGS', $display['count']),
		]);
	}

	public function cover($photo_id)
	{
		$this->init();
		list($photo, $album) = $this->load_photo($photo_id);
		if (!$album || !$this->access->can_set_cover($album) || !check_link_hash($this->request->variable('hash', ''), 'pf_cover_' . (int) $photo['photo_id']))
		{
			$this->deny();
		}

		$this->albums->set_cover((int) $album['album_id'], (int) $photo['photo_id']);

		return $this->done('PORTFOLIO_COVER_SET', $this->route('photo', ['photo_id' => (int) $photo['photo_id']]));
	}

	public function comment_delete($comment_id)
	{
		$this->init();
		$this->require_login();

		$comment = $this->photos->get_comment($comment_id);
		if (!$comment)
		{
			$this->not_found('PORTFOLIO_COMMENT_NOT_FOUND');
		}

		list($photo, $album) = $this->load_photo($comment['photo_id']);
		if (!$album || !$this->access->can_delete_comment($comment, $album))
		{
			$this->deny();
		}

		$back = $this->route('photo', ['photo_id' => (int) $photo['photo_id']]);

		if (confirm_box(true))
		{
			$this->photos->delete_comment($comment);

			return $this->done('PORTFOLIO_COMMENT_DELETED', $back);
		}

		confirm_box(false, $this->language->lang('PORTFOLIO_COMMENT_DELETE_CONFIRM'), build_hidden_fields(['comment_id' => (int) $comment['comment_id']]));

		return new RedirectResponse($back);
	}
}
