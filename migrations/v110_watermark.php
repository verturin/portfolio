<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.1.0 : filigrane dynamique (logo + nom du site + ID du visiteur).
 * Réglages stockés dans la table propre de l'extension (portfolio_config).
 */
class v110_watermark extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_wm_enabled'";
		$result = $this->db->sql_query($sql);
		$installed = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $installed;
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v100_data'];
	}

	public static function default_settings()
	{
		return [
			'portfolio_wm_enabled'			=> 0,
			'portfolio_wm_text_enabled'		=> 1,
			'portfolio_wm_text'				=> '{SITENAME} · ID {USER_ID}',
			'portfolio_wm_text_size'		=> 3,
			'portfolio_wm_text_color'		=> '#FFFFFF',
			'portfolio_wm_opacity'			=> 40,
			'portfolio_wm_position'			=> 'bottom-right',
			'portfolio_wm_shadow'			=> 1,
			'portfolio_wm_thumbs'			=> 0,
			'portfolio_wm_skip_members'		=> 1,
			'portfolio_wm_logo_enabled'		=> 0,
			'portfolio_wm_logo_size'		=> 15,
			'portfolio_wm_logo_position'	=> 'top-left',
			'portfolio_wm_logo_opacity'		=> 70,
			'portfolio_wm_logo_version'		=> 0,
			'portfolio_wm_cache_days'		=> 7,
			'portfolio_wm_cache_gc'			=> 0,
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'insert_settings']]],
			['module.add', ['acp', 'ACP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\acp\main_module',
				'modes'				=> ['watermark'],
			]]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'delete_settings']]],
		];
	}

	public function insert_settings()
	{
		$rows = [];
		foreach (static::default_settings() as $name => $value)
		{
			$rows[] = ['config_name' => $name, 'config_value' => (string) $value];
		}
		$this->db->sql_multi_insert($this->table_prefix . 'portfolio_config', $rows);

		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.1.0' WHERE config_name = 'portfolio_version'");
	}

	public function delete_settings()
	{
		if ($this->db_tools->sql_table_exists($this->table_prefix . 'portfolio_config'))
		{
			$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'portfolio_config WHERE ' . $this->db->sql_in_set('config_name', array_keys(static::default_settings())));
		}
	}
}
