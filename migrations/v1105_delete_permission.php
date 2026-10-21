<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.10.5 : permission « Peut supprimer ses propres photos et albums », accordée par défaut
 * (groupe Membres inscrits et rôles utilisateurs) ; tri et recherche par membre dans Albums et droits.
 */
class v1105_delete_permission extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT auth_option_id FROM ' . ACL_OPTIONS_TABLE . " WHERE auth_option = 'u_portfolio_delete'";
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = (bool) $this->db->sql_fetchfield('auth_option_id');
		$this->db->sql_freeresult($result);

		return $exists;
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v1104_pending_pm'];
	}

	public function update_data()
	{
		$data = [
			['permission.add', ['u_portfolio_delete']],
		];
		if ($this->group_exists('REGISTERED'))
		{
			$data[] = ['permission.permission_set', ['REGISTERED', 'u_portfolio_delete', 'group']];
		}
		foreach (['ROLE_USER_FULL', 'ROLE_USER_STANDARD', 'ROLE_USER_LIMITED', 'ROLE_USER_NEW_MEMBER'] as $role)
		{
			if ($this->role_exists($role))
			{
				$data[] = ['permission.permission_set', [$role, 'u_portfolio_delete']];
			}
		}
		$data[] = ['custom', [[$this, 'set_version']]];

		return $data;
	}

	public function revert_data()
	{
		return [
			['permission.remove', ['u_portfolio_delete']],
		];
	}

	public function set_version()
	{
		$this->db->sql_query('UPDATE ' . $this->table_prefix . "portfolio_config SET config_value = '1.10.5' WHERE config_name = 'portfolio_version'");
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
