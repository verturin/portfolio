<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.4.0 : export ZIP des membres (lien unique), sauvegarde complète, import / restauration.
 */
class v140_archive extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'portfolio_exports');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v130_privacy'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'portfolio_exports' => [
					'COLUMNS' => [
						'export_id'			=> ['UINT', null, 'auto_increment'],
						'user_id'			=> ['UINT', 0],
						'album_id'			=> ['UINT', 0],
						'export_token'		=> ['VCHAR:32', ''],
						'export_file'		=> ['VCHAR:100', ''],
						'export_size'		=> ['BINT', 0],
						'export_photos'		=> ['UINT', 0],
						'export_time'		=> ['TIMESTAMP', 0],
						'export_expire'		=> ['TIMESTAMP', 0],
						'export_downloads'	=> ['UINT', 0],
					],
					'PRIMARY_KEY'	=> 'export_id',
					'KEYS'			=> [
						'export_token'	=> ['UNIQUE', 'export_token'],
						'user_id'		=> ['INDEX', 'user_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [$this->table_prefix . 'portfolio_exports'],
		];
	}

	public function update_data()
	{
		$data = [
			['custom', [[$this, 'insert_settings']]],
			['permission.add', ['u_portfolio_export']],
			['permission.permission_set', ['REGISTERED', 'u_portfolio_export', 'group']],
			['module.add', ['acp', 'ACP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\acp\main_module',
				'modes'				=> ['backup'],
			]]],
		];

		foreach (['ROLE_USER_FULL', 'ROLE_USER_STANDARD'] as $role)
		{
			if ($this->role_exists($role))
			{
				$data[] = ['permission.permission_set', [$role, 'u_portfolio_export']];
			}
		}

		return $data;
	}

	public function insert_settings()
	{
		$rows = [];
		foreach (['portfolio_export_ttl' => 24, 'portfolio_export_daily' => 5, 'portfolio_export_gc' => 0] as $name => $value)
		{
			$rows[] = ['config_name' => $name, 'config_value' => (string) $value];
		}
		$this->db->sql_multi_insert($this->table_prefix . 'portfolio_config', $rows);
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.4.0' WHERE config_name = 'portfolio_version'");
	}

	protected function role_exists($role)
	{
		$sql = 'SELECT role_id FROM ' . ACL_ROLES_TABLE . " WHERE role_name = '" . $this->db->sql_escape($role) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$role_id = (int) $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		return $role_id > 0;
	}
}
