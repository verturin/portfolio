<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.10.3 : quotas de photos et d'albums par groupe, validation obligatoire par groupe
 * (y compris dans les albums du membre). Nouvelle page ACP « Quotas et validation ».
 */
class v1103_group_limits extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'portfolio_group_limits');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v1102_wm_optout_pm'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'portfolio_group_limits' => [
					'COLUMNS' => [
						'group_id'			=> ['UINT', 0],
						'limit_photos'		=> ['INT:11', -1],	// -1 = réglage général, 0 = illimité
						'limit_albums'		=> ['INT:11', -1],
						'limit_approval'	=> ['BOOL', 0],		// validation obligatoire, même dans ses albums
					],
					'PRIMARY_KEY'	=> 'group_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables'	=> [$this->table_prefix . 'portfolio_group_limits'],
		];
	}

	public function update_data()
	{
		return [
			['module.add', ['acp', 'ACP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\acp\main_module',
				'modes'				=> ['limits'],
			]]],
			['custom', [[$this, 'set_version']]],
		];
	}

	public function set_version()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.10.3' WHERE config_name = 'portfolio_version'");
	}
}
