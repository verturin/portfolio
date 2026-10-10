<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.9.2 : bascule phpBB Gallery par lots limités en temps, raison de chaque échec conservée.
 */
class v192_migrate_batches extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'portfolio_legacy', 'legacy_note');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v191_move_scope'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'portfolio_legacy' => [
					'legacy_note'	=> ['VCHAR:20', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'portfolio_legacy' => ['legacy_note'],
			],
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
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.9.2' WHERE config_name = 'portfolio_version'");
	}
}
