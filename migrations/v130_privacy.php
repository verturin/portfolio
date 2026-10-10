<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.3.0 : visibilité « amis », amis en plus des groupes, rôle « Invité » (lecture seule).
 *
 * Les rôles sont renumérotés : invité = 1, contributeur = 2, co-gestionnaire = 3.
 */
class v130_privacy extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'portfolio_albums', 'album_friends');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v120_navigation'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'portfolio_albums' => [
					'album_friends'	=> ['BOOL', 0, 'after' => 'album_visibility'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'portfolio_albums' => ['album_friends'],
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'shift_roles']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'unshift_roles']]],
		];
	}

	/**
	 * Contributeur 1 → 2, co-gestionnaire 2 → 3 (le 1 devient « invité »).
	 */
	public function shift_roles()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . 'portfolio_members SET member_role = member_role + 1');
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.3.0' WHERE config_name = 'portfolio_version'");
	}

	public function unshift_roles()
	{
		if (!$this->db_tools->sql_table_exists($this->table_prefix . 'portfolio_members'))
		{
			return;
		}
		$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'portfolio_members WHERE member_role = 1');
		$this->db->sql_query('UPDATE ' . $this->table_prefix . 'portfolio_members SET member_role = member_role - 1');
	}
}
