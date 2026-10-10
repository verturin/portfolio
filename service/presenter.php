<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Transforme les lignes de la base en tableaux prêts pour les templates Twig.
 */
class presenter
{
	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\user_loader */
	protected $user_loader;

	/** @var album_manager */
	protected $albums;

	/** @var photo_manager */
	protected $photos;

	/** @var access */
	protected $access;

	/** @var watermark|null */
	protected $watermark;

	/** @var bool Étiquette de visibilité sur les cartes d'album (administrateurs, si l'ACP l'autorise) */
	protected $show_vis = false;

	public function set_show_visibility($enabled)
	{
		$this->show_vis = (bool) $enabled;
	}

	/** @var bool Aperçu rapide (notes, réactions, commentaires) depuis les grilles */
	protected $quickview = false;

	public function set_quickview($enabled)
	{
		$this->quickview = (bool) $enabled;
	}

	public function set_watermark(watermark $watermark)
	{
		$this->watermark = $watermark;
	}

	public function __construct(\phpbb\controller\helper $helper, \phpbb\user $user, \phpbb\language\language $language, \phpbb\user_loader $user_loader, album_manager $albums, photo_manager $photos, access $access)
	{
		$this->helper = $helper;
		$this->user = $user;
		$this->language = $language;
		$this->user_loader = $user_loader;
		$this->albums = $albums;
		$this->photos = $photos;
		$this->access = $access;
	}

	public function route($name, array $params = [])
	{
		return $this->helper->route('verturin_portfolio_' . $name, $params);
	}

	/**
	 * URL absolue (utilisable depuis l'ACP et dans les e-mails).
	 */
	public function route_abs($name, array $params = [])
	{
		return $this->helper->route('verturin_portfolio_' . $name, $params, true, false, \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
	}

	public function load_users(array $user_ids)
	{
		$user_ids = array_unique(array_filter(array_map('intval', $user_ids)));
		if ($user_ids)
		{
			$this->user_loader->load_users($user_ids);
		}
	}

	public function user_exists($user_id)
	{
		$this->load_users([$user_id]);
		$row = $this->user_loader->get_user((int) $user_id);

		return $row && (int) $row['user_id'] === (int) $user_id && (int) $user_id !== ANONYMOUS && (int) $row['user_type'] !== USER_IGNORE;
	}

	public function username($user_id, $mode = 'full')
	{
		return $this->user_loader->get_username((int) $user_id, $mode);
	}

	public function avatar($user_id)
	{
		return $this->user_loader->get_avatar((int) $user_id, false, true);
	}

	public function file_url(array $photo, $size)
	{
		return $this->route('file', [
			'photo_id'	=> (int) $photo['photo_id'],
			'size'		=> $size,
			'v'			=> substr($photo['photo_hash'], 0, 8) . ($this->watermark ? $this->watermark->version() : ''),
		]);
	}

	public function text($text, $uid, $bitfield, $options)
	{
		if ($text === '' || $text === null)
		{
			return '';
		}

		return generate_text_for_display($text, $uid, $bitfield, (int) $options);
	}

	public function rating($avg_x100, $count)
	{
		$avg = ((int) $avg_x100) / 100;

		return [
			'avg'		=> $avg,
			'avg_txt'	=> number_format($avg, 1, ',', ' '),
			'percent'	=> round($avg * 20, 1),
			'count'		=> (int) $count,
		];
	}

	/**
	 * Carte photo (grilles).
	 */
	/**
	 * @param array $url_params Paramètres ajoutés au lien de la photo (ex. ['sort' => 'title'] pour garder l'ordre de l'album)
	 */
	public function photo(array $photo, array $url_params = [])
	{
		return [
			'id'			=> (int) $photo['photo_id'],
			'title'			=> $photo['photo_title'],
			'url'			=> $this->route('photo', array_merge(['photo_id' => (int) $photo['photo_id']], $url_params)),
			'thumb'			=> $this->file_url($photo, 'thumb'),
			'medium'		=> $this->file_url($photo, 'medium'),
			'width'			=> (int) $photo['photo_width'],
			'height'		=> (int) $photo['photo_height'],
			'views'			=> (int) $photo['photo_views'],
			'comments'		=> (int) $photo['photo_comments'],
			'rating'		=> $this->rating($photo['photo_rating_avg'], $photo['photo_rating_count']),
			'user_id'		=> (int) $photo['user_id'],
			'user'			=> $this->username($photo['user_id']),
			'user_name'		=> $this->username($photo['user_id'], 'username'),
			'user_colour'	=> $this->username($photo['user_id'], 'colour'),
			'user_url'		=> (int) $photo['user_id'] && (int) $photo['user_id'] !== ANONYMOUS ? $this->route('user', ['user_id' => (int) $photo['user_id']]) : '',
			'time'			=> $this->user->format_date((int) $photo['photo_time']),
			'time_iso'		=> gmdate('c', (int) $photo['photo_time']),
			'pending'		=> (int) $photo['photo_status'] === photo_manager::STATUS_PENDING,
			'trashed'		=> (int) $photo['photo_status'] === photo_manager::STATUS_TRASH,
			'album_id'		=> (int) $photo['album_id'],
			'quick'			=> $this->quickview ? $this->route('photo_quick', ['photo_id' => (int) $photo['photo_id']]) : '',
		];
	}

	public function photos(array $rows, array $url_params = [])
	{
		$this->load_users(array_column($rows, 'user_id'));
		$reactions = $this->photos->reaction_summary(array_column($rows, 'photo_id'));

		$out = [];
		foreach ($rows as $row)
		{
			$card = $this->photo($row, $url_params);
			$card['reactions'] = isset($reactions[$card['id']]) ? $reactions[$card['id']] : ['total' => 0, 'top' => []];
			$out[] = $card;
		}

		return $out;
	}

	/**
	 * Cartes album, avec couverture et nombre de sous-albums visibles.
	 */
	public function albums(array $albums)
	{
		if (!$albums)
		{
			return [];
		}

		$cover_ids = [];
		foreach ($albums as $album)
		{
			$cover_ids[] = (int) ($album['album_cover_id'] ?: $album['album_last_photo_id']);
		}
		$covers = $this->photos->get_many($cover_ids);
		$this->load_users(array_column($albums, 'owner_id'));

		$out = [];
		foreach ($albums as $album)
		{
			$out[] = $this->album($album, $covers);
		}

		return $out;
	}

	public function album(array $album, array $covers = [])
	{
		$cover_id = (int) ($album['album_cover_id'] ?: $album['album_last_photo_id']);
		$cover = isset($covers[$cover_id]) && (int) $covers[$cover_id]['photo_status'] === photo_manager::STATUS_APPROVED ? $covers[$cover_id] : null;

		$children = 0;
		$photos_total = (int) $album['album_photos'];
		foreach ($this->albums->get_descendant_ids((int) $album['album_id']) as $child_id)
		{
			$child = $this->albums->get($child_id);
			if ($child && $this->access->can_view_album($child))
			{
				if ((int) $child['parent_id'] === (int) $album['album_id'])
				{
					$children++;
				}
				$photos_total += (int) $child['album_photos'];
			}
		}

		return [
			'id'			=> (int) $album['album_id'],
			'name'			=> $album['album_name'],
			'url'			=> $this->route('album', ['album_id' => (int) $album['album_id']]),
			'cover'			=> $cover ? $this->file_url($cover, 'thumb') : '',
			'cover_medium'	=> $cover ? $this->file_url($cover, 'medium') : '',
			'photos'		=> (int) $album['album_photos'],
			'photos_total'	=> $photos_total,
			'children'		=> $children,
			'owner_id'		=> (int) $album['owner_id'],
			'owner'			=> (int) $album['owner_id'] ? $this->username($album['owner_id'], 'no_profile') : '',
			'owner_url'		=> (int) $album['owner_id'] ? $this->route('user', ['user_id' => (int) $album['owner_id']]) : '',
			'is_public'		=> (int) $album['owner_id'] === 0,
			'visibility'	=> (int) $album['album_visibility'],
			'vis_code'		=> isset(access::VIS_CODES[(int) $album['album_visibility']]) ? access::VIS_CODES[(int) $album['album_visibility']] : 'all',
			'vis_icon'		=> isset(access::VIS_ICONS[(int) $album['album_visibility']]) ? access::VIS_ICONS[(int) $album['album_visibility']] : 'globe',
			'vis_friends'	=> !empty($album['album_friends']),
			'show_vis'		=> $this->show_vis,
			'locked'		=> (bool) $album['album_locked'],
			'last_time'		=> $album['album_last_time'] ? $this->user->format_date((int) $album['album_last_time']) : '',
			'role'			=> $role = $this->access->role($album),
			'role_code'		=> [0 => '', 1 => 'viewer', 2 => 'contributor', 3 => 'manager', 4 => 'owner'][$role] ?? '',
			// Propriétaire : avec combien de membres l'album est partagé (v1.8.1)
			'shared_count'	=> $role === access::ROLE_OWNER ? count($this->albums->shared_users($album)) : 0,
			'u_sharing'		=> $role === access::ROLE_OWNER ? $this->route('sharing', ['album' => (int) $album['album_id']]) : '',
		];
	}

	/**
	 * Fil d'Ariane (inclut le portfolio du propriétaire pour un album personnel).
	 */
	public function breadcrumbs(?array $album = null)
	{
		$crumbs = [['name' => $this->language->lang('PORTFOLIO'), 'url' => $this->route('index')]];

		if ($album)
		{
			$chain = $this->albums->get_chain((int) $album['album_id']);
			$owner_id = (int) $chain[0]['owner_id'];
			if ($owner_id)
			{
				$this->load_users([$owner_id]);
				$crumbs[] = [
					'name'	=> $this->language->lang('PORTFOLIO_OF', $this->username($owner_id, 'username')),
					'url'	=> $this->route('user', ['user_id' => $owner_id]),
				];
			}
			foreach ($chain as $node)
			{
				$crumbs[] = ['name' => $node['album_name'], 'url' => $this->route('album', ['album_id' => (int) $node['album_id']])];
			}
		}

		return $crumbs;
	}

	/**
	 * Pagination (rendu entièrement en Twig).
	 */
	public function pagination($route, array $params, $total, $per_page, $start)
	{
		$per_page = max(1, (int) $per_page);
		$pages = (int) ceil($total / $per_page);
		$current = (int) floor($start / $per_page) + 1;

		if ($pages <= 1)
		{
			return ['pages' => [], 'total' => $pages, 'current' => 1, 'prev' => '', 'next' => ''];
		}

		$url = function ($page) use ($route, $params) {
			return $this->route($route, array_merge($params, $page > 1 ? ['page' => $page] : []));
		};

		$list = [];
		$last = 0;
		for ($i = 1; $i <= $pages; $i++)
		{
			if ($i === 1 || $i === $pages || abs($i - $current) <= 2)
			{
				if ($last && $i - $last > 1)
				{
					$list[] = ['gap' => true];
				}
				$list[] = ['num' => $i, 'url' => $url($i), 'current' => $i === $current, 'gap' => false];
				$last = $i;
			}
		}

		return [
			'pages'		=> $list,
			'total'		=> $pages,
			'current'	=> $current,
			'prev'		=> $current > 1 ? $url($current - 1) : '',
			'next'		=> $current < $pages ? $url($current + 1) : '',
		];
	}

	public function filesize($bytes)
	{
		return get_formatted_filesize((int) $bytes);
	}
}
