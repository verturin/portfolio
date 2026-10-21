<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.10.2 : message privé aux membres autorisés à retirer le filigrane (une seule fois),
 * page « Filigrane de mes photos » dans le panneau de l'utilisateur pour y renoncer.
 */
class v1102_wm_optout_pm extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.10.2', '>=');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v1101_wm_default'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'add_settings']]],
			['module.add', ['ucp', 0, 'UCP_PORTFOLIO_TITLE']],
			['module.add', ['ucp', 'UCP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\ucp\main_module',
				'modes'				=> ['watermark'],
			]]],
		];
	}

	public function add_settings()
	{
		$table = $this->table_prefix . 'portfolio_config';
		// Les membres déjà autorisés en 1.10.0 / 1.10.1 recevront le message au prochain enregistrement de la liste
		foreach (['portfolio_wm_optout_notified' => '', 'portfolio_wm_optout_excluded' => ''] as $name => $value)
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
		$this->db->sql_query('UPDATE ' . $table . " SET config_value = '1.10.2' WHERE config_name = 'portfolio_version'");
	}
}
