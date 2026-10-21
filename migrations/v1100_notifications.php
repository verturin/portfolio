<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

/**
 * v1.10.0 :
 * - notifications du forum pour les notes, commentaires et réactions (une par action, ou regroupées) ;
 * - filigrane retiré par le membre lui-même sur ses albums, si l'administration l'y autorise.
 */
class v1100_notifications extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'portfolio_activity');
	}

	public static function depends_on()
	{
		return ['\verturin\portfolio\migrations\v1910_tile_density'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'portfolio_activity' => [
					'COLUMNS' => [
						'activity_id'		=> ['UINT', null, 'auto_increment'],
						'user_id'			=> ['UINT', 0],
						'photo_id'			=> ['UINT', 0],
						'actor_id'			=> ['UINT', 0],
						'activity_type'		=> ['VCHAR:10', ''],
						'activity_value'	=> ['VCHAR:20', ''],
						'activity_time'		=> ['TIMESTAMP', 0],
						'activity_sent'		=> ['BOOL', 0],
					],
					'PRIMARY_KEY'	=> 'activity_id',
					'KEYS'			=> [
						'pending'	=> ['INDEX', ['activity_sent', 'activity_time']],
						'photo'		=> ['INDEX', ['photo_id', 'actor_id']],
					],
				],
			],
			'add_columns' => [
				$this->table_prefix . 'portfolio_albums' => [
					'album_owner_no_wm'	=> ['BOOL', 0, 'after' => 'album_no_watermark'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables'	=> [$this->table_prefix . 'portfolio_activity'],
			'drop_columns'	=> [$this->table_prefix . 'portfolio_albums' => ['album_owner_no_wm']],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'add_settings']]],
			['module.add', ['acp', 'ACP_PORTFOLIO_TITLE', [
				'module_basename'	=> '\verturin\portfolio\acp\main_module',
				'modes'				=> ['notifications'],
			]]],
		];
	}

	public function add_settings()
	{
		$table = $this->table_prefix . 'portfolio_config';
		$settings = [
			'portfolio_notify_rating'		=> '1',
			'portfolio_notify_comment'		=> '1',
			'portfolio_notify_reaction'		=> '1',
			'portfolio_notify_mode'			=> 'each',
			'portfolio_notify_delay'		=> '1',
			'portfolio_notify_last_run'		=> '0',
			'portfolio_notify_gc'			=> '0',
			'portfolio_wm_optout'			=> '0',
			'portfolio_wm_optout_users'		=> '',
			'portfolio_wm_optout_groups'	=> '',
		];
		foreach ($settings as $name => $value)
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
		$this->db->sql_query('UPDATE ' . $table . " SET config_value = '1.10.0' WHERE config_name = 'portfolio_version'");
	}
}
