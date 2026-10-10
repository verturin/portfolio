<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Forums-liens des pages Portfolio (v1.8.0).
 *
 * Chaque page peut être rattachée à un forum de type « lien », créé dans le forum ou sous-forum choisi.
 * Les droits de groupe de ce forum (Lister, Lire) décident qui voit le lien et qui peut ouvrir la page :
 * l'administrateur les règle comme pour n'importe quel forum.
 */
class forum_link
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\cache\driver\driver_interface $cache, \phpbb\auth\auth $auth, \phpbb\language\language $language, $root_path, $php_ext)
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->auth = $auth;
		$this->language = $language;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Crée le forum-lien. Les droits sont copiés depuis le forum parent.
	 *
	 * @return int|string Identifiant du forum créé, ou message d'erreur
	 */
	public function create($parent_id, $name, $desc, $url)
	{
		$this->load_acp_forums();
		$this->language->add_lang('acp/forums');

		$forum_data = [
			'parent_id'				=> (int) $parent_id,
			'forum_type'			=> FORUM_LINK,
			'type_action'			=> '',
			'forum_status'			=> ITEM_UNLOCKED,
			'forum_parents'			=> '',
			'forum_name'			=> $name,
			'forum_link'			=> $url,
			'forum_link_track'		=> false,
			'forum_desc'			=> $desc,
			'forum_desc_uid'		=> '',
			'forum_desc_options'	=> 7,
			'forum_desc_bitfield'	=> '',
			'forum_rules'			=> '',
			'forum_rules_uid'		=> '',
			'forum_rules_options'	=> 7,
			'forum_rules_bitfield'	=> '',
			'forum_rules_link'		=> '',
			'forum_image'			=> '',
			'forum_style'			=> 0,
			'display_subforum_list'	=> true,
			'display_subforum_limit' => false,
			'display_on_index'		=> true,
			'forum_topics_per_page'	=> 0,
			'enable_indexing'		=> true,
			'enable_icons'			=> false,
			'enable_prune'			=> false,
			'enable_post_review'	=> true,
			'enable_quick_reply'	=> false,
			'enable_shadow_prune'	=> false,
			'prune_days'			=> 7,
			'prune_viewed'			=> 7,
			'prune_freq'			=> 1,
			'prune_old_polls'		=> false,
			'prune_announce'		=> false,
			'prune_sticky'			=> false,
			'prune_shadow_days'		=> 7,
			'prune_shadow_freq'		=> 1,
			'forum_password'		=> '',
			'forum_password_confirm' => '',
			'forum_password_unset'	=> false,
			'forum_options'			=> 0,
			'show_active'			=> false,
		];

		$acp = new \acp_forums();
		$errors = $acp->update_forum_data($forum_data);
		if ($errors)
		{
			return implode('<br>', $errors);
		}

		$forum_id = (int) $forum_data['forum_id'];
		if ($parent_id)
		{
			copy_forum_permissions((int) $parent_id, $forum_id, false);
		}

		$this->refresh();

		return $forum_id;
	}

	public function update($forum_id, $name, $desc, $url)
	{
		$this->db->sql_query('UPDATE ' . FORUMS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', [
			'forum_name'	=> $name,
			'forum_desc'	=> $desc,
			'forum_link'	=> $url,
		]) . ' WHERE forum_id = ' . (int) $forum_id . ' AND forum_type = ' . FORUM_LINK);
		$this->refresh();
	}

	public function delete($forum_id)
	{
		if (!$this->exists($forum_id))
		{
			return;
		}
		$this->load_acp_forums();
		$this->language->add_lang('acp/forums');
		$acp = new \acp_forums();
		$acp->delete_forum((int) $forum_id);
		$this->refresh();
	}

	public function exists($forum_id)
	{
		if (!$forum_id)
		{
			return false;
		}
		$result = $this->db->sql_query('SELECT forum_id FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int) $forum_id);
		$found = (bool) $this->db->sql_fetchfield('forum_id');
		$this->db->sql_freeresult($result);

		return $found;
	}

	/**
	 * Le visiteur peut-il ouvrir une page rattachée à ce forum ? (droit « Lire » du forum)
	 */
	public function can_read($forum_id)
	{
		return !$forum_id || ($this->exists($forum_id) && $this->auth->acl_get('f_read', (int) $forum_id));
	}

	/**
	 * Liste des forums pour le choix de l'emplacement (catégories et forums, pas les liens).
	 */
	public function select_options($selected = 0)
	{
		if (!function_exists('make_forum_select'))
		{
			include($this->root_path . 'includes/functions_admin.' . $this->php_ext);
		}

		return make_forum_select((int) $selected, false, true, false, false, false, true);
	}

	protected function refresh()
	{
		$this->cache->destroy('sql', FORUMS_TABLE);
		$this->auth->acl_clear_prefetch();
		if (function_exists('phpbb_cache_moderators'))
		{
			global $phpbb_container;
			phpbb_cache_moderators($this->db, $phpbb_container->get('cache'), $this->auth);
		}
	}

	protected function load_acp_forums()
	{
		if (!function_exists('copy_forum_permissions'))
		{
			include($this->root_path . 'includes/functions_admin.' . $this->php_ext);
		}
		if (!class_exists('acp_forums'))
		{
			include($this->root_path . 'includes/acp/acp_forums.' . $this->php_ext);
		}
	}
}
