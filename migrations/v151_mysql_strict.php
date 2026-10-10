<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.5.1 : correctif MySQL en mode strict (envoi de photos sans description) ; réactions incluses dans les sauvegardes.
 * Aucun changement de schéma : seule la version enregistrée change.
 */
class v151_mysql_strict extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.5.1', '>=');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v150_gallery_reactions'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'set_version']]],
		];
	}

	public function set_version()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.5.1' WHERE config_name = 'portfolio_version'");
	}
}
