<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.10.4 : message privé aux administrateurs, modérateurs, groupes ou membres choisis
 * quand une photo attend la validation.
 */
class v1104_pending_pm extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.10.4', '>=');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v1103_group_limits'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'add_settings']]],
		];
	}

	public function add_settings()
	{
		$table = $this->table_prefix . 'portfolio_config';
		$settings = [
			'portfolio_pending_pm'			=> '1',
			'portfolio_pending_pm_admins'	=> '1',
			'portfolio_pending_pm_mods'		=> '0',
			'portfolio_pending_pm_groups'	=> '',
			'portfolio_pending_pm_users'	=> '',
			'portfolio_pending_pm_from'		=> '0',
		];
		foreach ($settings as $name => $value)
		{
			$sql = 'SELECT config_name FROM ' . $table . " WHERE config_name = '" . $this->db->sql_escape($name) . "'";
			$result = $this->db->sql_query($sql);
			$exists = (bool) $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			if (!$exists)
			{
				$this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', [
					'config_name'	=> $name,
					'config_value'	=> $value,
				]));
			}
		}
		$this->db->sql_query('UPDATE ' . $table . " SET config_value = '1.10.4' WHERE config_name = 'portfolio_version'");
	}
}
