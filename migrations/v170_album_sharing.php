<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.7.0 : partage album par album (le partage « tous mes albums » est converti), étiquettes de visibilité
 * réservées aux administrateurs, taille des vignettes, filigrane plus lisible et recalculable.
 */
class v170_album_sharing extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_grid_size'";
		$result = $this->db->sql_query($sql);
		$found = $this->db->sql_fetchfield('config_value') !== false;
		$this->db->sql_freeresult($result);

		return $found;
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v160_display_embed'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'insert_settings']]],
			['custom', [[$this, 'convert_global_shares']]],
		];
	}

	public function insert_settings()
	{
		$rows = [];
		foreach ([
			'portfolio_vis_badges'	=> 0,
			'portfolio_grid_size'	=> 210,
			'portfolio_wm_revision'	=> 1,
		] as $name => $value)
		{
			$rows[] = ['config_name' => $name, 'config_value' => (string) $value];
		}
		$this->db->sql_multi_insert($this->table_prefix . 'portfolio_config', $rows);

		// Contour autour du texte du filigrane : indispensable pour le lire sur les photos claires
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1' WHERE config_name = 'portfolio_wm_shadow'");
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.7.0' WHERE config_name = 'portfolio_version'");
	}

	/**
	 * Un partage « tous mes albums » (album_id = 0) devient un partage sur chacun des albums racines
	 * du propriétaire ; les sous-albums en héritent. Les accès restent donc identiques.
	 */
	public function convert_global_shares()
	{
		$members = $this->table_prefix . 'portfolio_members';
		$albums = $this->table_prefix . 'portfolio_albums';

		$sql = 'SELECT * FROM ' . $members . ' WHERE album_id = 0';
		$result = $this->db->sql_query($sql);
		$globals = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($globals as $row)
		{
			$sql = 'SELECT album_id FROM ' . $albums . ' WHERE owner_id = ' . (int) $row['owner_id'] . ' AND parent_id = 0';
			$result = $this->db->sql_query($sql);
			while ($album = $this->db->sql_fetchrow($result))
			{
				$sql = 'SELECT member_role FROM ' . $members . '
					WHERE owner_id = ' . (int) $row['owner_id'] . ' AND album_id = ' . (int) $album['album_id'] . ' AND user_id = ' . (int) $row['user_id'];
				$check = $this->db->sql_query($sql);
				$existing = $this->db->sql_fetchfield('member_role');
				$this->db->sql_freeresult($check);

				if ($existing === false)
				{
					$this->db->sql_query('INSERT INTO ' . $members . ' ' . $this->db->sql_build_array('INSERT', [
						'owner_id'		=> (int) $row['owner_id'],
						'album_id'		=> (int) $album['album_id'],
						'user_id'		=> (int) $row['user_id'],
						'member_role'	=> (int) $row['member_role'],
						'member_time'	=> (int) $row['member_time'],
					]));
				}
				else if ((int) $existing < (int) $row['member_role'])
				{
					$this->db->sql_query('UPDATE ' . $members . ' SET member_role = ' . (int) $row['member_role'] . '
						WHERE owner_id = ' . (int) $row['owner_id'] . ' AND album_id = ' . (int) $album['album_id'] . ' AND user_id = ' . (int) $row['user_id']);
				}
			}
			$this->db->sql_freeresult($result);
		}

		$this->db->sql_query('DELETE FROM ' . $members . ' WHERE album_id = 0');
	}
}
