<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.5.0 :
 *  - bascule depuis phpBB Gallery (table de correspondance, sauvegarde des messages réécrits) ;
 *  - réactions sur les photos ;
 *  - autorisation de téléchargement des originaux par album ;
 *  - lien vers la galerie dans le profil et le mini-profil des messages.
 */
class v150_gallery_reactions extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'portfolio_reactions');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v141_acp_skin'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				// Correspondance ancien identifiant phpBB Gallery => nouvel identifiant Portfolio
				$this->table_prefix . 'portfolio_legacy' => [
					'COLUMNS' => [
						'legacy_type'	=> ['VCHAR:10', ''],
						'legacy_id'		=> ['UINT', 0],
						'new_id'		=> ['UINT', 0],
						'user_id'		=> ['UINT', 0],
						'legacy_time'	=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> ['legacy_type', 'legacy_id'],
					'KEYS'			=> [
						'new_id'	=> ['INDEX', ['legacy_type', 'new_id']],
					],
				],

				// Texte d'origine des messages et signatures réécrits (pour pouvoir annuler)
				$this->table_prefix . 'portfolio_legacy_texts' => [
					'COLUMNS' => [
						'text_type'		=> ['VCHAR:10', ''],
						'text_id'		=> ['UINT', 0],
						'original_text'	=> ['MTEXT_UNI', ''],
						'rewrite_time'	=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> ['text_type', 'text_id'],
				],

				// Réactions (une par membre et par photo)
				$this->table_prefix . 'portfolio_reactions' => [
					'COLUMNS' => [
						'photo_id'		=> ['UINT', 0],
						'user_id'		=> ['UINT', 0],
						'reaction'		=> ['VCHAR:20', ''],
						'reaction_time'	=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> ['photo_id', 'user_id'],
					'KEYS'			=> [
						'photo_reaction'	=> ['INDEX', ['photo_id', 'reaction']],
					],
				],
			],
			'add_columns' => [
				$this->table_prefix . 'portfolio_albums' => [
					'album_download'	=> ['BOOL', 1, 'after' => 'album_friends'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'portfolio_legacy',
				$this->table_prefix . 'portfolio_legacy_texts',
				$this->table_prefix . 'portfolio_reactions',
			],
			'drop_columns' => [
				$this->table_prefix . 'portfolio_albums' => ['album_download'],
			],
		];
	}

	public function update_data()
	{
		$data = [
			['custom', [[$this, 'insert_settings']]],
			['permission.add', ['u_portfolio_react']],
			['permission.add', ['u_portfolio_profile_link']],
			['permission.permission_set', ['REGISTERED', ['u_portfolio_react', 'u_portfolio_profile_link'], 'group']],
			['permission.permission_set', ['GUESTS', 'u_portfolio_profile_link', 'group']],
			['module.add', ['acp', 'ACP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\acp\main_module',
				'modes'				=> ['migrate'],
			]]],
		];

		foreach (['ROLE_USER_FULL', 'ROLE_USER_STANDARD', 'ROLE_USER_LIMITED', 'ROLE_USER_NEW_MEMBER'] as $role)
		{
			if ($this->role_exists($role))
			{
				$data[] = ['permission.permission_set', [$role, ['u_portfolio_react', 'u_portfolio_profile_link']]];
			}
		}

		return $data;
	}

	public function insert_settings()
	{
		$rows = [];
		foreach ([
			'portfolio_reactions_enabled'	=> 1,
			'portfolio_reactions_list'		=> 'like,love,haha,wow,sad,clap',
			'portfolio_profile_link'		=> 1,
			'portfolio_miniprofile_link'	=> 1,
			'portfolio_legacy_redirect'		=> 1,
			'portfolio_migrate_batch'		=> 25,
		] as $name => $value)
		{
			$rows[] = ['config_name' => $name, 'config_value' => (string) $value];
		}
		$this->db->sql_multi_insert($this->table_prefix . 'portfolio_config', $rows);
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.5.0' WHERE config_name = 'portfolio_version'");
	}

	protected function role_exists($role)
	{
		$sql = 'SELECT role_id FROM ' . ACL_ROLES_TABLE . " WHERE role_name = '" . $this->db->sql_escape($role) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$role_id = (int) $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		return $role_id > 0;
	}
}
