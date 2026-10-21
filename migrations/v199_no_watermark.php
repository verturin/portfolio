<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.9.9 : filigrane désactivable par album (l'album et tous ses sous-albums).
 */
class v199_no_watermark extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'portfolio_albums', 'album_no_watermark');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v198_bulk_albums'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'portfolio_albums' => [
					'album_no_watermark'	=> ['BOOL', 0, 'after' => 'album_protected'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns'	=> [$this->table_prefix . 'portfolio_albums' => ['album_no_watermark']],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'set_version']]],
		];
	}

	public function set_version()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.9.9' WHERE config_name = 'portfolio_version'");
	}
}
