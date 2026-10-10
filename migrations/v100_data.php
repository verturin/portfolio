<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

class v100_data extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$installed = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $installed;
	}

	/**
	 * Valeurs par défaut des réglages (table portfolio_config).
	 */
	public static function default_settings()
	{
		return [
			'portfolio_version'			=> '1.0.0',
			'portfolio_navbar'			=> 1,
			'portfolio_per_page'			=> 24,
			'portfolio_comments_per_page'	=> 20,
			'portfolio_index_recent'		=> 12,
			'portfolio_max_filesize'		=> 10240,
			'portfolio_max_width'			=> 4096,
			'portfolio_max_height'		=> 4096,
			'portfolio_medium_size'		=> 1600,
			'portfolio_thumb_size'		=> 480,
			'portfolio_jpeg_quality'		=> 88,
			'portfolio_allowed_ext'		=> 'jpg,jpeg,png,gif,webp',
			'portfolio_strip_exif'		=> 1,
			'portfolio_user_quota'		=> 0,
			'portfolio_user_max_albums'	=> 0,
			'portfolio_max_depth'			=> 5,
			'portfolio_rate_own'			=> 0,
			'portfolio_alert_threshold'	=> 20,
			'portfolio_alert_window'		=> 600,
			'portfolio_alert_block'		=> 0,
			'portfolio_trash_autopurge'	=> 0,
			'portfolio_purge_last_gc'	=> 0,
		];
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v100_schema'];
	}

	public function update_data()
	{
		$data = [
			['custom', [[$this, 'insert_settings']]],

			['permission.add', ['u_portfolio_view']],
			['permission.add', ['u_portfolio_create_album']],
			['permission.add', ['u_portfolio_upload']],
			['permission.add', ['u_portfolio_noapprove']],
			['permission.add', ['u_portfolio_rate']],
			['permission.add', ['u_portfolio_comment']],
			['permission.add', ['u_portfolio_share']],
			['permission.add', ['u_portfolio_restore']],
			['permission.add', ['u_portfolio_download']],
			['permission.add', ['u_portfolio_unlimited']],
			['permission.add', ['m_portfolio_approve']],
			['permission.add', ['m_portfolio_edit']],
			['permission.add', ['m_portfolio_delete']],
			['permission.add', ['m_portfolio_comments']],
			['permission.add', ['a_portfolio_manage']],
			['permission.add', ['a_portfolio_trash']],

			// Invités : consultation seule
			['permission.permission_set', ['GUESTS', 'u_portfolio_view', 'group']],

			// Membres inscrits : usage standard
			['permission.permission_set', ['REGISTERED', [
				'u_portfolio_view', 'u_portfolio_create_album', 'u_portfolio_upload', 'u_portfolio_noapprove',
				'u_portfolio_rate', 'u_portfolio_comment', 'u_portfolio_share', 'u_portfolio_restore',
				'u_portfolio_download',
			], 'group']],

			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_PORTFOLIO_TITLE']],
			['module.add', ['acp', 'ACP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\acp\main_module',
				'modes'				=> ['settings', 'albums', 'trash'],
			]]],
		];

		// Rôles standards (uniquement s'ils existent encore sur le forum)
		$roles = [
			'ROLE_USER_FULL'		=> ['u_portfolio_view', 'u_portfolio_create_album', 'u_portfolio_upload', 'u_portfolio_noapprove', 'u_portfolio_rate', 'u_portfolio_comment', 'u_portfolio_share', 'u_portfolio_restore', 'u_portfolio_download', 'u_portfolio_unlimited'],
			'ROLE_USER_STANDARD'	=> ['u_portfolio_view', 'u_portfolio_create_album', 'u_portfolio_upload', 'u_portfolio_noapprove', 'u_portfolio_rate', 'u_portfolio_comment', 'u_portfolio_share', 'u_portfolio_restore', 'u_portfolio_download'],
			'ROLE_USER_LIMITED'		=> ['u_portfolio_view', 'u_portfolio_rate', 'u_portfolio_comment'],
			'ROLE_USER_NEW_MEMBER'	=> ['u_portfolio_view', 'u_portfolio_upload', 'u_portfolio_rate', 'u_portfolio_comment', 'u_portfolio_restore'],
			'ROLE_MOD_FULL'			=> ['m_portfolio_approve', 'm_portfolio_edit', 'm_portfolio_delete', 'm_portfolio_comments'],
			'ROLE_MOD_STANDARD'		=> ['m_portfolio_approve', 'm_portfolio_edit', 'm_portfolio_delete', 'm_portfolio_comments'],
			'ROLE_ADMIN_FULL'		=> ['a_portfolio_manage', 'a_portfolio_trash'],
			'ROLE_ADMIN_STANDARD'	=> ['a_portfolio_manage', 'a_portfolio_trash'],
		];

		foreach ($roles as $role => $permissions)
		{
			if ($this->role_exists($role))
			{
				$data[] = ['permission.permission_set', [$role, $permissions]];
			}
		}

		return $data;
	}

	public function insert_settings()
	{
		$rows = [];
		foreach (static::default_settings() as $name => $value)
		{
			$rows[] = ['config_name' => $name, 'config_value' => (string) $value];
		}
		$this->db->sql_multi_insert($this->table_prefix . 'portfolio_config', $rows);
	}

	protected function role_exists($role)
	{
		$sql = 'SELECT role_id
			FROM ' . ACL_ROLES_TABLE . "
			WHERE role_name = '" . $this->db->sql_escape($role) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$role_id = (int) $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		return $role_id > 0;
	}
}
