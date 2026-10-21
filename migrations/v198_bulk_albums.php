<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.9.8 : visibilité et téléchargement modifiables en nombre dans l'ACP ;
 * albums créés par la bascule phpBB Gallery réservés aux membres inscrits et sans téléchargement par défaut.
 */
class v198_bulk_albums extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.9.8', '>=');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v197_elsewhere'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'add_settings']]],
			['custom', [[$this, 'set_version']]],
		];
	}

	public function add_settings()
	{
		$table = $this->table_prefix . 'portfolio_config';
		foreach (['portfolio_migrate_visibility' => 'registered', 'portfolio_migrate_download' => '0'] as $name => $value)
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
	}

	public function set_version()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.9.8' WHERE config_name = 'portfolio_version'");
	}
}
