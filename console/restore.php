<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * php bin/phpbbcli.php portfolio:restore <fichier.zip> [--mode=merge|replace] [--owner=pseudo] [--force-owner=pseudo] [--config]
 */
class restore extends \phpbb\console\command\command
{
	/** @var \verturin\portfolio\service\archive */
	protected $archive;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	public function __construct(\phpbb\user $user, \verturin\portfolio\service\archive $archive, \phpbb\db\driver\driver_interface $db)
	{
		$this->archive = $archive;
		$this->db = $db;
		parent::__construct($user);
	}

	protected function configure()
	{
		$this->setName('portfolio:restore')
			->setDescription('Portfolio : importe une sauvegarde ou un export de membre')
			->addArgument('file', InputArgument::REQUIRED, 'Archive ZIP (chemin complet, ou nom d’un fichier de files/portfolio/backup/)')
			->addOption('mode', null, InputOption::VALUE_REQUIRED, 'merge (ajout) ou replace (remplace TOUTES les données du portfolio)', 'merge')
			->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Membre qui reçoit les albums dont le propriétaire est introuvable', '')
			->addOption('force-owner', null, InputOption::VALUE_REQUIRED, 'Attribue tous les albums importés à ce membre', '')
			->addOption('config', null, InputOption::VALUE_NONE, 'Restaure aussi les réglages (mode replace)');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);
		if (!\verturin\portfolio\service\archive::zip_available())
		{
			$io->error('L’extension PHP zip (ZipArchive) est requise.');

			return 1;
		}

		$file = $input->getArgument('file');
		if (!is_file($file))
		{
			$file = $this->archive->backup_path($file);
		}
		if (!$file)
		{
			$io->error('Archive introuvable.');

			return 1;
		}

		$manifest = $this->archive->inspect($file);
		if (!is_array($manifest))
		{
			$io->error('Archive invalide (format verturin-portfolio attendu).');

			return 1;
		}

		$mode = $input->getOption('mode') === 'replace' ? 'replace' : 'merge';
		$owner = $this->user_id($input->getOption('owner'));
		$force = $this->user_id($input->getOption('force-owner'));
		if ($owner < 0 || $force < 0)
		{
			$io->error('Membre introuvable.');

			return 1;
		}

		$io->text(sprintf('Archive « %s » : %d albums, %d photos (créée le %s sur %s).', $manifest['type'], $manifest['counts']['albums'], $manifest['counts']['photos'], $manifest['created'], $manifest['board_url']));
		if ($mode === 'replace' && $input->isInteractive() && !$io->confirm('Le mode replace SUPPRIME toutes les données actuelles du portfolio. Continuer ?', false))
		{
			return 1;
		}

		$stats = $this->archive->import($file, $mode, $owner, $force, (bool) $input->getOption('config'));
		if (isset($stats['error']))
		{
			$io->error($this->user->lang($stats['error']));

			return 1;
		}

		$io->success(sprintf('%d albums, %d photos, %d commentaires, %d notes importés. Fichiers manquants : %d. Membres introuvables : %d.', $stats['albums'], $stats['photos'], $stats['comments'], $stats['ratings'], $stats['missing_files'], $stats['unmapped_users']));

		return 0;
	}

	protected function user_id($username)
	{
		$username = trim((string) $username);
		if ($username === '')
		{
			return 0;
		}

		$sql = 'SELECT user_id FROM ' . USERS_TABLE . " WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'";
		$result = $this->db->sql_query($sql);
		$user_id = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		return $user_id ?: -1;
	}
}
