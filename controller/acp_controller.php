<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\controller;

use verturin\portfolio\service\access;
use verturin\portfolio\service\photo_manager;

/**
 * Administration : réglages, albums et droits par groupe, corbeille (validation définitive).
 */
class acp_controller
{
	/** @var \verturin\portfolio\service\settings Réglages du Portfolio */
	protected $config;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \verturin\portfolio\service\access */
	protected $access;

	/** @var \verturin\portfolio\service\album_manager */
	protected $albums;

	/** @var \verturin\portfolio\service\photo_manager */
	protected $photos;

	/** @var \verturin\portfolio\service\presenter */
	protected $presenter;

	protected $photos_table;
	protected $root_path;
	protected $php_ext;

	/** @var string */
	protected $u_action;

	/**
	 * Réglages : clé => [type, min, max]
	 */
	protected $settings = [
		'PORTFOLIO_LEGEND_DISPLAY' => [
			'portfolio_navbar'				=> ['bool'],
			'portfolio_grid_size'			=> ['int', 100, 400],
			'portfolio_vis_badges'			=> ['bool'],
			'portfolio_per_page'			=> ['int', 6, 200],
			'portfolio_comments_per_page'	=> ['int', 5, 200],
			'portfolio_index_recent'		=> ['int', 4, 60],
			'portfolio_rate_own'			=> ['bool'],
		],
		'PORTFOLIO_LEGEND_ALBUMS' => [
			'portfolio_vis_choices'				=> ['visibilities'],
			'portfolio_vis_default'				=> ['visibility'],
			'portfolio_album_lock_choice'		=> ['bool'],
			'portfolio_album_download_choice'	=> ['bool'],
			'portfolio_album_download_default'	=> ['bool'],
			'portfolio_album_sort_choice'		=> ['bool'],
		],
		'PORTFOLIO_LEGEND_IMAGES' => [
			'portfolio_max_filesize'		=> ['int', 0, 512000],
			'portfolio_max_width'			=> ['int', 0, 20000],
			'portfolio_max_height'			=> ['int', 0, 20000],
			'portfolio_medium_size'			=> ['int', 320, 4000],
			'portfolio_thumb_size'			=> ['int', 120, 1200],
			'portfolio_jpeg_quality'		=> ['int', 40, 100],
			'portfolio_allowed_ext'			=> ['string'],
			'portfolio_strip_exif'			=> ['bool'],
		],
		'PORTFOLIO_LEGEND_LIMITS' => [
			'portfolio_user_quota'			=> ['int', 0, 1000000],
			'portfolio_user_max_albums'		=> ['int', 0, 10000],
			'portfolio_max_depth'			=> ['int', 1, 20],
		],
		'PORTFOLIO_LEGEND_SAFETY' => [
			'portfolio_alert_threshold'		=> ['int', 0, 100000],
			'portfolio_alert_window'		=> ['int', 60, 604800],
			'portfolio_alert_block'			=> ['bool'],
			'portfolio_trash_autopurge'		=> ['int', 0, 3650],
		],
		'PORTFOLIO_LEGEND_SOCIAL' => [
			'portfolio_reactions_enabled'	=> ['bool'],
			'portfolio_reactions_list'		=> ['reactions'],
			'portfolio_profile_link'		=> ['bool'],
			'portfolio_miniprofile_link'	=> ['bool'],
			'portfolio_profile_photos'		=> ['select', [0, 3, 4, 5, 6, 8, 10, 12, 15, 20], 5],
			'portfolio_quickview'			=> ['bool'],
			'portfolio_embed'				=> ['bool'],
		],
		'PORTFOLIO_LEGEND_EXPORT' => [
			'portfolio_export_ttl'			=> ['int', 1, 720],
			'portfolio_export_daily'		=> ['int', 0, 100],
		],
	];

	public function __construct(
		\verturin\portfolio\service\settings $config,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\log\log_interface $log,
		\phpbb\db\driver\driver_interface $db,
		\verturin\portfolio\service\access $access,
		\verturin\portfolio\service\album_manager $albums,
		\verturin\portfolio\service\photo_manager $photos,
		\verturin\portfolio\service\presenter $presenter,
		$photos_table,
		$root_path,
		$php_ext
	)
	{
		$this->config = $config;
		$this->template = $template;
		$this->user = $user;
		$this->language = $language;
		$this->request = $request;
		$this->log = $log;
		$this->db = $db;
		$this->access = $access;
		$this->albums = $albums;
		$this->photos = $photos;
		$this->presenter = $presenter;
		$this->photos_table = $photos_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/** @var \verturin\portfolio\service\watermark */
	protected $watermark;

	public function set_watermark(\verturin\portfolio\service\watermark $watermark)
	{
		$this->watermark = $watermark;
	}

	/** @var \verturin\portfolio\service\archive */
	protected $archive;

	public function set_archive(\verturin\portfolio\service\archive $archive)
	{
		$this->archive = $archive;
	}

	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	/* ------------------------------------------------------------------
	 * Réglages
	 * ------------------------------------------------------------------ */

	public function settings()
	{
		$form_key = 'verturin_portfolio_acp_settings';
		add_form_key($form_key);

		// Régénération des vignettes et images d'affichage, par lots (v1.7.0)
		if ($this->request->variable('action', '') === 'rebuild' && check_link_hash($this->request->variable('hash', ''), 'pf_acp_rebuild'))
		{
			$this->rebuild_images();
		}

		// (Ré)installation du BBCode [portfolio] (après un changement d'adresse du forum par exemple)
		if ($this->request->variable('action', '') === 'bbcode' && check_link_hash($this->request->variable('hash', ''), 'pf_acp_bbcode'))
		{
			$ok = $this->bbcode->install(generate_board_url());
			global $phpbb_container;
			$phpbb_container->get('cache')->purge();
			trigger_error($this->language->lang($ok ? 'ACP_PORTFOLIO_PFBBCODE_DONE' : 'ACP_PORTFOLIO_PFBBCODE_FULL') . adm_back_link($this->u_action), $ok ? E_USER_NOTICE : E_USER_WARNING);
		}

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			foreach ($this->settings as $fields)
			{
				foreach ($fields as $key => $def)
				{
					switch ($def[0])
					{
						case 'bool':
							$value = $this->request->variable($key, 0) ? 1 : 0;
						break;
						case 'int':
							$value = min($def[2], max($def[1], $this->request->variable($key, (int) $this->config[$key])));
						break;
						case 'visibilities':
							$picked = array_intersect([0, 1, 2, 4, 3], array_map('intval', $this->request->variable($key, [0])));
							$value = implode(',', $picked ?: [1]);
						break;
						case 'visibility':
							$value = min(4, max(0, $this->request->variable($key, 1)));
						break;
						case 'select':
							$value = $this->request->variable($key, (int) $def[2]);
							$value = in_array($value, $def[1], true) ? $value : (int) $def[2];
						break;
						case 'reactions':
							$picked = array_intersect(array_keys(\verturin\portfolio\service\photo_manager::REACTIONS), $this->request->variable($key, ['']));
							$value = implode(',', $picked ?: ['like']);
						break;
						default:
							$value = $this->clean_extensions($this->request->variable($key, (string) $this->config[$key]));
					}
					$this->config->set($key, $value);
				}
			}

			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_SETTINGS');
			trigger_error($this->language->lang('PORTFOLIO_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		$legends = [];
		foreach ($this->settings as $legend => $fields)
		{
			$items = [];
			foreach ($fields as $key => $def)
			{
				$lang = strtoupper($key);
				$choices = [];
				if ($def[0] === 'visibilities' || $def[0] === 'visibility')
				{
					$current = array_map('intval', explode(',', (string) $this->config[$key]));
					foreach ([0 => 'ALL', 1 => 'REGISTERED', 2 => 'GROUPS', 4 => 'FRIENDS', 3 => 'PRIVATE'] as $vis => $name)
					{
						$choices[] = [
							'value'		=> $vis,
							'label'		=> $this->language->lang('PORTFOLIO_VIS_' . $name),
							'explain'	=> $vis === 2 ? $this->language->lang('ACP_PORTFOLIO_VIS_GROUPS_NOTE') : '',
							'checked'	=> in_array($vis, $current, true),
						];
					}
				}
				if ($def[0] === 'select')
				{
					foreach ($def[1] as $option)
					{
						$choices[] = [
							'value'		=> $option,
							'label'		=> $option ? $option : $this->language->lang('ACP_PORTFOLIO_SELECT_NONE'),
							'checked'	=> (int) $this->config[$key] === $option,
						];
					}
				}
				if ($def[0] === 'reactions')
				{
					$current = explode(',', (string) $this->config[$key]);
					foreach (\verturin\portfolio\service\photo_manager::REACTIONS as $code => $emoji)
					{
						$choices[] = ['value' => $code, 'emoji' => $emoji, 'label' => $this->language->lang('PORTFOLIO_REACTION_' . strtoupper($code)), 'checked' => in_array($code, $current, true)];
					}
				}
				$items[] = [
					'key'		=> $key,
					'type'		=> $def[0],
					'choices'	=> $choices,
					'value'		=> $this->config[$key],
					'min'		=> isset($def[1]) && !is_array($def[1]) ? $def[1] : '',
					'max'		=> isset($def[2]) && $def[0] !== 'select' ? $def[2] : '',
					'label'		=> $this->language->lang($lang),
					'explain'	=> $this->language->is_set($lang . '_EXPLAIN') ? $this->language->lang($lang . '_EXPLAIN') : '',
				];
			}
			$legends[] = ['title' => $this->language->lang($legend), 'items' => $items];
		}

		$this->template->assign_vars([
			'PF_LEGENDS'	=> $legends,
			'PF_STATS'		=> $this->stats(),
			'PF_VERSION'	=> $this->config['portfolio_version'],
			'PF_GD'			=> function_exists('gd_info') ? gd_info()['GD Version'] : '',
			'PF_EXIF_EXT'	=> function_exists('exif_read_data'),
			'PF_WEBP'		=> function_exists('imagewebp'),
			'PF_BBCODE'		=> $this->bbcode ? $this->bbcode->is_installed() : false,
			'U_PF_REBUILD'	=> $this->u_action . '&amp;action=rebuild&amp;hash=' . generate_link_hash('pf_acp_rebuild'),
			'U_PF_BBCODE'	=> $this->u_action . '&amp;action=bbcode&amp;hash=' . generate_link_hash('pf_acp_bbcode'),
			'U_ACTION'		=> $this->u_action,
			'U_PF_FRONT'	=> $this->presenter->route_abs('index'),
		]);
	}

	/**
	 * Recalcule vignette et image d'affichage de toutes les photos (après un changement de taille).
	 * Travaille par lots pour ne jamais dépasser le temps d'exécution de PHP.
	 */
	protected function rebuild_images()
	{
		global $phpbb_container;
		$image = $phpbb_container->get('verturin.portfolio.image');

		$after = $this->request->variable('after', 0);
		$done = $this->request->variable('done', 0);
		$batch = 15;
		$started = microtime(true);

		$result = $this->db->sql_query('SELECT COUNT(photo_id) AS n FROM ' . $this->photos_table);
		$total = (int) $this->db->sql_fetchfield('n');
		$this->db->sql_freeresult($result);
		$sql = 'SELECT photo_id, photo_file FROM ' . $this->photos_table . ' WHERE photo_id > ' . (int) $after . ' ORDER BY photo_id ASC';
		$result = $this->db->sql_query_limit($sql, $batch);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		$processed = 0;
		foreach ($rows as $row)
		{
			$image->rebuild_derivatives($row['photo_file']);
			$after = (int) $row['photo_id'];
			$done++;
			$processed++;
			if (microtime(true) - $started > 15)
			{
				break;
			}
		}

		if (count($rows) < $batch && $processed === count($rows))
		{
			if ($this->watermark)
			{
				$this->watermark->clear_cache();
			}
			$this->config->increment('portfolio_wm_revision', 1);
			trigger_error($this->language->lang('ACP_PORTFOLIO_REBUILD_DONE', $done) . adm_back_link($this->u_action));
		}

		$next = $this->u_action . '&amp;action=rebuild&amp;hash=' . generate_link_hash('pf_acp_rebuild') . '&amp;after=' . $after . '&amp;done=' . $done;
		meta_refresh(1, $next);
		trigger_error($this->language->lang('ACP_PORTFOLIO_REBUILD_PROGRESS', $done, $total));
	}

	/** @var \verturin\portfolio\service\page_manager */
	protected $pages;

	/** @var \verturin\portfolio\service\forum_link */
	protected $forum_link;

	public function set_pages(\verturin\portfolio\service\page_manager $pages, \verturin\portfolio\service\forum_link $forum_link)
	{
		$this->pages = $pages;
		$this->forum_link = $forum_link;
	}

	/** @var \verturin\portfolio\service\departed */
	protected $departed;

	public function set_departed(\verturin\portfolio\service\departed $departed)
	{
		$this->departed = $departed;
	}

	/* ------------------------------------------------------------------
	 * Membres partis : repreneur et albums orphelins (v1.9.0)
	 * ------------------------------------------------------------------ */

	public function departed()
	{
		$form_key = 'verturin_portfolio_acp_departed';
		add_form_key($form_key);
		$action = $this->request->variable('action', '');

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$name = trim($this->request->variable('heir_name', '', true));
			$heir_id = 0;
			if ($name !== '')
			{
				$heir_id = $this->user_id_from_name($name);
				if (!$heir_id)
				{
					trigger_error($this->language->lang('ACP_PORTFOLIO_HEIR_NOT_FOUND', $name) . adm_back_link($this->u_action), E_USER_WARNING);
				}
			}
			$mode = $this->request->variable('departed_mode', 'transfer');
			if (!in_array($mode, ['transfer', 'follow', 'trash'], true))
			{
				$mode = 'transfer';
			}

			$this->config->set('portfolio_heir_id', $heir_id);
			$this->config->set('portfolio_departed_mode', $mode);
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_SETTINGS');
			trigger_error($this->language->lang('PORTFOLIO_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		if ($action === 'adopt' || $action === 'adopt_all')
		{
			$scan = $this->departed->scan();
			$target = $this->request->variable('user_id', 0);
			$sign = (bool) $this->request->variable('sign_photos', 0);
			$to_name = trim($this->request->variable('to_name', '', true));
			$heir_id = $this->departed->heir_id();
			if ($to_name !== '')
			{
				$heir_id = $this->user_id_from_name($to_name);
				if (!$heir_id)
				{
					trigger_error($this->language->lang('ACP_PORTFOLIO_HEIR_NOT_FOUND', $to_name) . adm_back_link($this->u_action), E_USER_WARNING);
				}
			}
			$todo = array_filter($scan, function ($row) use ($action, $target, $sign) {
				return ($row['albums'] > 0 || ($sign && $row['photos'] > 0)) && ($action === 'adopt_all' || $row['user_id'] === $target);
			});

			if (!$todo)
			{
				redirect($this->u_action);
			}

			$heir_name = $this->departed->username_of($heir_id);
			if (confirm_box(true))
			{
				$albums = 0;
				$photos = 0;
				foreach ($todo as $row)
				{
					$label = $row['username'] !== '' ? $row['username'] : $this->language->lang('PORTFOLIO_DEPARTED_UNKNOWN', $row['user_id']);
					if ($row['username'] === '')
					{
						$this->departed->record($row['user_id'], $label);
					}
					if ($row['albums'])
					{
						$this->departed->adopt($row['user_id'], $heir_id, $this->language->lang('PORTFOLIO_DEPARTED_ALBUM', $label));
						$albums += $row['albums'];
					}
					if ($sign)
					{
						$photos += $this->departed->assign_photos($row['user_id'], $heir_id);
					}
				}
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_ADOPTED', false, [count($todo), $heir_name]);
				trigger_error($this->language->lang('ACP_PORTFOLIO_ADOPT_DONE', count($todo), $albums, $heir_name) . ($sign ? '<br>' . $this->language->lang('ACP_PORTFOLIO_SIGN_DONE', $photos, $heir_name) : '') . adm_back_link($this->u_action));
			}

			$names = array_map(function ($row) {
				return $row['username'] !== '' ? $row['username'] : '#' . $row['user_id'];
			}, $todo);
			confirm_box(false, $this->language->lang($sign ? 'ACP_PORTFOLIO_ADOPT_SIGN_CONFIRM' : 'ACP_PORTFOLIO_ADOPT_CONFIRM', implode(', ', $names), $heir_name), build_hidden_fields(['action' => $action, 'user_id' => $target, 'sign_photos' => (int) $sign, 'to_name' => $to_name]));
			redirect($this->u_action);
		}

		$heir_id = $this->departed->heir_id();
		$set_heir = (int) $this->config['portfolio_heir_id'];
		$rows = [];
		$adoptable = 0;
		foreach ($this->departed->scan() as $row)
		{
			$adoptable += $row['albums'] ? 1 : 0;
			$rows[] = [
				'id'		=> $row['user_id'],
				'name'		=> $row['username'] !== '' ? $row['username'] : $this->language->lang('PORTFOLIO_DEPARTED_UNKNOWN', $row['user_id']),
				'known'		=> $row['username'] !== '',
				'albums'	=> $row['albums'],
				'photos'	=> $row['photos'],
				'u_adopt'	=> $this->u_action . '&amp;action=adopt&amp;user_id=' . $row['user_id'],
			];
		}

		$history = [];
		foreach ($this->departed->history() as $row)
		{
			$album = (int) $row['album_id'] ? $this->albums->get((int) $row['album_id']) : null;
			$history[] = [
				'name'		=> $row['username'],
				'heir'		=> $this->presenter->username((int) $row['heir_id']),
				'time'		=> $this->user->format_date((int) $row['departed_time']),
				'album'		=> $album ? $album['album_name'] : '',
				'u_album'	=> $album ? $this->presenter->route_abs('album', ['album_id' => (int) $album['album_id']]) : '',
			];
		}

		$this->template->assign_vars([
			'PF_HEIR_USERNAME'	=> $this->departed->username_of($heir_id),
			'PF_HEIR_NAME'		=> $set_heir && $this->departed->user_exists($set_heir) ? $this->departed->username_of($set_heir) : '',
			'PF_HEIR_EFFECTIVE'	=> $this->presenter->username($heir_id),
			'PF_HEIR_DEFAULT'	=> !$set_heir || !$this->departed->user_exists($set_heir),
			'PF_DEPARTED_MODE'	=> (string) $this->config['portfolio_departed_mode'] ?: 'transfer',
			'PF_ORPHANS'		=> $rows,
			'PF_ADOPTABLE'		=> $adoptable,
			'PF_HISTORY'		=> $history,
			'U_PF_ADOPT_ALL'	=> $this->u_action . '&amp;action=adopt_all',
			'U_FIND_USERNAME'	=> append_sid($this->root_path . 'memberlist.' . $this->php_ext, 'mode=searchuser&amp;form=acp_portfolio_departed&amp;field=heir_name&amp;select_single=true'),
			'U_ACTION'			=> $this->u_action,
		]);
	}

	protected function user_id_from_name($name)
	{
		$sql = 'SELECT user_id FROM ' . USERS_TABLE . "
			WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($name)) . "'
				AND user_type <> " . USER_IGNORE;
		$result = $this->db->sql_query($sql);
		$user_id = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		return $user_id;
	}

	/* ------------------------------------------------------------------
	 * Pages Portfolio publiées dans le forum (v1.8.0)
	 * ------------------------------------------------------------------ */

	public function pages()
	{
		$action = $this->request->variable('action', '');
		$page_id = $this->request->variable('page_id', 0);
		$page = $page_id ? $this->pages->get($page_id) : null;
		$this->language->add_lang('acp/forums');

		if ($action === 'delete' && $page)
		{
			if (confirm_box(true))
			{
				if ((int) $page['forum_id'])
				{
					$this->forum_link->delete((int) $page['forum_id']);
				}
				$this->pages->delete($page_id);
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_PAGE_DELETED', false, [$page['page_title']]);
				trigger_error($this->language->lang('ACP_PORTFOLIO_PAGE_DELETED') . adm_back_link($this->u_action));
			}
			confirm_box(false, $this->language->lang('ACP_PORTFOLIO_PAGE_DELETE_CONFIRM', $page['page_title']), build_hidden_fields(['action' => 'delete', 'page_id' => $page_id]));
			redirect($this->u_action);
		}

		if ($action === 'add' || ($action === 'edit' && $page))
		{
			$this->page_form($page);

			return;
		}

		$rows = [];
		foreach ($this->pages->get_all() as $row)
		{
			$album = (int) $row['album_id'] ? $this->albums->get((int) $row['album_id']) : null;
			$forum_ok = (int) $row['forum_id'] && $this->forum_link->exists((int) $row['forum_id']);
			$rows[] = [
				'title'		=> $row['page_title'],
				'type'		=> $this->language->lang('ACP_PORTFOLIO_PAGE_TYPE_' . strtoupper($row['page_type'])),
				'album'		=> $row['page_type'] === 'album' && $album ? $album['album_name'] : '',
				'protected'	=> $album && !empty($album['album_protected']),
				'forum'		=> $forum_ok,
				'u_view'	=> $this->page_url((int) $row['page_id']),
				'u_perms'	=> $forum_ok ? append_sid($this->root_path . 'adm/index.' . $this->php_ext, 'i=acp_permissions&amp;mode=setting_forum_local&amp;forum_id[]=' . (int) $row['forum_id']) : '',
				'u_forum'	=> $forum_ok ? append_sid($this->root_path . 'adm/index.' . $this->php_ext, 'i=acp_forums&amp;mode=manage&amp;action=edit&amp;f=' . (int) $row['forum_id']) : '',
				'u_edit'	=> $this->u_action . '&amp;action=edit&amp;page_id=' . (int) $row['page_id'],
				'u_delete'	=> $this->u_action . '&amp;action=delete&amp;page_id=' . (int) $row['page_id'],
			];
		}

		$this->template->assign_vars([
			'PF_PAGES'		=> $rows,
			'U_PF_ADD'		=> $this->u_action . '&amp;action=add',
		]);
	}

	protected function page_url($page_id)
	{
		return generate_board_url() . '/app.php/portfolio/page/' . (int) $page_id;
	}

	protected function page_form(?array $page)
	{
		$form_key = 'verturin_portfolio_acp_page';
		$errors = [];
		$page_id = $page ? (int) $page['page_id'] : 0;
		$forum_id = $page && $this->forum_link->exists((int) $page['forum_id']) ? (int) $page['forum_id'] : 0;

		$data = [
			'page_title'	=> $page ? $page['page_title'] : '',
			'page_desc'		=> $page ? generate_text_for_edit($page['page_desc'], $page['page_desc_uid'], (int) $page['page_desc_options'])['text'] : '',
			'page_type'		=> $page ? $page['page_type'] : 'recent',
			'album_id'		=> $page ? (int) $page['album_id'] : 0,
			'page_limit'	=> $page ? (int) $page['page_limit'] : 24,
		];
		$album = $data['album_id'] ? $this->albums->get($data['album_id']) : null;
		$protect = $album ? !empty($album['album_protected']) : true;
		$publish = $page ? (bool) $forum_id : true;
		$parent_id = 0;

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			$data = [
				'page_title'	=> trim($this->request->variable('page_title', '', true)),
				'page_desc'		=> $this->request->variable('page_desc', '', true),
				'page_type'		=> $this->request->variable('page_type', 'recent'),
				'album_id'		=> $this->request->variable('album_id', 0),
				'page_limit'	=> $this->request->variable('page_limit', 24),
			];
			$protect = (bool) $this->request->variable('protect_album', 0);
			$publish = (bool) $this->request->variable('publish', 0);
			$parent_id = $this->request->variable('parent_id', 0);

			if ($data['page_title'] === '')
			{
				$errors[] = $this->language->lang('ACP_PORTFOLIO_PAGE_ERR_TITLE');
			}
			if (!in_array($data['page_type'], \verturin\portfolio\service\page_manager::TYPES, true))
			{
				$data['page_type'] = 'recent';
			}
			if ($data['page_type'] === 'album' && $data['album_id'] >= 0 && !$this->albums->get($data['album_id']))
			{
				$errors[] = $this->language->lang('ACP_PORTFOLIO_PAGE_ERR_ALBUM');
			}

			if (!$errors)
			{
				// Nouvel album public créé pour la page (ex. « Présentations »)
				if ($data['page_type'] === 'album' && $data['album_id'] < 0)
				{
					$data['album_id'] = $this->albums->create([
						'album_name'		=> $data['page_title'],
						'album_desc'		=> '',
						'album_visibility'	=> \verturin\portfolio\service\access::VIS_REGISTERED,
						'album_sort'		=> 'date_desc',
						'parent_id'			=> 0,
						'owner_id'			=> 0,
					]);
				}
				if ($data['page_type'] !== 'album')
				{
					$data['album_id'] = 0;
				}
				else
				{
					$this->albums->update($data['album_id'], ['album_protected' => $protect ? 1 : 0]);
				}

				$uid = $bitfield = '';
				$options = 7;
				$desc = $data['page_desc'];
				if (trim($desc) !== '')
				{
					$this->language->add_lang('posting');
					generate_text_for_storage($desc, $uid, $bitfield, $options, true, true, true);
				}
				$page_id = $this->pages->save(array_merge($data, [
					'page_desc'				=> $desc,
					'page_desc_uid'			=> $uid,
					'page_desc_bitfield'	=> $bitfield,
					'page_desc_options'		=> $options,
				]), $page_id);

				// Forum-lien : créé, mis à jour ou retiré
				$message = '';
				if ($publish && !$forum_id)
				{
					$created = $this->forum_link->create($parent_id, $data['page_title'], '', $this->page_url($page_id));
					if (is_int($created))
					{
						$this->pages->set_forum($page_id, $created);
						$message = '<br><br>' . $this->language->lang('ACP_PORTFOLIO_PAGE_FORUM_CREATED', '<a href="' . append_sid($this->root_path . 'adm/index.' . $this->php_ext, 'i=acp_permissions&amp;mode=setting_forum_local&amp;forum_id[]=' . $created) . '">', '</a>');
					}
					else
					{
						$message = '<br><br>' . $created;
					}
				}
				else if ($publish && $forum_id)
				{
					$this->forum_link->update($forum_id, $data['page_title'], '', $this->page_url($page_id));
				}
				else if (!$publish && $forum_id)
				{
					$this->forum_link->delete($forum_id);
					$this->pages->set_forum($page_id, 0);
				}

				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_PAGE_SAVED', false, [$data['page_title']]);
				trigger_error($this->language->lang('ACP_PORTFOLIO_PAGE_SAVED') . $message . adm_back_link($this->u_action));
			}
		}

		add_form_key($form_key);

		$types = [];
		foreach (\verturin\portfolio\service\page_manager::TYPES as $type)
		{
			$types[] = ['value' => $type, 'label' => $this->language->lang('ACP_PORTFOLIO_PAGE_TYPE_' . strtoupper($type)), 'selected' => $type === $data['page_type']];
		}
		$albums = [];
		foreach ($this->albums->flat_tree() as $id => $node)
		{
			$owner = (int) $node['album']['owner_id'] ? ' (' . $this->presenter->username($node['album']['owner_id'], 'username') . ')' : '';
			$albums[] = ['value' => $id, 'label' => str_repeat('— ', $node['depth']) . $node['album']['album_name'] . $owner, 'selected' => $id === (int) $data['album_id']];
		}
		$forums = [];
		foreach ($this->forum_link->select_options($parent_id) as $id => $row)
		{
			if ((int) $row['forum_type'] === FORUM_LINK)
			{
				continue;
			}
			$forums[] = ['value' => $id, 'label' => $row['padding'] . $row['forum_name'], 'selected' => !empty($row['selected'])];
		}

		$this->template->assign_vars([
			'S_PF_PAGE_FORM'	=> true,
			'PF_ERRORS'			=> $errors,
			'PF_FORM'			=> $data + ['protect' => $protect, 'publish' => $publish, 'has_forum' => (bool) $forum_id],
			'PF_TYPES'			=> $types,
			'PF_ALBUM_OPTIONS'	=> $albums,
			'PF_FORUMS'			=> $forums,
			'PF_IS_NEW'			=> !$page,
			'U_ACTION'			=> $this->u_action . '&amp;action=' . ($page ? 'edit&amp;page_id=' . $page_id : 'add'),
			'U_BACK'			=> $this->u_action,
		]);
	}

	/** @var \verturin\portfolio\service\bbcode */
	protected $bbcode;

	public function set_bbcode(\verturin\portfolio\service\bbcode $bbcode)
	{
		$this->bbcode = $bbcode;
	}

	protected function clean_extensions($value)
	{
		$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		$list = array_intersect(array_map('trim', explode(',', strtolower($value))), $allowed);

		return implode(',', $list ?: ['jpg', 'jpeg', 'png']);
	}

	protected function stats()
	{
		$disk = 0;
		$dir = $this->root_path . 'files/portfolio';
		if (is_dir($dir))
		{
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file)
			{
				$disk += $file->getSize();
			}
		}

		return [
			'albums'	=> count($this->albums->get_all()),
			'approved'	=> $this->photos->count_by_status(photo_manager::STATUS_APPROVED),
			'pending'	=> $this->photos->count_by_status(photo_manager::STATUS_PENDING),
			'trash'		=> $this->photos->count_by_status(photo_manager::STATUS_TRASH),
			'disk'		=> get_formatted_filesize($disk),
			'writable'	=> is_dir($dir) && is_writable($dir),
		];
	}

	/* ------------------------------------------------------------------
	 * Bascule depuis phpBB Gallery (v1.5.0)
	 * ------------------------------------------------------------------ */

	/** @var \verturin\portfolio\service\gallery_migrator */
	protected $migrator;

	public function set_migrator(\verturin\portfolio\service\gallery_migrator $migrator)
	{
		$this->migrator = $migrator;
	}

	public function migrate()
	{
		$form_key = 'verturin_portfolio_acp_migrate';
		$action = $this->request->variable('action', '');

		// Périmètre : tout le forum, un groupe ou un membre
		$scope = $this->request->variable('scope', 'all');
		$scope = in_array($scope, ['all', 'group', 'user'], true) ? $scope : 'all';
		$value = 0;
		if ($scope === 'group')
		{
			$value = $this->request->variable('group_id', $this->request->variable('value', 0));
		}
		else if ($scope === 'user')
		{
			$value = $this->request->variable('value', 0);
			$name = $this->request->variable('username', '', true);
			if ($name !== '')
			{
				$value = $this->find_user_id($name);
			}
		}
		$include_public = (bool) $this->request->variable('include_public', 1);
		$strip_exif = (bool) $this->request->variable('strip_exif', (int) $this->config['portfolio_strip_exif']);
		$users = $scope === 'all' ? null : $this->migrator->scope_users($scope, $value);

		$scope_params = '&amp;scope=' . $scope . '&amp;value=' . (int) $value . '&amp;include_public=' . (int) $include_public . '&amp;strip_exif=' . (int) $strip_exif;
		$progress = null;
		$plan = null;
		$message = '';
		$missing = null;

		// Liste des fichiers introuvables (lecture seule, v1.9.1)
		if ($action === 'missing')
		{
			$missing = $this->missing_rows();
			$action = '';
		}

		if ($action !== '')
		{
			// Les lots suivants s'enchaînent par lien signé ; tout le reste exige le jeton du formulaire
			$is_batch = $action === 'run' && $this->request->variable('hash', '') !== '';
			$valid = $is_batch
				? check_link_hash($this->request->variable('hash', ''), 'pf_migrate_' . $scope . '_' . (int) $value)
				: (confirm_box(true) || check_form_key($form_key));
			if (!$valid)
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			if (in_array($action, ['plan', 'run'], true) && (!$this->migrator->gallery_available() || ($scope !== 'all' && !$value)))
			{
				trigger_error($this->language->lang($this->migrator->gallery_available() ? 'ACP_PORTFOLIO_MIGRATE_SCOPE_MISSING' : 'ACP_PORTFOLIO_MIGRATE_NO_GALLERY') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			switch ($action)
			{
				case 'plan':
					$plan = $this->migrator->plan($users, $include_public);
					foreach ($plan['per_user'] as &$plan_row)
					{
						$plan_row['username'] = $this->member_label($plan_row['user_id'], $plan_row['username']);
					}
					unset($plan_row);
				break;

				case 'run':
					if ($this->request->is_set_post('batch_size'))
					{
						$this->config->set('portfolio_migrate_batch', min(100, max(1, $this->request->variable('batch_size', 10))));
					}
					$this->migrator->set_fallback_name($this->language->lang('PORTFOLIO_MIG_FALLBACK_ALBUM'));
					$result = $this->migrator->run_batch($users, $include_public, max(1, (int) $this->config['portfolio_migrate_batch']), $strip_exif);
					if (!$result['done'] && ($result['migrated'] || $result['skipped']))
					{
						$next = $this->u_action . '&amp;action=run' . $scope_params . '&amp;hash=' . generate_link_hash('pf_migrate_' . $scope . '_' . (int) $value);
						meta_refresh(1, str_replace('&amp;', '&', $next));
						$progress = [
							'done'		=> $result['total'] - $result['remaining'],
							'total'		=> $result['total'],
							'percent'	=> $result['total'] ? (int) floor(($result['total'] - $result['remaining']) * 100 / $result['total']) : 100,
							'u_next'	=> $next,
							'failed'	=> $this->failed_rows($result['failed']),
							'set_aside'	=> $result['set_aside'],
						];
					}
					else
					{
						$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_MIGRATED', false, [$this->scope_label($scope, $value), $result['total'] - $result['remaining']]);
						$aside = $result['set_aside'] ? '<br><br>' . $this->language->lang('ACP_PORTFOLIO_MIG_SET_ASIDE', $result['set_aside'], $this->u_action . '&amp;action=missing#pf-missing') : '';
						trigger_error($this->language->lang('ACP_PORTFOLIO_MIGRATE_DONE', $result['total'] - $result['remaining'], $result['total']) . $aside . adm_back_link($this->u_action));
					}
				break;

				case 'bbcode':
					if (!$this->migrator->install_bbcode())
					{
						trigger_error($this->language->lang('ACP_PORTFOLIO_BBCODE_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_BBCODE');
					trigger_error($this->language->lang('ACP_PORTFOLIO_BBCODE_DONE') . adm_back_link($this->u_action));
				break;

				case 'redirect':
					$this->config->set('portfolio_legacy_redirect', $this->request->variable('legacy_redirect', 0) ? 1 : 0);
					trigger_error($this->language->lang('PORTFOLIO_SETTINGS_SAVED') . adm_back_link($this->u_action));
				break;

				case 'rewrite_test':
					$stats = $this->migrator->rewrite_links(true);
					$message = $this->language->lang('ACP_PORTFOLIO_REWRITE_TEST_RESULT', $stats['posts'], $stats['sigs'], $stats['links']);
				break;

				case 'rewrite':
					if (!confirm_box(true))
					{
						confirm_box(false, $this->language->lang('ACP_PORTFOLIO_REWRITE_CONFIRM'), build_hidden_fields(['action' => 'rewrite']));
						redirect($this->u_action);
					}
					$stats = $this->migrator->rewrite_links(false);
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_REWRITE', false, [$stats['posts'], $stats['sigs']]);
					trigger_error($this->language->lang('ACP_PORTFOLIO_REWRITE_DONE', $stats['posts'], $stats['sigs'], $stats['links']) . adm_back_link($this->u_action));
				break;

				case 'restore_links':
					if (!confirm_box(true))
					{
						confirm_box(false, $this->language->lang('ACP_PORTFOLIO_RESTORE_LINKS_CONFIRM'), build_hidden_fields(['action' => 'restore_links']));
						redirect($this->u_action);
					}
					$count = $this->migrator->restore_links();
					trigger_error($this->language->lang('ACP_PORTFOLIO_RESTORE_LINKS_DONE', $count) . adm_back_link($this->u_action));
				break;

				case 'clear_incident':
					$this->migrator->clear_incident();
					redirect($this->u_action);
				break;

				case 'missing_selected':
					$ids = array_filter(array_map('intval', $this->request->variable('pf_ids', [0])));
					$op = $this->request->variable('pf_op', '');
					if (!$ids)
					{
						trigger_error($this->language->lang('ACP_PORTFOLIO_MIG_NONE_SELECTED') . adm_back_link($this->u_action . '&amp;action=missing'), E_USER_WARNING);
					}
					$back = adm_back_link($this->u_action . '&amp;action=missing#pf-missing');
					if ($op === 'retry')
					{
						$count = $this->migrator->retry_missing($ids);
						trigger_error($this->language->lang('ACP_PORTFOLIO_MIG_RETRY_DONE', $count) . $back);
					}
					if ($op === 'drop')
					{
						$count = $this->migrator->drop_missing($ids);
						$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_MIG_DROPPED', false, [$count]);
						trigger_error($this->language->lang('ACP_PORTFOLIO_MIG_DROP_DONE', $count) . $back);
					}
					if ($op === 'assign' && $this->departed)
					{
						$heir_id = $this->departed->heir_id();
						$heir_name = $this->departed->username_of($heir_id);
						$this->migrator->set_fallback_name($this->language->lang('PORTFOLIO_MIG_FALLBACK_ALBUM'));
						$result = $this->migrator->assign_missing($ids, $heir_id, $strip_exif);
						$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_MIG_ASSIGNED', false, [$result['done'], $heir_name]);
						$message = $this->language->lang('ACP_PORTFOLIO_MIG_ASSIGN_DONE', $result['done'], $heir_name);
						foreach ($this->failed_rows($result['failed']) as $row)
						{
							$message .= '<br>n° ' . $row['id'] . ' « ' . $row['title'] . ' » : ' . $row['reason'] . ($row['message'] !== '' ? ' — ' . $row['message'] : '');
						}
						if ($result['left'])
						{
							$message .= '<br>' . $this->language->lang('ACP_PORTFOLIO_MIG_ASSIGN_LEFT', $result['left']);
						}
						trigger_error($message . $back);
					}
					redirect($this->u_action . '&amp;action=missing');
				break;

				case 'retry_missing':
					$count = $this->migrator->retry_missing();
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_MIG_RETRY', false, [$count]);
					trigger_error($this->language->lang('ACP_PORTFOLIO_MIG_RETRY_DONE', $count) . adm_back_link($this->u_action));
				break;

				case 'undo':
					if (!confirm_box(true))
					{
						confirm_box(false, $this->language->lang('ACP_PORTFOLIO_UNDO_CONFIRM'), build_hidden_fields(['action' => 'undo']));
						redirect($this->u_action);
					}
					if ($this->migrator->count_rewritten_total())
					{
						trigger_error($this->language->lang('ACP_PORTFOLIO_UNDO_LINKS_FIRST') . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$stats = $this->migrator->undo();
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_MIGRATE_UNDONE', false, [$stats['photos'], $stats['albums']]);
					trigger_error($this->language->lang('ACP_PORTFOLIO_UNDO_DONE', $stats['photos'], $stats['albums']) . adm_back_link($this->u_action));
				break;
			}
		}

		add_form_key($form_key);

		$groups = [];
		foreach ($this->fetch_groups() as $group_id => $name)
		{
			$groups[] = ['id' => $group_id, 'name' => $name, 'selected' => $scope === 'group' && $group_id === (int) $value];
		}

		$username = '';
		if ($scope === 'user' && $value)
		{
			$this->presenter->load_users([(int) $value]);
			$username = $this->presenter->username($value, 'username');
		}

		$stats = $this->migrator->stats();
		$this->template->assign_vars([
			'PF_MIG'			=> $stats,
			'PF_PLAN'			=> $plan,
			'PF_MISSING'		=> $missing,
			'PF_HEIR'			=> $this->departed ? $this->departed->username_of($this->departed->heir_id()) : '',
			'U_PF_DEPARTED'		=> str_replace('mode=migrate', 'mode=departed', $this->u_action),
			'PF_INCIDENT'		=> $this->incident_row(),
			'PF_LIMITS'			=> $this->migrator->server_limits(),
			'PF_VERSION'		=> $this->config['portfolio_version'],
			'U_PF_MISSING'		=> $this->u_action . '&amp;action=missing#pf-missing',
			'PF_PROGRESS'		=> $progress,
			'PF_MESSAGE'		=> $message,
			'PF_GROUPS'			=> $groups,
			'PF_SCOPE'			=> $scope,
			'PF_SCOPE_LABEL'	=> $this->scope_label($scope, $value),
			'PF_USERNAME'		=> $username,
			'PF_INCLUDE_PUBLIC'	=> $include_public,
			'PF_STRIP_EXIF'		=> $strip_exif,
			'PF_REDIRECT'		=> (bool) $this->config['portfolio_legacy_redirect'],
			'PF_BATCH'			=> (int) $this->config['portfolio_migrate_batch'],
			'U_ACTION'			=> $this->u_action,
			'U_PF_BACKUP'		=> str_replace('mode=migrate', 'mode=backup', $this->u_action),
			'U_PF_FIND_USER'	=> append_sid($this->root_path . 'memberlist.' . $this->php_ext, 'mode=searchuser&amp;form=acp_portfolio_migrate&amp;field=username&amp;select_single=true'),
		]);
	}

	/**
	 * Nom à afficher pour l'auteur d'une photo de la galerie (v1.9.4) : le pseudo actuel du membre, en signalant
	 * l'ancien pseudo enregistré par phpBB Gallery s'il a changé, ou « membre parti » s'il n'existe plus.
	 */
	protected function member_label($user_id, $gallery_name)
	{
		$user_id = (int) $user_id;
		$gallery_name = (string) $gallery_name;
		if ($user_id <= ANONYMOUS)
		{
			return $gallery_name !== '' ? $gallery_name : $this->language->lang('GUEST');
		}
		if ($this->presenter->is_departed($user_id))
		{
			return $this->language->lang('ACP_PORTFOLIO_MIG_MEMBER_GONE', $gallery_name !== '' ? $gallery_name : '?', $user_id);
		}
		$current = $this->presenter->username($user_id, 'username');
		if ($gallery_name !== '' && utf8_clean_string($gallery_name) !== utf8_clean_string($current))
		{
			return $this->language->lang('ACP_PORTFOLIO_MIG_MEMBER_RENAMED', $current, $gallery_name);
		}

		return $current;
	}

	protected function failed_rows(array $failed)
	{
		$rows = [];
		foreach ($failed as $row)
		{
			$message = $row['message'];
			if ($message !== '' && $this->language->is_set($message))
			{
				$message = $this->language->lang($message);
			}
			$rows[] = [
				'id'		=> $row['image_id'],
				'title'		=> $row['title'],
				'reason'	=> $this->language->lang('ACP_PORTFOLIO_MIG_REASON_' . strtoupper($row['reason'])),
				'message'	=> $message,
			];
		}

		return $rows;
	}

	protected function incident_row()
	{
		$incident = $this->migrator->incident();
		if (!$incident)
		{
			return null;
		}
		$incident['time'] = $this->user->format_date((int) $incident['time']);
		$incident['peak'] = $incident['peak'] ? get_formatted_filesize((int) $incident['peak']) : '';
		$incident['killed'] = !empty($incident['killed']);

		return $incident;
	}

	protected function missing_rows()
	{
		$rows = [];
		foreach ($this->migrator->missing_list() as $row)
		{
			$name = $this->member_label($row['user_id'], $row['username']);
			$rows[] = [
				'id'		=> $row['image_id'],
				'title'		=> $row['title'] !== '' ? $row['title'] : '—',
				'user'		=> $name,
				'album'		=> $row['album'],
				'time'		=> $row['time'] ? $this->user->format_date($row['time'], 'd/m/Y') : '',
				'path'		=> $row['path'],
				'reason'	=> $this->language->lang('ACP_PORTFOLIO_MIG_REASON_' . strtoupper($row['reason'])),
				'code'		=> $row['reason'],
				'detail'	=> $row['width'] ? $row['width'] . ' × ' . $row['height'] . ' px · ' . get_formatted_filesize($row['size']) : '',
				'error'		=> $row['error'] !== '' && $this->language->is_set($row['error']) ? $this->language->lang($row['error']) : $row['error'],
			];
		}

		return $rows;
	}

	protected function scope_label($scope, $value)
	{
		if ($scope === 'group')
		{
			$groups = $this->fetch_groups();

			return $this->language->lang('ACP_PORTFOLIO_SCOPE_GROUP_LABEL', isset($groups[(int) $value]) ? $groups[(int) $value] : '#' . (int) $value);
		}
		if ($scope === 'user')
		{
			$this->presenter->load_users([(int) $value]);

			return $this->language->lang('ACP_PORTFOLIO_SCOPE_USER_LABEL', $this->presenter->username($value, 'username'));
		}

		return $this->language->lang('ACP_PORTFOLIO_SCOPE_ALL');
	}

	protected function fetch_groups()
	{
		$groups = [];
		$result = $this->db->sql_query('SELECT group_id, group_name, group_type FROM ' . GROUPS_TABLE . " WHERE group_name <> 'BOTS' ORDER BY group_type DESC, group_name ASC");
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[(int) $row['group_id']] = (int) $row['group_type'] === GROUP_SPECIAL ? $this->language->lang('G_' . $row['group_name']) : $row['group_name'];
		}
		$this->db->sql_freeresult($result);

		return $groups;
	}

	/* ------------------------------------------------------------------
	 * Sauvegarde, import et restauration (v1.4.0)
	 * ------------------------------------------------------------------ */

	public function backup()
	{
		$form_key = 'verturin_portfolio_acp_backup';
		$action = $this->request->variable('action', '');
		$zip_ok = \verturin\portfolio\service\archive::zip_available();

		// Téléchargement d'une sauvegarde (lien signé)
		if ($action === 'download')
		{
			$name = $this->request->variable('file', '');
			$path = $this->archive->backup_path($name);
			if (!$path || !check_link_hash($this->request->variable('hash', ''), 'pf_backup_' . $name))
			{
				trigger_error($this->language->lang('PORTFOLIO_BACKUP_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($path);
			$response->headers->set('Content-Type', 'application/zip');
			$response->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($path));
			$response->send();
			garbage_collection();
			exit_handler();
		}

		if ($action === 'delete')
		{
			$name = $this->request->variable('file', '');
			$path = $this->archive->backup_path($name);
			if (!$path)
			{
				trigger_error($this->language->lang('PORTFOLIO_BACKUP_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			if (confirm_box(true))
			{
				@unlink($path);
				trigger_error($this->language->lang('PORTFOLIO_BACKUP_DELETED', $name) . adm_back_link($this->u_action));
			}
			confirm_box(false, $this->language->lang('PORTFOLIO_BACKUP_DELETE_CONFIRM', $name), build_hidden_fields(['action' => 'delete', 'file' => $name]));
			redirect($this->u_action);
		}

		if ($action === 'create')
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			if (!$zip_ok)
			{
				trigger_error($this->language->lang('PORTFOLIO_ZIP_MISSING') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			try
			{
				$name = $this->archive->backup_full();
			}
			catch (\RuntimeException $e)
			{
				trigger_error($this->language->lang('PORTFOLIO_ARCHIVE_WRITE') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_BACKUP', false, [$name]);
			trigger_error($this->language->lang('PORTFOLIO_BACKUP_CREATED', $name, get_formatted_filesize(filesize($this->archive->backup_path($name)))) . adm_back_link($this->u_action));
		}

		if ($action === 'import')
		{
			$this->do_import($form_key);
		}

		add_form_key($form_key);

		$backups = [];
		foreach ($this->archive->list_backups() as $file)
		{
			$info = $zip_ok ? $this->archive->inspect($this->archive->backup_path($file['name'])) : [];
			$backups[] = [
				'name'		=> $file['name'],
				'size'		=> get_formatted_filesize($file['size']),
				'time'		=> $this->user->format_date($file['time']),
				'type'		=> is_array($info) ? $this->language->lang($info['type'] === 'full' ? 'PORTFOLIO_ARCHIVE_FULL' : 'PORTFOLIO_ARCHIVE_USER') : $this->language->lang('PORTFOLIO_ARCHIVE_INVALID_SHORT'),
				'counts'	=> is_array($info) && isset($info['counts']) ? $this->language->lang('PORTFOLIO_ARCHIVE_COUNTS', (int) $info['counts']['albums'], (int) $info['counts']['photos']) : '',
				'origin'	=> is_array($info) ? ($info['board_url'] . ' · ' . $info['created_by']) : '',
				'valid'		=> is_array($info),
				'u_download'	=> $this->u_action . '&amp;action=download&amp;file=' . urlencode($file['name']) . '&amp;hash=' . generate_link_hash('pf_backup_' . $file['name']),
				'u_delete'		=> $this->u_action . '&amp;action=delete&amp;file=' . urlencode($file['name']),
			];
		}

		$this->template->assign_vars([
			'PF_BACKUPS'		=> $backups,
			'PF_ZIP_OK'			=> $zip_ok,
			'PF_BACKUP_DIR'		=> 'files/portfolio/backup/',
			'PF_UPLOAD_MAX'		=> get_formatted_filesize(min($this->ini_bytes('upload_max_filesize'), $this->ini_bytes('post_max_size'))),
			'U_ACTION'			=> $this->u_action,
		]);
	}

	protected function do_import($form_key)
	{
		$confirmed = confirm_box(true);
		if (!$confirmed && !check_form_key($form_key))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$mode = $this->request->variable('mode_import', 'merge') === 'replace' ? 'replace' : 'merge';
		$name = $this->request->variable('server_file', '');

		// Archive envoyée depuis l'ordinateur : rangée dans le dossier des sauvegardes
		$upload = $this->request->file('archive');
		if (!$confirmed && !empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name']))
		{
			$name = 'import_' . gmdate('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.zip';
			if (!@move_uploaded_file($upload['tmp_name'], $this->archive->dir('backup') . $name))
			{
				trigger_error($this->language->lang('PORTFOLIO_ARCHIVE_WRITE') . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}

		$path = $this->archive->backup_path($name);
		if (!$path)
		{
			trigger_error($this->language->lang('PORTFOLIO_BACKUP_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$manifest = $this->archive->inspect($path);
		if (!is_array($manifest))
		{
			trigger_error($this->language->lang($manifest) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$default_owner = $this->find_user_id($this->request->variable('default_owner', '', true)) ?: (int) $this->user->data['user_id'];
		$force_owner = $this->find_user_id($this->request->variable('force_owner', '', true));
		$restore_config = (bool) $this->request->variable('restore_config', 0);

		if (!$confirmed)
		{
			confirm_box(false, $this->language->lang($mode === 'replace' ? 'PORTFOLIO_IMPORT_REPLACE_CONFIRM' : 'PORTFOLIO_IMPORT_MERGE_CONFIRM', $name, (int) $manifest['counts']['albums'], (int) $manifest['counts']['photos']), build_hidden_fields([
				'action'			=> 'import',
				'mode_import'		=> $mode,
				'server_file'		=> $name,
				'default_owner'		=> $this->request->variable('default_owner', '', true),
				'force_owner'		=> $this->request->variable('force_owner', '', true),
				'restore_config'	=> $restore_config ? 1 : 0,
			]));
			redirect($this->u_action);
		}

		$stats = $this->archive->import($path, $mode, $default_owner, $force_owner, $restore_config);
		if (isset($stats['error']))
		{
			trigger_error($this->language->lang($stats['error']) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, $mode === 'replace' ? 'LOG_PORTFOLIO_RESTORE' : 'LOG_PORTFOLIO_IMPORT', false, [$name, $stats['albums'], $stats['photos']]);
		trigger_error($this->language->lang('PORTFOLIO_IMPORT_DONE', $stats['albums'], $stats['photos'], $stats['comments'], $stats['ratings'], $stats['missing_files'], $stats['unmapped_users']) . adm_back_link($this->u_action));
	}

	protected function find_user_id($username)
	{
		$username = trim((string) $username);
		if ($username === '')
		{
			return 0;
		}
		$sql = 'SELECT user_id FROM ' . USERS_TABLE . " WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "' AND user_type <> " . USER_IGNORE;
		$result = $this->db->sql_query($sql);
		$user_id = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		if (!$user_id)
		{
			trigger_error($this->language->lang('PORTFOLIO_ERR_USER_UNKNOWN', $username) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		return $user_id;
	}

	protected function ini_bytes($key)
	{
		$value = trim((string) @ini_get($key));
		$num = (int) $value;
		switch (strtolower(substr($value, -1)))
		{
			case 'g':
				$num *= 1024;
			// no break
			case 'm':
				$num *= 1024;
			// no break
			case 'k':
				$num *= 1024;
		}

		return $num > 0 ? $num : PHP_INT_MAX;
	}

	/* ------------------------------------------------------------------
	 * Filigrane (v1.1.0)
	 * ------------------------------------------------------------------ */

	public function watermark()
	{
		$form_key = 'verturin_portfolio_acp_watermark';

		$fields = [
			'portfolio_wm_enabled'			=> ['bool'],
			'portfolio_wm_skip_members'		=> ['bool'],
			'portfolio_wm_thumbs'			=> ['bool'],
			'portfolio_wm_text_enabled'		=> ['bool'],
			'portfolio_wm_text'				=> ['text'],
			'portfolio_wm_position'			=> ['choice', \verturin\portfolio\service\watermark::POSITIONS],
			'portfolio_wm_text_size'		=> ['int', 1, 30],
			'portfolio_wm_text_color'		=> ['color'],
			'portfolio_wm_opacity'			=> ['int', 5, 100],
			'portfolio_wm_shadow'			=> ['bool'],
			'portfolio_wm_logo_enabled'		=> ['bool'],
			'portfolio_wm_logo_position'	=> ['choice', \verturin\portfolio\service\watermark::LOGO_POSITIONS],
			'portfolio_wm_logo_size'		=> ['int', 3, 60],
			'portfolio_wm_logo_opacity'		=> ['int', 5, 100],
			'portfolio_wm_cache_days'		=> ['int', 1, 365],
		];

		$messages = [];

		if ($this->request->is_set_post('submit') || $this->request->is_set_post('clear_cache') || $this->request->is_set_post('delete_logo'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if ($this->request->is_set_post('clear_cache'))
			{
				// Recalcul (v1.7.0) : cache vidé ET nouvelle révision dans l'adresse des images,
				// pour que les navigateurs abandonnent aussi leurs copies déjà téléchargées.
				$count = $this->watermark->clear_cache();
				$this->config->increment('portfolio_wm_revision', 1);
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_WATERMARK');
				trigger_error($this->language->lang('ACP_PORTFOLIO_WM_REBUILT', $count) . adm_back_link($this->u_action));
			}

			if ($this->request->is_set_post('delete_logo'))
			{
				@unlink($this->watermark->logo_path());
				$this->config->set('portfolio_wm_logo_enabled', 0);
				$this->config->increment('portfolio_wm_logo_version', 1);
				trigger_error($this->language->lang('ACP_PORTFOLIO_WM_LOGO_DELETED') . adm_back_link($this->u_action));
			}

			foreach ($fields as $key => $def)
			{
				switch ($def[0])
				{
					case 'bool':
						$value = $this->request->variable($key, 0) ? 1 : 0;
					break;
					case 'int':
						$value = min($def[2], max($def[1], $this->request->variable($key, (int) $this->config[$key])));
					break;
					case 'choice':
						$value = $this->request->variable($key, '');
						$value = in_array($value, $def[1], true) ? $value : $def[1][0];
					break;
					case 'color':
						$value = $this->request->variable($key, '#FFFFFF');
						$value = preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : '#FFFFFF';
					break;
					default:
						$value = truncate_string($this->request->variable($key, '', true), 120, 255, false);
				}
				$this->config->set($key, $value);
			}

			// Logo envoyé : vérifié, converti en PNG propre (aucune donnée annexe conservée)
			$file = $this->request->file('wm_logo');
			if (!empty($file['tmp_name']) && is_uploaded_file($file['tmp_name']))
			{
				$error = $this->save_logo($file['tmp_name']);
				if ($error)
				{
					trigger_error($this->language->lang($error) . adm_back_link($this->u_action), E_USER_WARNING);
				}
			}

			$this->watermark->clear_cache();
			$this->config->increment('portfolio_wm_revision', 1);
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_WATERMARK');
			trigger_error($this->language->lang('PORTFOLIO_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		add_form_key($form_key);

		$items = [];
		foreach ($fields as $key => $def)
		{
			$lang = strtoupper($key);
			$options = [];
			if ($def[0] === 'choice')
			{
				foreach ($def[1] as $choice)
				{
					$options[] = ['value' => $choice, 'label' => $this->language->lang('ACP_PORTFOLIO_WM_POS_' . strtoupper(str_replace('-', '_', $choice))), 'selected' => $choice === $this->config[$key]];
				}
			}
			$items[] = [
				'key'		=> $key,
				'type'		=> $def[0],
				'value'		=> $this->config[$key],
				'min'		=> isset($def[1]) && !is_array($def[1]) ? $def[1] : '',
				'max'		=> isset($def[2]) ? $def[2] : '',
				'options'	=> $options,
				'label'		=> $this->language->lang($lang),
				'explain'	=> $this->language->is_set($lang . '_EXPLAIN') ? $this->language->lang($lang . '_EXPLAIN') : '',
			];
		}

		// Aperçu en direct (image de démonstration filigranée avec le compte de l'administrateur)
		$preview = '';
		$tmp = $this->root_path . 'files/portfolio/wm/preview_' . (int) $this->user->data['user_id'] . '.jpg';
		if (is_dir(dirname($tmp)) || @mkdir(dirname($tmp), 0755, true))
		{
			$enabled = $this->config['portfolio_wm_enabled'];
			$this->config->offsetSet('portfolio_wm_enabled', 1);
			if ($this->watermark->preview($tmp))
			{
				$preview = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($tmp));
			}
			@unlink($tmp);
			$this->config->offsetSet('portfolio_wm_enabled', $enabled);
		}

		$logo = $this->watermark->logo_path();

		$this->template->assign_vars([
			'PF_ITEMS'			=> $items,
			'PF_PREVIEW'		=> $preview,
			'PF_HAS_LOGO'		=> is_file($logo),
			'PF_LOGO'			=> is_file($logo) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo)) : '',
			'PF_TTF'			=> function_exists('imagettftext'),
			'PF_SAMPLE_TEXT'	=> $this->watermark->text_for_viewer(['photo_id' => 0]),
			'U_ACTION'			=> $this->u_action,
		]);
	}

	/**
	 * @return string Clé d'erreur ou chaîne vide
	 */
	protected function save_logo($tmp)
	{
		$info = @getimagesize($tmp);
		if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true) || $info[0] > 4000 || $info[1] > 4000)
		{
			return 'ACP_PORTFOLIO_WM_LOGO_INVALID';
		}

		switch ($info[2])
		{
			case IMAGETYPE_PNG:
				$img = @imagecreatefrompng($tmp);
			break;
			case IMAGETYPE_GIF:
				$img = @imagecreatefromgif($tmp);
			break;
			default:
				$img = @imagecreatefromjpeg($tmp);
		}
		if (!$img)
		{
			return 'ACP_PORTFOLIO_WM_LOGO_INVALID';
		}
		if (!imageistruecolor($img))
		{
			imagepalettetotruecolor($img);
		}
		imagealphablending($img, false);
		imagesavealpha($img, true);

		$path = $this->watermark->logo_path();
		if (!is_dir(dirname($path)))
		{
			@mkdir(dirname($path), 0755, true);
		}
		if (!@imagepng($img, $path, 6))
		{
			return 'ACP_PORTFOLIO_NOT_WRITABLE';
		}

		$this->config->set('portfolio_wm_logo_enabled', 1);
		$this->config->increment('portfolio_wm_logo_version', 1);

		return '';
	}

	/* ------------------------------------------------------------------
	 * Albums et droits par groupe
	 * ------------------------------------------------------------------ */

	public function albums()
	{
		$action = $this->request->variable('action', '');
		$album_id = $this->request->variable('album_id', 0);
		$album = $album_id ? $this->albums->get($album_id) : null;

		if ($action && !$album)
		{
			trigger_error($this->language->lang('PORTFOLIO_ALBUM_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		switch ($action)
		{
			case 'groups':
				$this->album_groups($album);

				return;

			case 'move_up':
			case 'move_down':
			case 'lock':
			case 'protect':
				if (!check_link_hash($this->request->variable('hash', ''), 'pf_acp_album_' . $album_id))
				{
					trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				}
				if ($action === 'lock')
				{
					$this->albums->update($album_id, ['album_locked' => !$album['album_locked']]);
				}
				else if ($action === 'protect')
				{
					$this->albums->update($album_id, ['album_protected' => empty($album['album_protected'])]);
				}
				else
				{
					$this->albums->move_order($album_id, $action === 'move_up' ? 'up' : 'down');
				}
				redirect($this->u_action);
			break;

			case 'delete':
				$active = $this->albums->count_active_photos($album_id);
				if ($active || $this->albums->get_children($album_id))
				{
					trigger_error($this->language->lang('PORTFOLIO_ALBUM_NOT_EMPTY_ACP', $album['album_name'], $active, count($this->albums->get_children($album_id))) . adm_back_link($this->u_action), E_USER_WARNING);
				}
				if (confirm_box(true))
				{
					$this->albums->delete($album_id);
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_ALBUM_DELETED', false, [$album['album_name']]);
					trigger_error($this->language->lang('PORTFOLIO_ALBUM_DELETED', $album['album_name']) . adm_back_link($this->u_action));
				}
				confirm_box(false, $this->language->lang('PORTFOLIO_DELETE_ALBUM_CONFIRM', $album['album_name']), build_hidden_fields([
					'action'	=> 'delete',
					'album_id'	=> $album_id,
				]));
				redirect($this->u_action);
			break;
		}

		$filter = $this->request->variable('filter', 'all');
		$owners = [];
		foreach ($this->albums->get_all() as $row)
		{
			$owners[] = (int) $row['owner_id'];
		}
		$this->presenter->load_users($owners);

		$rows = [];
		foreach ($this->albums->flat_tree() as $id => $node)
		{
			$row = $node['album'];
			$is_public = !(int) $row['owner_id'];
			if (($filter === 'public' && !$is_public) || ($filter === 'members' && $is_public))
			{
				continue;
			}

			$hash = generate_link_hash('pf_acp_album_' . $id);
			$groups = $this->albums->get_groups($id);
			$rows[] = [
				'id'			=> $id,
				'name'			=> $row['album_name'],
				'depth'			=> $node['depth'],
				'owner'			=> $is_public ? '' : $this->presenter->username($row['owner_id']),
				'is_public'		=> $is_public,
				'visibility'	=> $this->language->lang(['PORTFOLIO_VIS_ALL', 'PORTFOLIO_VIS_REGISTERED', 'PORTFOLIO_VIS_GROUPS', 'PORTFOLIO_VIS_PRIVATE', 'PORTFOLIO_VIS_FRIENDS'][(int) $row['album_visibility']] ?? 'PORTFOLIO_VIS_PRIVATE'),
				'photos'		=> (int) $row['album_photos'],
				'locked'		=> (bool) $row['album_locked'],
				'protected'		=> !empty($row['album_protected']),
				'u_protect'		=> $this->u_action . '&amp;action=protect&amp;album_id=' . $id . '&amp;hash=' . $hash,
				'group_rules'	=> count($groups),
				'u_view'		=> $this->presenter->route_abs('album', ['album_id' => $id]),
				'u_edit'		=> $this->presenter->route_abs('album_edit', ['album_id' => $id]),
				'u_members'		=> $this->presenter->route_abs('album_members', ['album_id' => $id]),
				'u_groups'		=> $this->u_action . '&amp;action=groups&amp;album_id=' . $id,
				'u_up'			=> $this->u_action . '&amp;action=move_up&amp;album_id=' . $id . '&amp;hash=' . $hash,
				'u_down'		=> $this->u_action . '&amp;action=move_down&amp;album_id=' . $id . '&amp;hash=' . $hash,
				'u_lock'		=> $this->u_action . '&amp;action=lock&amp;album_id=' . $id . '&amp;hash=' . $hash,
				'u_delete'		=> $this->u_action . '&amp;action=delete&amp;album_id=' . $id,
			];
		}

		$this->template->assign_vars([
			'PF_ROWS'			=> $rows,
			'PF_FILTER'			=> $filter,
			'U_ACTION'			=> $this->u_action,
			'U_PF_NEW_PUBLIC'	=> $this->presenter->route_abs('album_create', ['public' => 1]),
		]);
	}

	protected function album_groups(array $album)
	{
		$album_id = (int) $album['album_id'];
		$form_key = 'verturin_portfolio_acp_groups';
		$flags = ['can_view', 'can_upload', 'can_rate', 'can_comment'];

		$groups = [];
		$sql = 'SELECT group_id, group_name, group_type FROM ' . GROUPS_TABLE . ' ORDER BY group_type DESC, group_name ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[(int) $row['group_id']] = (int) $row['group_type'] === GROUP_SPECIAL ? $this->language->lang('G_' . $row['group_name']) : $row['group_name'];
		}
		$this->db->sql_freeresult($result);

		if ($this->request->is_set_post('submit') || $this->request->is_set_post('clear'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$rows = [];
			if ($this->request->is_set_post('submit'))
			{
				$enabled = array_map('intval', $this->request->variable('enabled', [0]));
				foreach ($enabled as $group_id)
				{
					if (!isset($groups[$group_id]))
					{
						continue;
					}
					foreach ($flags as $flag)
					{
						$rows[$group_id][$flag] = $this->request->variable($flag . '_' . $group_id, 0) ? 1 : 0;
					}
				}
			}

			$targets = [$album_id];
			if ($this->request->variable('recursive', 0))
			{
				$targets = array_merge($targets, $this->albums->get_descendant_ids($album_id));
			}
			foreach ($targets as $target)
			{
				$this->albums->set_groups($target, $rows);
			}

			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_ALBUM_GROUPS', false, [$album['album_name']]);
			trigger_error($this->language->lang('PORTFOLIO_GROUPS_SAVED') . adm_back_link($this->u_action));
		}

		add_form_key($form_key);
		$current = $this->albums->get_groups($album_id);

		$matrix = [];
		foreach ($groups as $group_id => $name)
		{
			$matrix[] = [
				'id'			=> $group_id,
				'name'			=> $name,
				'enabled'		=> isset($current[$group_id]),
				'can_view'		=> isset($current[$group_id]) ? (bool) $current[$group_id]['can_view'] : true,
				'can_upload'	=> isset($current[$group_id]) ? (bool) $current[$group_id]['can_upload'] : false,
				'can_rate'		=> isset($current[$group_id]) ? (bool) $current[$group_id]['can_rate'] : true,
				'can_comment'	=> isset($current[$group_id]) ? (bool) $current[$group_id]['can_comment'] : true,
			];
		}

		$this->template->assign_vars([
			'S_PF_GROUPS'		=> true,
			'PF_ALBUM_NAME'		=> $album['album_name'],
			'PF_ALBUM_VIS'		=> (int) $album['album_visibility'] === access::VIS_GROUPS,
			'PF_MATRIX'			=> $matrix,
			'PF_HAS_CHILDREN'	=> (bool) $this->albums->get_children($album_id),
			'U_ACTION'			=> $this->u_action . '&amp;action=groups&amp;album_id=' . $album_id,
			'U_BACK'			=> $this->u_action,
		]);
	}

	/* ------------------------------------------------------------------
	 * Corbeille : validation définitive ou restauration
	 * ------------------------------------------------------------------ */

	public function trash()
	{
		$form_key = 'verturin_portfolio_acp_trash';

		$action = $this->request->variable('action', '');
		$ids = array_filter(array_map('intval', $this->request->variable('photo_ids', [0])));

		if ($action === 'purge' || $action === 'purge_older')
		{
			if ($action === 'purge_older')
			{
				$days = max(0, $this->request->variable('days', 30));
				$ids = $this->photos->trash_older_than($days, 5000);
			}

			if (!$ids)
			{
				trigger_error($this->language->lang('PORTFOLIO_NOTHING_SELECTED') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if (confirm_box(true))
			{
				$count = $this->photos->purge($ids);
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PORTFOLIO_PURGED', false, [$count]);
				trigger_error($this->language->lang('PORTFOLIO_PURGED', $count) . adm_back_link($this->u_action));
			}

			confirm_box(false, $this->language->lang('PORTFOLIO_PURGE_CONFIRM', count($ids)), build_hidden_fields([
				'action'	=> 'purge',
				'photo_ids'	=> $ids,
			]));
			redirect($this->u_action);
		}

		if ($action === 'restore')
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$restored = $this->photos->restore($ids, $this->request->variable('target_album', 0));
			$skipped = count($ids) - count($restored);
			trigger_error($this->language->lang('PORTFOLIO_RESTORED_ACP', count($restored), $skipped) . adm_back_link($this->u_action));
		}

		add_form_key($form_key);

		$per_page = 40;
		$start = $this->request->variable('start', 0);
		$user_filter = $this->request->variable('deleted_by', 0);
		list($rows, $total) = $this->photos->list_by_status(photo_manager::STATUS_TRASH, $start, $per_page, $user_filter ? 'deleted_by = ' . $user_filter : '');

		$user_ids = [];
		foreach ($rows as $row)
		{
			$user_ids[] = (int) $row['user_id'];
			$user_ids[] = (int) $row['deleted_by'];
		}

		// Suppressions des dernières 24 h par membre (repérage des « boulettes »)
		$recent = [];
		$threshold = (int) $this->config['portfolio_alert_threshold'];
		$sql = 'SELECT deleted_by, COUNT(photo_id) AS total, MAX(deleted_time) AS last_time
			FROM ' . $this->photos_table . '
			WHERE photo_status = ' . photo_manager::STATUS_TRASH . '
				AND deleted_time > ' . (time() - 86400) . '
			GROUP BY deleted_by
			ORDER BY total DESC';
		$result = $this->db->sql_query_limit($sql, 20);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$recent[] = $row;
			$user_ids[] = (int) $row['deleted_by'];
		}
		$this->db->sql_freeresult($result);
		$this->presenter->load_users($user_ids);

		$items = [];
		foreach ($rows as $row)
		{
			$album = $this->albums->get($row['album_id']);
			$items[] = [
				'id'			=> (int) $row['photo_id'],
				'title'			=> $row['photo_title'],
				'thumb'			=> $this->presenter->route_abs('file', ['photo_id' => (int) $row['photo_id'], 'size' => 'thumb', 'v' => substr($row['photo_hash'], 0, 8)]),
				'medium'		=> $this->presenter->route_abs('file', ['photo_id' => (int) $row['photo_id'], 'size' => 'medium', 'v' => substr($row['photo_hash'], 0, 8)]),
				'album'			=> $album ? $album['album_name'] : '',
				'album_missing'	=> !$album,
				'owner'			=> $this->presenter->username($row['user_id']),
				'deleted_by'	=> $this->presenter->username($row['deleted_by']),
				'deleted_time'	=> $this->user->format_date((int) $row['deleted_time']),
				'reason'		=> $row['deleted_reason'],
				'size'			=> get_formatted_filesize((int) $row['photo_filesize']),
			];
		}

		$recent_list = [];
		foreach ($recent as $row)
		{
			$recent_list[] = [
				'user'		=> $this->presenter->username($row['deleted_by']),
				'total'		=> (int) $row['total'],
				'last'		=> $this->user->format_date((int) $row['last_time']),
				'alert'		=> $threshold && (int) $row['total'] >= $threshold,
				'u_filter'	=> $this->u_action . '&amp;deleted_by=' . (int) $row['deleted_by'],
			];
		}

		$targets = [];
		foreach ($this->albums->flat_tree() as $id => $node)
		{
			$targets[] = ['id' => $id, 'name' => str_repeat('— ', $node['depth']) . $node['album']['album_name']];
		}

		$pagination = [];
		if ($total > $per_page)
		{
			for ($i = 0; $i * $per_page < $total; $i++)
			{
				$pagination[] = [
					'num'		=> $i + 1,
					'current'	=> $i * $per_page === (int) $start,
					'url'		=> $this->u_action . '&amp;start=' . ($i * $per_page) . ($user_filter ? '&amp;deleted_by=' . $user_filter : ''),
				];
			}
		}

		$this->template->assign_vars([
			'PF_ITEMS'		=> $items,
			'PF_TOTAL'		=> $total,
			'PF_RECENT'		=> $recent_list,
			'PF_THRESHOLD'	=> $threshold,
			'PF_TARGETS'	=> $targets,
			'PF_PAGINATION'	=> $pagination,
			'PF_FILTERED'	=> (bool) $user_filter,
			'U_ACTION'		=> $this->u_action . ($user_filter ? '&amp;deleted_by=' . $user_filter : ''),
			'U_PF_ALL'		=> $this->u_action,
		]);
	}
}
