<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Message privé « photo en attente de validation » (v1.10.4).
 *
 * Destinataires réglés dans ACP › Portfolio › Quotas et validation : administrateurs du Portfolio,
 * modérateurs ayant la permission de valider, groupes choisis, membres choisis.
 *
 * Un envoi en plusieurs photos ne produit qu'un message : tant que les photos signalées
 * attendent encore, le même membre ne déclenche pas de nouveau message avant THROTTLE secondes.
 */
class pending_pm
{
	const THROTTLE = 600;
	const TYPE = 'queued';

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var settings */
	protected $config;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\controller\helper */
	protected $helper;

	protected $activity_table;
	protected $photos_table;
	protected $root_path;
	protected $php_ext;

	public function __construct(\phpbb\db\driver\driver_interface $db, settings $config, \phpbb\auth\auth $auth, \phpbb\user $user, \phpbb\language\language $language, \phpbb\controller\helper $helper, $activity_table, $photos_table, $root_path, $php_ext)
	{
		$this->db = $db;
		$this->config = $config;
		$this->auth = $auth;
		$this->user = $user;
		$this->language = $language;
		$this->helper = $helper;
		$this->activity_table = $activity_table;
		$this->photos_table = $photos_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function is_enabled()
	{
		return (bool) $this->config['portfolio_pending_pm'];
	}

	/**
	 * Une photo vient d'être mise en attente de validation.
	 *
	 * @return int Nombre de destinataires prévenus
	 */
	public function photo_pending(array $photo, array $album)
	{
		if (!$this->is_enabled())
		{
			return 0;
		}
		$uploader = (int) $photo['user_id'];
		if ($this->recently_sent($uploader, (int) $photo['photo_id']))
		{
			return 0;
		}

		$recipients = array_diff($this->recipients(), [$uploader]);
		$sender = $this->sender();
		if (!$recipients || !$sender)
		{
			return 0;
		}

		if (!function_exists('submit_pm'))
		{
			include_once($this->root_path . 'includes/functions_privmsgs.' . $this->php_ext);
		}

		$abs = \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL;
		$this->language->add_lang('common', 'verturin/portfolio');
		$subject = $this->language->lang('PORTFOLIO_PENDING_PM_SUBJECT', $this->user->data['username']);
		$text = $this->language->lang('PORTFOLIO_PENDING_PM_BODY',
			$this->user->data['username'],
			$album['album_name'],
			$this->helper->route('verturin_portfolio_album', ['album_id' => (int) $album['album_id']], false, false, $abs),
			$this->helper->route('verturin_portfolio_queue', [], false, false, $abs)
		);
		$uid = $bitfield = '';
		$flags = 0;
		generate_text_for_storage($text, $uid, $bitfield, $flags, true, true, true);

		$address = [];
		foreach ($recipients as $user_id)
		{
			$address[(int) $user_id] = 'bcc';
		}
		$pm_data = [
			'from_user_id'		=> (int) $sender['user_id'],
			'from_user_ip'		=> (string) $sender['user_ip'],
			'from_username'		=> $sender['username'],
			'enable_sig'		=> false,
			'enable_bbcode'		=> true,
			'enable_smilies'	=> true,
			'enable_urls'		=> true,
			'icon_id'			=> 0,
			'bbcode_bitfield'	=> $bitfield,
			'bbcode_uid'		=> $uid,
			'message'			=> $text,
			'address_list'		=> ['u' => $address],
		];
		submit_pm('post', $subject, $pm_data, false);

		$this->db->sql_query('INSERT INTO ' . $this->activity_table . ' ' . $this->db->sql_build_array('INSERT', [
			'user_id'			=> $uploader,
			'photo_id'			=> (int) $photo['photo_id'],
			'actor_id'			=> $uploader,
			'activity_type'		=> self::TYPE,
			'activity_value'	=> (string) count($recipients),
			'activity_time'		=> time(),
			'activity_sent'		=> 1,	// jamais repris par le regroupement des notifications
		]));

		return count($recipients);
	}

	/**
	 * Un message a déjà été envoyé il y a peu pour ce membre et d'autres de ses photos attendent encore :
	 * c'est la suite du même envoi, les destinataires sont déjà prévenus.
	 */
	protected function recently_sent($uploader, $photo_id)
	{
		$sql = 'SELECT activity_time FROM ' . $this->activity_table . "
			WHERE activity_type = '" . self::TYPE . "'
				AND user_id = " . (int) $uploader . '
			ORDER BY activity_time DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$last = (int) $this->db->sql_fetchfield('activity_time');
		$this->db->sql_freeresult($result);
		if (!$last || $last < time() - self::THROTTLE)
		{
			return false;
		}

		$sql = 'SELECT COUNT(photo_id) AS total FROM ' . $this->photos_table . '
			WHERE user_id = ' . (int) $uploader . '
				AND photo_id <> ' . (int) $photo_id . '
				AND photo_status = ' . photo_manager::STATUS_PENDING;
		$result = $this->db->sql_query($sql);
		$waiting = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $waiting > 0;
	}

	/**
	 * @return int[] Membres à prévenir
	 */
	public function recipients()
	{
		$ids = self::id_list($this->config['portfolio_pending_pm_users']);

		$perms = [];
		if ($this->config['portfolio_pending_pm_admins'])
		{
			$perms[] = 'a_portfolio_manage';
		}
		if ($this->config['portfolio_pending_pm_mods'])
		{
			$perms[] = 'm_portfolio_approve';
		}
		foreach ($perms as $perm)
		{
			$list = $this->auth->acl_get_list(false, $perm);
			foreach ($list as $forum)
			{
				if (isset($forum[$perm]))
				{
					$ids = array_merge($ids, array_map('intval', $forum[$perm]));
				}
			}
		}

		$groups = self::id_list($this->config['portfolio_pending_pm_groups']);
		if ($groups)
		{
			$result = $this->db->sql_query('SELECT user_id FROM ' . USER_GROUP_TABLE . '
				WHERE user_pending = 0 AND ' . $this->db->sql_in_set('group_id', $groups));
			while ($row = $this->db->sql_fetchrow($result))
			{
				$ids[] = (int) $row['user_id'];
			}
			$this->db->sql_freeresult($result);
		}

		$ids = array_values(array_unique(array_filter($ids)));
		if (!$ids)
		{
			return [];
		}

		// Comptes actifs uniquement (ni robots, ni comptes désactivés)
		$out = [];
		$result = $this->db->sql_query('SELECT user_id FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('user_id', $ids) . '
				AND ' . $this->db->sql_in_set('user_type', [USER_NORMAL, USER_FOUNDER]));
		while ($row = $this->db->sql_fetchrow($result))
		{
			$out[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $out;
	}

	/**
	 * Expéditeur : l'administrateur qui a enregistré le réglage, sinon le premier fondateur.
	 */
	public function sender()
	{
		$where = (int) $this->config['portfolio_pending_pm_from']
			? 'user_id = ' . (int) $this->config['portfolio_pending_pm_from']
			: 'user_type = ' . USER_FOUNDER;
		$result = $this->db->sql_query_limit('SELECT user_id, username, user_ip FROM ' . USERS_TABLE . ' WHERE ' . $where . ' ORDER BY user_id', 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$row && (int) $this->config['portfolio_pending_pm_from'])
		{
			$result = $this->db->sql_query_limit('SELECT user_id, username, user_ip FROM ' . USERS_TABLE . ' WHERE user_type = ' . USER_FOUNDER . ' ORDER BY user_id', 1);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
		}

		return $row ?: null;
	}

	protected static function id_list($value)
	{
		return array_values(array_filter(array_map('intval', explode(',', (string) $value))));
	}
}
