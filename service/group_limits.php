<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Quotas et validation par groupe (v1.10.3).
 *
 * Pour chaque groupe : nombre maximum de photos, nombre maximum d'albums
 * (-1 = réglage général, 0 = illimité) et validation obligatoire.
 *
 * Un membre dans plusieurs groupes obtient la valeur la plus favorable des groupes réglés
 * (illimité l'emporte, sinon le plus grand nombre). La validation obligatoire s'applique
 * dès qu'un de ses groupes la demande.
 */
class group_limits
{
	const DEFAULT_VALUE = -1;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $table;

	/** @var array|null [group_id => row] */
	protected $rows;

	/** @var array [user_id => résultat] */
	protected $user_cache = [];

	public function __construct(\phpbb\db\driver\driver_interface $db, $table)
	{
		$this->db = $db;
		$this->table = $table;
	}

	/**
	 * @return array [group_id => ['photos' => int, 'albums' => int, 'approval' => bool]]
	 */
	public function all()
	{
		if ($this->rows === null)
		{
			$this->rows = [];
			$result = $this->db->sql_query('SELECT group_id, limit_photos, limit_albums, limit_approval FROM ' . $this->table);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->rows[(int) $row['group_id']] = [
					'photos'	=> (int) $row['limit_photos'],
					'albums'	=> (int) $row['limit_albums'],
					'approval'	=> (bool) $row['limit_approval'],
				];
			}
			$this->db->sql_freeresult($result);
		}

		return $this->rows;
	}

	/**
	 * Remplace l'ensemble des réglages. Un groupe laissé entièrement « par défaut » n'est pas conservé.
	 *
	 * @param array $rows [group_id => ['photos' => int, 'albums' => int, 'approval' => bool]]
	 */
	public function save(array $rows)
	{
		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->table);
		$insert = [];
		foreach ($rows as $group_id => $row)
		{
			$photos = max(self::DEFAULT_VALUE, (int) $row['photos']);
			$albums = max(self::DEFAULT_VALUE, (int) $row['albums']);
			$approval = !empty($row['approval']);
			if ((int) $group_id <= 0 || ($photos === self::DEFAULT_VALUE && $albums === self::DEFAULT_VALUE && !$approval))
			{
				continue;
			}
			$insert[] = [
				'group_id'			=> (int) $group_id,
				'limit_photos'		=> $photos,
				'limit_albums'		=> $albums,
				'limit_approval'	=> $approval ? 1 : 0,
			];
		}
		if ($insert)
		{
			$this->db->sql_multi_insert($this->table, $insert);
		}
		$this->db->sql_transaction('commit');

		$this->rows = null;
		$this->user_cache = [];
	}

	/**
	 * Valeurs applicables à un membre d'après ses groupes.
	 *
	 * @return array ['photos' => int, 'albums' => int, 'approval' => bool] (-1 = réglage général)
	 */
	public function for_user($user_id)
	{
		$user_id = (int) $user_id;
		if (isset($this->user_cache[$user_id]))
		{
			return $this->user_cache[$user_id];
		}

		$out = ['photos' => self::DEFAULT_VALUE, 'albums' => self::DEFAULT_VALUE, 'approval' => false];
		$rows = $this->all();
		if ($rows && $user_id > ANONYMOUS)
		{
			$photos = $albums = [];
			$sql = 'SELECT group_id FROM ' . USER_GROUP_TABLE . '
				WHERE user_id = ' . $user_id . '
					AND user_pending = 0
					AND ' . $this->db->sql_in_set('group_id', array_keys($rows));
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$limit = $rows[(int) $row['group_id']];
				if ($limit['photos'] >= 0)
				{
					$photos[] = $limit['photos'];
				}
				if ($limit['albums'] >= 0)
				{
					$albums[] = $limit['albums'];
				}
				$out['approval'] = $out['approval'] || $limit['approval'];
			}
			$this->db->sql_freeresult($result);

			$out['photos'] = self::best($photos);
			$out['albums'] = self::best($albums);
		}

		return $this->user_cache[$user_id] = $out;
	}

	/**
	 * Valeur la plus favorable : 0 (illimité) l'emporte, sinon la plus grande.
	 */
	protected static function best(array $values)
	{
		if (!$values)
		{
			return self::DEFAULT_VALUE;
		}

		return in_array(0, $values, true) ? 0 : max($values);
	}
}
