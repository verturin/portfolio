<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.10.6 :
 *  - « Peut supprimer ses propres photos et albums » passe à Non par défaut (à accorder par groupe) ;
 *  - motif de suppression obligatoire par défaut (réglable) ;
 *  - champ de profil « Galerie » laissé par phpBB Gallery : lien vers le Portfolio.
 */
class v1106_delete_reason extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT config_value FROM ' . $this->table_prefix . "portfolio_config WHERE config_name = 'portfolio_version'";
		$result = $this->db->sql_query($sql);
		$version = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $version !== '' && version_compare($version, '1.10.6', '>=');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v1105_delete_permission'];
	}

	public function update_data()
	{
		$data = [];
		if ($this->group_exists('REGISTERED'))
		{
			$data[] = ['permission.permission_unset', ['REGISTERED', 'u_portfolio_delete', 'group']];
		}
		foreach (['ROLE_USER_FULL', 'ROLE_USER_STANDARD', 'ROLE_USER_LIMITED', 'ROLE_USER_NEW_MEMBER'] as $role)
		{
			if ($this->role_exists($role))
			{
				$data[] = ['permission.permission_unset', [$role, 'u_portfolio_delete']];
			}
		}
		$data[] = ['custom', [[$this, 'add_settings']]];
		$data[] = ['custom', [[$this, 'gallery_profile_field']]];

		return $data;
	}

	public function add_settings()
	{
		$table = $this->table_prefix . 'portfolio_config';
		$sql = 'SELECT config_name FROM ' . $table . " WHERE config_name = 'portfolio_trash_reason_required'";
		$result = $this->db->sql_query($sql);
		$exists = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$exists)
		{
			$this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', [
				'config_name'	=> 'portfolio_trash_reason_required',
				'config_value'	=> '1',
			]));
		}
		$this->db->sql_query('UPDATE ' . $table . " SET config_value = '1.10.6' WHERE config_name = 'portfolio_version'");
	}

	/**
	 * Le champ de profil « gallery_palbum » de phpBB Gallery pointe vers /gallery/album/N, introuvable
	 * une fois la galerie désactivée : il pointe désormais vers le Portfolio (album repris, sinon la galerie
	 * si elle est encore active).
	 */
	public function gallery_profile_field()
	{
		$sql = 'SELECT field_id FROM ' . PROFILE_FIELDS_TABLE . " WHERE field_name = 'gallery_palbum'";
		$result = $this->db->sql_query_limit($sql, 1);
		$field_id = (int) $this->db->sql_fetchfield('field_id');
		$this->db->sql_freeresult($result);
		if (!$field_id)
		{
			return;
		}
		$url = generate_board_url() . (empty($this->config['enable_mod_rewrite']) ? '/app.php' : '') . '/portfolio/legacy/album/%s';
		$this->db->sql_query('UPDATE ' . PROFILE_FIELDS_TABLE . " SET field_contact_url = '" . $this->db->sql_escape($url) . "' WHERE field_id = " . $field_id);
	}

	protected function role_exists($role)
	{
		$sql = 'SELECT role_id FROM ' . ACL_ROLES_TABLE . " WHERE role_name = '" . $this->db->sql_escape($role) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$role_id = (int) $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		return $role_id > 0;
	}

	protected function group_exists($name)
	{
		$sql = 'SELECT group_id FROM ' . GROUPS_TABLE . " WHERE group_name = '" . $this->db->sql_escape($name) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$group_id = (int) $this->db->sql_fetchfield('group_id');
		$this->db->sql_freeresult($result);

		return $group_id > 0;
	}
}
