<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\migrations;

class v100_schema extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'portfolio_albums');
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				// Réglages de l'extension (indépendants de phpbb_config)
				$this->table_prefix . 'portfolio_config' => [
					'COLUMNS' => [
						'config_name'	=> ['VCHAR', ''],
						'config_value'	=> ['VCHAR_UNI', ''],
					],
					'PRIMARY_KEY'	=> 'config_name',
				],

				// Albums et sous-albums (owner_id = 0 : album public géré par l'administration)
				$this->table_prefix . 'portfolio_albums' => [
					'COLUMNS' => [
						'album_id'				=> ['UINT', null, 'auto_increment'],
						'parent_id'				=> ['UINT', 0],
						'owner_id'				=> ['UINT', 0],
						'album_name'			=> ['VCHAR_UNI', ''],
						'album_desc'			=> ['MTEXT_UNI', ''],
						'album_desc_uid'		=> ['VCHAR:8', ''],
						'album_desc_bitfield'	=> ['VCHAR:255', ''],
						'album_desc_options'	=> ['UINT:11', 7],
						'album_visibility'		=> ['TINT:1', 0],
						'album_locked'			=> ['BOOL', 0],
						'album_order'			=> ['UINT', 0],
						'album_sort'			=> ['VCHAR:20', 'date_desc'],
						'album_cover_id'		=> ['UINT', 0],
						'album_photos'			=> ['UINT', 0],
						'album_last_photo_id'	=> ['UINT', 0],
						'album_last_time'		=> ['TIMESTAMP', 0],
						'album_time'			=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> 'album_id',
					'KEYS'			=> [
						'parent_id'	=> ['INDEX', 'parent_id'],
						'owner_id'	=> ['INDEX', 'owner_id'],
					],
				],

				// Co-gestion : album_id = 0 => partage de TOUS les albums du propriétaire
				$this->table_prefix . 'portfolio_members' => [
					'COLUMNS' => [
						'member_id'		=> ['UINT', null, 'auto_increment'],
						'owner_id'		=> ['UINT', 0],
						'album_id'		=> ['UINT', 0],
						'user_id'		=> ['UINT', 0],
						'member_role'	=> ['TINT:1', 1],
						'member_time'	=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> 'member_id',
					'KEYS'			=> [
						'owner_album_user'	=> ['UNIQUE', ['owner_id', 'album_id', 'user_id']],
						'user_id'			=> ['INDEX', 'user_id'],
					],
				],

				// Droits par groupe et par album (voir / envoyer / noter / commenter)
				$this->table_prefix . 'portfolio_album_groups' => [
					'COLUMNS' => [
						'album_id'		=> ['UINT', 0],
						'group_id'		=> ['UINT', 0],
						'can_view'		=> ['BOOL', 1],
						'can_upload'	=> ['BOOL', 0],
						'can_rate'		=> ['BOOL', 1],
						'can_comment'	=> ['BOOL', 1],
					],
					'PRIMARY_KEY'	=> ['album_id', 'group_id'],
				],

				// Photos (photo_status : 0 en attente, 1 publiée, 2 corbeille)
				$this->table_prefix . 'portfolio_photos' => [
					'COLUMNS' => [
						'photo_id'				=> ['UINT', null, 'auto_increment'],
						'album_id'				=> ['UINT', 0],
						'user_id'				=> ['UINT', 0],
						'photo_title'			=> ['VCHAR_UNI', ''],
						'photo_desc'			=> ['MTEXT_UNI', ''],
						'photo_desc_uid'		=> ['VCHAR:8', ''],
						'photo_desc_bitfield'	=> ['VCHAR:255', ''],
						'photo_desc_options'	=> ['UINT:11', 7],
						'photo_file'			=> ['VCHAR:100', ''],
						'photo_realname'		=> ['VCHAR_UNI', ''],
						'photo_ext'				=> ['VCHAR:8', ''],
						'photo_mimetype'		=> ['VCHAR:50', ''],
						'photo_filesize'		=> ['UINT:11', 0],
						'photo_width'			=> ['USINT', 0],
						'photo_height'			=> ['USINT', 0],
						'photo_hash'			=> ['VCHAR:40', ''],
						'photo_exif_stripped'	=> ['BOOL', 0],
						'photo_time'			=> ['TIMESTAMP', 0],
						'photo_status'			=> ['TINT:1', 1],
						'photo_prev_status'		=> ['TINT:1', 1],
						'photo_views'			=> ['UINT', 0],
						'photo_rating_sum'		=> ['UINT', 0],
						'photo_rating_count'	=> ['UINT', 0],
						'photo_rating_avg'		=> ['USINT', 0],
						'photo_comments'		=> ['UINT', 0],
						'deleted_time'			=> ['TIMESTAMP', 0],
						'deleted_by'			=> ['UINT', 0],
						'deleted_reason'		=> ['VCHAR_UNI', ''],
					],
					'PRIMARY_KEY'	=> 'photo_id',
					'KEYS'			=> [
						'album_status'	=> ['INDEX', ['album_id', 'photo_status']],
						'user_id'		=> ['INDEX', 'user_id'],
						'status_time'	=> ['INDEX', ['photo_status', 'photo_time']],
						'deleted'		=> ['INDEX', ['deleted_by', 'deleted_time']],
						'rating'		=> ['INDEX', 'photo_rating_avg'],
					],
				],

				// Notes des membres (1 à 5)
				$this->table_prefix . 'portfolio_ratings' => [
					'COLUMNS' => [
						'photo_id'		=> ['UINT', 0],
						'user_id'		=> ['UINT', 0],
						'rating'		=> ['TINT:1', 0],
						'rating_time'	=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> ['photo_id', 'user_id'],
				],

				// Commentaires
				$this->table_prefix . 'portfolio_comments' => [
					'COLUMNS' => [
						'comment_id'		=> ['UINT', null, 'auto_increment'],
						'photo_id'			=> ['UINT', 0],
						'user_id'			=> ['UINT', 0],
						'comment_text'		=> ['MTEXT_UNI', ''],
						'comment_uid'		=> ['VCHAR:8', ''],
						'comment_bitfield'	=> ['VCHAR:255', ''],
						'comment_options'	=> ['UINT:11', 7],
						'comment_time'		=> ['TIMESTAMP', 0],
						'comment_edit_time'	=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY'	=> 'comment_id',
					'KEYS'			=> [
						'photo_id'	=> ['INDEX', ['photo_id', 'comment_time']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'portfolio_config',
				$this->table_prefix . 'portfolio_albums',
				$this->table_prefix . 'portfolio_members',
				$this->table_prefix . 'portfolio_album_groups',
				$this->table_prefix . 'portfolio_photos',
				$this->table_prefix . 'portfolio_ratings',
				$this->table_prefix . 'portfolio_comments',
			],
		];
	}
}
