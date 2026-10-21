<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.9.10 : fréquence des répétitions du filigrane en mosaïque.
 */
class v1910_tile_density extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_wm_tile_density'";
		$result = $this->db->sql_query($sql);
		$value = $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $value !== false;
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v199_no_watermark'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'add_setting']]],
		];
	}

	public function add_setting()
	{
		$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'portfolio_config ' . $this->db->sql_build_array('INSERT', [
			'config_name'	=> 'portfolio_wm_tile_density',
			'config_value'	=> '5',
		]));
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.9.10' WHERE config_name = 'portfolio_version'");
	}
}
