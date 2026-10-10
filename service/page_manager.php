<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Pages Portfolio affichées dans le forum (v1.8.0).
 */
class page_manager
{
	const TYPES = ['recent', 'top', 'comments', 'random', 'albums', 'album'];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $pages_table)
	{
		$this->db = $db;
		$this->table = $pages_table;
	}

	public function get($page_id)
	{
		$result = $this->db->sql_query('SELECT * FROM ' . $this->table . ' WHERE page_id = ' . (int) $page_id);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	public function get_all()
	{
		$result = $this->db->sql_query('SELECT * FROM ' . $this->table . ' ORDER BY page_title ASC, page_id ASC');
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function save(array $data, $page_id = 0)
	{
		$row = [
			'page_title'			=> (string) $data['page_title'],
			'page_desc'				=> (string) $data['page_desc'],
			'page_desc_uid'			=> (string) $data['page_desc_uid'],
			'page_desc_bitfield'	=> (string) $data['page_desc_bitfield'],
			'page_desc_options'		=> (int) $data['page_desc_options'],
			'page_type'				=> in_array($data['page_type'], self::TYPES, true) ? $data['page_type'] : 'recent',
			'album_id'				=> (int) $data['album_id'],
			'page_limit'			=> min(100, max(1, (int) $data['page_limit'])),
		];

		if ($page_id)
		{
			$this->db->sql_query('UPDATE ' . $this->table . ' SET ' . $this->db->sql_build_array('UPDATE', $row) . ' WHERE page_id = ' . (int) $page_id);

			return (int) $page_id;
		}

		$row['forum_id'] = 0;
		$row['page_time'] = time();
		$this->db->sql_query('INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $row));

		return (int) $this->db->sql_nextid();
	}

	public function set_forum($page_id, $forum_id)
	{
		$this->db->sql_query('UPDATE ' . $this->table . ' SET forum_id = ' . (int) $forum_id . ' WHERE page_id = ' . (int) $page_id);
	}

	public function delete($page_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->table . ' WHERE page_id = ' . (int) $page_id);
	}

	/**
	 * Pages qui affichent cet album (protection contre la suppression).
	 */
	public function pages_for_album($album_id)
	{
		$result = $this->db->sql_query('SELECT page_id FROM ' . $this->table . " WHERE page_type = 'album' AND album_id = " . (int) $album_id);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return array_map('intval', array_column($rows, 'page_id'));
	}
}
