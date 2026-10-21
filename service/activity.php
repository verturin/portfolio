<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Notes, commentaires et réactions laissés par les autres membres sur les photos (v1.10.0).
 *
 * Chaque action est inscrite dans la table portfolio_activity, puis :
 * - mode « each » : une notification du forum aussitôt, une par action ;
 * - mode « digest » : la tâche planifiée regroupe toutes les actions en attente en une notification par photo.
 *
 * Changer d'avis (nouvelle note, autre réaction) remplace la notification précédente au lieu d'en ajouter une ;
 * retirer sa note ou sa réaction la supprime.
 */
class activity
{
	const TYPES = ['rating', 'comment', 'reaction'];

	const NOTIFICATION = [
		'rating'	=> 'verturin.portfolio.notification.type.rating',
		'comment'	=> 'verturin.portfolio.notification.type.comment',
		'reaction'	=> 'verturin.portfolio.notification.type.reaction',
	];

	const DIGEST = 'verturin.portfolio.notification.type.digest';

	/** Délais de regroupement proposés (minutes) */
	const DELAYS = [1, 5, 15, 30, 60];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var settings */
	protected $settings;

	/** @var \phpbb\notification\manager */
	protected $notifications;

	/** @var string */
	protected $table;

	/** @var string */
	protected $photos_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, settings $settings, \phpbb\notification\manager $notifications, $table, $photos_table)
	{
		$this->db = $db;
		$this->settings = $settings;
		$this->notifications = $notifications;
		$this->table = $table;
		$this->photos_table = $photos_table;
	}

	public function is_enabled($type)
	{
		return in_array($type, self::TYPES, true) && (bool) $this->settings['portfolio_notify_' . $type];
	}

	public function mode()
	{
		return $this->settings['portfolio_notify_mode'] === 'digest' ? 'digest' : 'each';
	}

	public function delay_minutes()
	{
		$delay = (int) $this->settings['portfolio_notify_delay'];

		return in_array($delay, self::DELAYS, true) ? $delay : 1;
	}

	/**
	 * Une action d'un membre sur la photo d'un autre.
	 *
	 * @param string $type   rating | comment | reaction
	 * @param array  $photo  ligne de la photo (photo_id, user_id, photo_title)
	 * @param int    $actor  auteur de l'action
	 * @param string $value  note (1 à 5), identifiant du commentaire ou code de la réaction ; '' = action retirée
	 */
	public function record($type, array $photo, $actor, $value)
	{
		$owner = (int) $photo['user_id'];
		$actor = (int) $actor;
		$photo_id = (int) $photo['photo_id'];
		$value = (string) $value;

		if ($owner <= ANONYMOUS || $actor === $owner || $actor <= ANONYMOUS)
		{
			return;
		}

		// Note ou réaction changée ou retirée : l'ancienne action disparaît (une seule par membre et par photo)
		if ($type !== 'comment')
		{
			$this->forget($type, $photo_id, $actor);
		}

		if ($value === '' || !$this->is_enabled($type))
		{
			return;
		}

		$this->db->sql_query('INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', [
			'user_id'			=> $owner,
			'photo_id'			=> $photo_id,
			'actor_id'			=> $actor,
			'activity_type'		=> $type,
			'activity_value'	=> substr($value, 0, 20),
			'activity_time'		=> time(),
			'activity_sent'		=> 0,
		]));
		$activity_id = (int) $this->db->sql_nextid();

		if ($this->mode() === 'each')
		{
			$this->notify_one($activity_id, $type, $photo, $actor, $value);
			$this->mark_sent([$activity_id]);
		}
	}

	/**
	 * Retire les actions (et leurs notifications) d'un membre sur une photo : note ou réaction changée,
	 * commentaire supprimé ($value = identifiant du commentaire).
	 */
	public function forget($type, $photo_id, $actor = 0, $value = '')
	{
		$where = "activity_type = '" . $this->db->sql_escape($type) . "' AND photo_id = " . (int) $photo_id;
		if ($actor)
		{
			$where .= ' AND actor_id = ' . (int) $actor;
		}
		if ($value !== '')
		{
			$where .= " AND activity_value = '" . $this->db->sql_escape((string) $value) . "'";
		}

		$ids = [];
		$result = $this->db->sql_query('SELECT activity_id FROM ' . $this->table . ' WHERE ' . $where);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['activity_id'];
		}
		$this->db->sql_freeresult($result);

		if (!$ids)
		{
			return;
		}

		if ($type !== 'comment')
		{
			$this->notifications->delete_notifications(self::NOTIFICATION[$type], $ids);
		}
		$this->db->sql_query('DELETE FROM ' . $this->table . ' WHERE ' . $this->db->sql_in_set('activity_id', $ids));
	}

	/** Toutes les traces d'une photo supprimée définitivement */
	public function forget_photo($photo_id)
	{
		foreach (['rating', 'reaction'] as $type)
		{
			$this->forget($type, $photo_id);
		}
		$this->notifications->delete_notifications(self::DIGEST, false, (int) $photo_id);
		$this->db->sql_query('DELETE FROM ' . $this->table . ' WHERE photo_id = ' . (int) $photo_id);
	}

	protected function notify_one($activity_id, $type, array $photo, $actor, $value)
	{
		$data = [
			'activity_id'	=> $activity_id,
			'photo_id'		=> (int) $photo['photo_id'],
			'photo_title'	=> (string) $photo['photo_title'],
			'user_id'		=> (int) $photo['user_id'],
			'actor_id'		=> (int) $actor,
			'value'			=> $value,
			'time'			=> time(),
		];
		if ($type === 'comment')
		{
			// Le type « comment » existe depuis la v1.0 : identifiant = celui du commentaire
			$data['comment_id'] = (int) $value;
		}

		$this->notifications->add_notifications(self::NOTIFICATION[$type], $data);
	}

	protected function mark_sent(array $ids)
	{
		if ($ids)
		{
			$this->db->sql_query('UPDATE ' . $this->table . ' SET activity_sent = 1 WHERE ' . $this->db->sql_in_set('activity_id', array_map('intval', $ids)));
		}
	}

	/* ------------------------------------------------------------------
	 * Regroupement (tâche planifiée)
	 * ------------------------------------------------------------------ */

	public function pending_count()
	{
		$result = $this->db->sql_query('SELECT COUNT(activity_id) AS total FROM ' . $this->table . ' WHERE activity_sent = 0');
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	public function digest_due()
	{
		return (int) $this->settings['portfolio_notify_last_run'] <= time() - $this->delay_minutes() * 60 && $this->pending_count() > 0;
	}

	/**
	 * Envoie une notification par photo pour toutes les actions en attente.
	 *
	 * @return int Nombre de notifications envoyées
	 */
	public function send_digest()
	{
		$this->settings->set('portfolio_notify_last_run', time());

		$groups = [];
		$sql = 'SELECT a.*, p.photo_title, p.user_id AS photo_owner
			FROM ' . $this->table . ' a
			LEFT JOIN ' . $this->photos_table . ' p ON p.photo_id = a.photo_id
			WHERE a.activity_sent = 0
			ORDER BY a.activity_id ASC';
		$result = $this->db->sql_query_limit($sql, 2000);
		$all = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$all[] = (int) $row['activity_id'];
			if ($row['photo_owner'] === null || (int) $row['photo_owner'] !== (int) $row['user_id'])
			{
				continue; // photo supprimée ou passée à un autre membre
			}
			$key = (int) $row['user_id'] . '_' . (int) $row['photo_id'];
			if (!isset($groups[$key]))
			{
				$groups[$key] = [
					'activity_id'	=> (int) $row['activity_id'],
					'user_id'		=> (int) $row['user_id'],
					'photo_id'		=> (int) $row['photo_id'],
					'photo_title'	=> (string) $row['photo_title'],
					'actors'		=> [],
					'counts'		=> ['rating' => 0, 'comment' => 0, 'reaction' => 0],
					'last_comment'	=> 0,
					'time'			=> (int) $row['activity_time'],
				];
			}
			$g = &$groups[$key];
			$g['counts'][$row['activity_type']]++;
			$g['actors'][(int) $row['actor_id']] = true;
			$g['time'] = max($g['time'], (int) $row['activity_time']);
			if ($row['activity_type'] === 'comment')
			{
				$g['last_comment'] = (int) $row['activity_value'];
			}
			unset($g);
		}
		$this->db->sql_freeresult($result);

		foreach ($groups as $g)
		{
			$actors = array_keys($g['actors']);
			$this->notifications->add_notifications(self::DIGEST, [
				'activity_id'	=> $g['activity_id'],
				'photo_id'		=> $g['photo_id'],
				'photo_title'	=> $g['photo_title'],
				'user_id'		=> $g['user_id'],
				'actor_id'		=> (int) end($actors),
				'actor_count'	=> count($actors),
				'counts'		=> $g['counts'],
				'comment_id'	=> $g['last_comment'],
				'time'			=> $g['time'],
			]);
		}

		$this->mark_sent($all);

		return count($groups);
	}

	public function cleanup_due()
	{
		return (int) $this->settings['portfolio_notify_gc'] < time() - 86400;
	}

	/** Ménage quotidien : les actions déjà notifiées depuis plus de 30 jours ne servent plus */
	public function cleanup()
	{
		$this->settings->set('portfolio_notify_gc', time());
		$this->db->sql_query('DELETE FROM ' . $this->table . ' WHERE activity_sent = 1 AND activity_time < ' . (time() - 30 * 86400));
	}
}
