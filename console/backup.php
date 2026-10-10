<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * php bin/phpbbcli.php portfolio:backup
 * Sauvegarde complète en ligne de commande (pas de limite de temps du navigateur).
 */
class backup extends \phpbb\console\command\command
{
	/** @var \verturin\portfolio\service\archive */
	protected $archive;

	public function __construct(\phpbb\user $user, \verturin\portfolio\service\archive $archive)
	{
		$this->archive = $archive;
		parent::__construct($user);
	}

	protected function configure()
	{
		$this->setName('portfolio:backup')
			->setDescription('Portfolio : crée une sauvegarde complète (données + photos) dans files/portfolio/backup/');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);
		if (!\verturin\portfolio\service\archive::zip_available())
		{
			$io->error('L’extension PHP zip (ZipArchive) est requise.');

			return 1;
		}

		$name = $this->archive->backup_full();
		$io->success('Sauvegarde créée : files/portfolio/backup/' . $name);

		return 0;
	}
}
