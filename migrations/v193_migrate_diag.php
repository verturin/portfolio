<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.9.3 : diagnostic de la bascule (incident, limites PHP, taille des lots).
 * Aucun changement de schéma : seule la version enregistrée change.
 */
class v193_migrate_diag extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.9.3', '>=') && $this->db_tools->sql_column_exists($this->table_prefix . 'portfolio_legacy', 'legacy_error');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v192_migrate_batches'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'portfolio_legacy' => [
					'legacy_error'	=> ['VCHAR_UNI', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'portfolio_legacy' => ['legacy_error'],
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
		// Lots plus petits par défaut : les grosses galeries sur hébergement mutualisé passent mieux
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '10' WHERE config_name = 'portfolio_migrate_batch' AND config_value = '25'");
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.9.3' WHERE config_name = 'portfolio_version'");
	}
}
