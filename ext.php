<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio;

class ext extends \phpbb\extension\base
{
	/** Types de notification fournis par l'extension */
	const NOTIFICATION_TYPES = [
		'verturin.portfolio.notification.type.mass_delete',
		'verturin.portfolio.notification.type.comment',
		'verturin.portfolio.notification.type.approved',
		'verturin.portfolio.notification.type.rating',
		'verturin.portfolio.notification.type.reaction',
		'verturin.portfolio.notification.type.digest',
	];

	/**
	 * phpBB 3.3.15 minimum, GD obligatoire.
	 */
	public function is_enableable()
	{
		$config = $this->container->get('config');

		return phpbb_version_compare($config['version'], '3.3.15', '>=')
			&& version_compare(PHP_VERSION, '7.2.0', '>=')
			&& extension_loaded('gd');
	}

	public function enable_step($old_state)
	{
		if ($old_state === false)
		{
			$manager = $this->container->get('notification_manager');
			foreach (self::NOTIFICATION_TYPES as $type)
			{
				$manager->enable_notifications($type);
			}
			$this->create_storage();

			return 'notifications';
		}

		return parent::enable_step($old_state);
	}

	public function disable_step($old_state)
	{
		if ($old_state === false)
		{
			$manager = $this->container->get('notification_manager');
			foreach (self::NOTIFICATION_TYPES as $type)
			{
				$manager->disable_notifications($type);
			}

			return 'notifications';
		}

		return parent::disable_step($old_state);
	}

	public function purge_step($old_state)
	{
		if ($old_state === false)
		{
			$manager = $this->container->get('notification_manager');
			foreach (self::NOTIFICATION_TYPES as $type)
			{
				try
				{
					$manager->purge_notifications($type);
				}
				catch (\phpbb\notification\exception $e)
				{
					// Type déjà retiré : rien à faire
				}
			}

			return 'notifications';
		}

		if ($old_state === 'notifications')
		{
			// La purge supprime aussi les fichiers photo (les tables sont supprimées par les migrations)
			$this->remove_dir($this->storage_root());

			return 'files';
		}

		return parent::purge_step($old_state);
	}

	protected function storage_root()
	{
		return $this->container->getParameter('core.root_path') . 'files/portfolio';
	}

	protected function create_storage()
	{
		$root = $this->storage_root();
		foreach (['', '/source', '/medium', '/thumb', '/cache', '/wm', '/export', '/backup'] as $dir)
		{
			if (!is_dir($root . $dir))
			{
				@mkdir($root . $dir, 0755, true);
			}
		}

		// Interdit tout accès direct : les photos passent par le contrôleur (droits vérifiés)
		if (!file_exists($root . '/.htaccess'))
		{
			@file_put_contents($root . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n");
		}
		if (!file_exists($root . '/index.htm'))
		{
			@file_put_contents($root . '/index.htm', '');
		}
	}

	protected function remove_dir($dir)
	{
		if (!is_dir($dir))
		{
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($items as $item)
		{
			$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		}
		@rmdir($dir);
	}
}
