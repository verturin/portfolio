<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.8.3 : rôle d’un partage modifiable avec confirmation ; menu de l’album aligné.
 * Aucun changement de schéma : seule la version enregistrée change.
 */
class v183_share_roles extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.8.3', '>=');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v182_visitor_view'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'set_version']]],
		];
	}

	public function set_version()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.8.3' WHERE config_name = 'portfolio_version'");
	}
}
