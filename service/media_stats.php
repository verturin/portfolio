<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Nombre de vidéos et de documents (v1.10.10).
 *
 * Les modules Portfolio Vidéo (verturin/portfoliovideo) et Portfolio Documents (verturin/portfoliodocs)
 * enregistrent chaque vidéo ou document comme une photo du Portfolio, avec une ligne dans leur propre table.
 * Ce service compte ces éléments pour les afficher à part, et les retirer du nombre de photos.
 * Un module absent ou désactivé n'apparaît pas.
 */
class media_stats
{
	/** Module => [nom de l'extension, table sans préfixe] */
	const MODULES = [
		'videos'	=> ['verturin/portfoliovideo', 'portfolio_videos'],
		'documents'	=> ['verturin/portfoliodocs', 'portfolio_docs'],
	];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\extension\manager */
	protected $ext_manager;

	/** @var \phpbb\db\tools\tools_interface */
	protected $db_tools;

	/** @var string */
	protected $table_prefix;

	/** @var string */
	protected $photos_table;

	/** @var array|null [module => table] des modules actifs */
	protected $active;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\extension\manager $ext_manager, \phpbb\db\tools\tools_interface $db_tools, $table_prefix, $photos_table)
	{
		$this->db = $db;
		$this->ext_manager = $ext_manager;
		$this->db_tools = $db_tools;
		$this->table_prefix = $table_prefix;
		$this->photos_table = $photos_table;
	}

	/**
	 * @return array [module => table] des modules installés et activés
	 */
	public function active_modules()
	{
		if ($this->active === null)
		{
			$this->active = [];
			foreach (self::MODULES as $module => list($ext, $table))
			{
				$table = $this->table_prefix . $table;
				if ($this->ext_manager->is_enabled($ext)
					&& $this->db_tools->sql_table_exists($table)
					&& $this->db_tools->sql_column_exists($table, 'photo_id'))
				{
					$this->active[$module] = $table;
				}
			}
		}

		return $this->active;
	}

	/**
	 * Nombre de vidéos et de documents visibles parmi les photos validées des albums donnés.
	 *
	 * @param int[]    $album_ids Albums pris en compte (vide = tous)
	 * @param int|null $status    Statut des photos (null = validées)
	 * @return array [module => nombre], seulement pour les modules actifs
	 */
	public function counts(array $album_ids = [], $status = null)
	{
		$out = [];
		foreach ($this->active_modules() as $module => $table)
		{
			$sql = 'SELECT COUNT(m.photo_id) AS total
				FROM ' . $table . ' m, ' . $this->photos_table . ' p
				WHERE p.photo_id = m.photo_id
					AND p.photo_status = ' . (int) ($status === null ? photo_manager::STATUS_APPROVED : $status)
					. ($album_ids ? ' AND ' . $this->db->sql_in_set('p.album_id', array_map('intval', $album_ids)) : '');
			$result = $this->db->sql_query($sql);
			$out[$module] = (int) $this->db->sql_fetchfield('total');
			$this->db->sql_freeresult($result);
		}

		return $out;
	}
}
