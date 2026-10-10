<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.8.0 : pages Portfolio affichées dans le forum (forum-lien, droits par groupe du forum) et albums protégés.
 */
class v180_pages extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.8.0', '>=');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v170_album_sharing'];
	}

	public function update_schema()
	{
		// Pas de condition ici : le migrateur recalcule ce tableau à chaque étape. Les outils de phpBB
		// ignorent d'eux-mêmes une table ou une colonne déjà présente (installation interrompue).
		return [
			'add_tables' => [
				$this->table_prefix . 'portfolio_pages' => [
					'COLUMNS' => [
						'page_id'			=> ['UINT', null, 'auto_increment'],
						'page_title'		=> ['VCHAR_UNI:255', ''],
						'page_desc'			=> ['MTEXT_UNI', ''],
						'page_desc_uid'		=> ['VCHAR:8', ''],
						'page_desc_bitfield' => ['VCHAR:255', ''],
						'page_desc_options'	=> ['UINT:11', 7],
						'page_type'			=> ['VCHAR:20', 'recent'],
						'album_id'			=> ['UINT', 0],
						'page_limit'		=> ['USINT', 24],
						'forum_id'			=> ['UINT', 0],
						'page_time'			=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> 'page_id',
					'KEYS'			=> [
						'forum_id'	=> ['INDEX', 'forum_id'],
					],
				],
			],
			'add_columns' => [
				$this->table_prefix . 'portfolio_albums' => [
					'album_protected'	=> ['BOOL', 0, 'after' => 'album_download'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables'	=> [$this->table_prefix . 'portfolio_pages'],
			'drop_columns'	=> [$this->table_prefix . 'portfolio_albums' => ['album_protected']],
		];
	}

	public function update_data()
	{
		return [
			['module.add', ['acp', 'ACP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\acp\main_module',
				'modes'				=> ['pages'],
			]]],
			['custom', [[$this, 'set_version']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_link_forums']]],
		];
	}

	public function set_version()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.8.0' WHERE config_name = 'portfolio_version'");
	}

	/**
	 * Désinstallation complète : les forums-liens créés pour les pages ne mèneraient plus nulle part.
	 * Ils sont transformés en simples catégories vides plutôt que supprimés (l'administrateur décide).
	 */
	public function remove_link_forums()
	{
		if (!$this->db_tools->sql_table_exists($this->table_prefix . 'portfolio_pages'))
		{
			return;
		}
		$sql = 'SELECT forum_id FROM ' . $this->table_prefix . 'portfolio_pages WHERE forum_id > 0';
		$result = $this->db->sql_query($sql);
		$ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['forum_id'];
		}
		$this->db->sql_freeresult($result);

		if ($ids)
		{
			$this->db->sql_query('UPDATE ' . $this->table_prefix . "forums SET forum_link = '', forum_type = " . FORUM_CAT . ' WHERE ' . $this->db->sql_in_set('forum_id', $ids) . ' AND forum_type = ' . FORUM_LINK);
		}
	}
}
