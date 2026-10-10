<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.9.0 : membres partis.
 * - table portfolio_departed : nom conservé, repreneur, album de rangement ;
 * - réglages portfolio_heir_id (0 = premier fondateur) et portfolio_departed_mode ;
 * - page ACP « Membres partis ».
 */
class v190_departed extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.9.0', '>=');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v186_autowire'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'portfolio_departed' => [
					'COLUMNS' => [
						'user_id'		=> ['UINT', 0],
						'username'		=> ['VCHAR_UNI', ''],
						'heir_id'		=> ['UINT', 0],
						'album_id'		=> ['UINT', 0],
						'departed_time'	=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> 'user_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [$this->table_prefix . 'portfolio_departed'],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'insert_settings']]],
			['module.add', ['acp', 'ACP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\acp\main_module',
				'modes'				=> ['departed'],
			]]],
			['custom', [[$this, 'set_version']]],
		];
	}

	public function insert_settings()
	{
		foreach (['portfolio_heir_id' => '0', 'portfolio_departed_mode' => 'transfer'] as $name => $value)
		{
			$this->db->sql_query('DELETE FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = '" . $name . "'");
			$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'portfolio_config ' . $this->db->sql_build_array('INSERT', [
				'config_name'	=> $name,
				'config_value'	=> $value,
			]));
		}
	}

	public function set_version()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.9.0' WHERE config_name = 'portfolio_version'");
	}
}
