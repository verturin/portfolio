<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.6.0 : choix de création d'album gérés dans l'ACP, BBCode [portfolio], dernières photos dans le profil,
 * aperçu rapide, filigrane sur les vignettes.
 */
class v160_display_embed extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_vis_choices'";
		$result = $this->db->sql_query($sql);
		$found = $this->db->sql_fetchfield('config_value') !== false;
		$this->db->sql_freeresult($result);

		return $found;
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v151_mysql_strict'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'insert_settings']]],
			['permission.add', ['u_portfolio_vis_groups']],
			['custom', [[$this, 'install_bbcode']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_bbcode']]],
		];
	}

	public function insert_settings()
	{
		$rows = [];
		foreach ([
			'portfolio_vis_choices'				=> '0,1,4,3',
			'portfolio_vis_default'				=> 1,
			'portfolio_album_lock_choice'		=> 1,
			'portfolio_album_download_choice'	=> 1,
			'portfolio_album_download_default'	=> 1,
			'portfolio_album_sort_choice'		=> 1,
			'portfolio_profile_photos'			=> 5,
			'portfolio_quickview'				=> 1,
			'portfolio_embed'					=> 1,
		] as $name => $value)
		{
			$rows[] = ['config_name' => $name, 'config_value' => (string) $value];
		}
		$this->db->sql_multi_insert($this->table_prefix . 'portfolio_config', $rows);

		// Les vignettes sont désormais filigranées (texte adapté à leur petite taille)
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1' WHERE config_name = 'portfolio_wm_thumbs'");
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.6.0' WHERE config_name = 'portfolio_version'");
	}

	public function install_bbcode()
	{
		$bbcode = new \verturin\portfolio\service\bbcode($this->db, $this->table_prefix . 'bbcodes');
		$bbcode->install(generate_board_url());
	}

	public function remove_bbcode()
	{
		$bbcode = new \verturin\portfolio\service\bbcode($this->db, $this->table_prefix . 'bbcodes');
		$bbcode->uninstall();
	}
}
