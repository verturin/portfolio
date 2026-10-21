<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * php bin/phpbbcli.php portfolio:migrate-gallery [--user=pseudo | --group=ID] [--simulate] [--no-public] [--keep-exif] [--bbcode]
 * Bascule phpBB Gallery → Portfolio sans limite de temps (grosses galeries).
 */
class migrate_gallery extends \phpbb\console\command\command
{
	/** @var \verturin\portfolio\service\gallery_migrator */
	protected $migrator;

	/** @var \verturin\portfolio\service\settings */
	protected $settings;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	public function __construct(\phpbb\user $user, \verturin\portfolio\service\gallery_migrator $migrator, \verturin\portfolio\service\settings $settings, \phpbb\db\driver\driver_interface $db)
	{
		$this->migrator = $migrator;
		$this->settings = $settings;
		$this->db = $db;
		parent::__construct($user);
	}

	protected function configure()
	{
		$this->setName('portfolio:migrate-gallery')
			->setDescription('Portfolio : copie les photos de phpBB Gallery (un membre, un groupe ou tout le forum)')
			->addOption('user', null, InputOption::VALUE_REQUIRED, 'Nom du membre à basculer', '')
			->addOption('group', null, InputOption::VALUE_REQUIRED, 'ID du groupe à basculer', 0)
			->addOption('simulate', null, InputOption::VALUE_NONE, 'Affiche ce qui serait copié, sans rien modifier')
			->addOption('no-public', null, InputOption::VALUE_NONE, 'Ne pas copier les photos des albums publics (avec --user ou --group)')
			->addOption('keep-exif', null, InputOption::VALUE_NONE, 'Copier les originaux tels quels, sans retirer les données EXIF/GPS')
			->addOption('visibility', null, InputOption::VALUE_REQUIRED, 'Visibilité des albums créés : registered (membres inscrits, défaut), keep (celle de phpBB Gallery) ou private', '')
			->addOption('download', null, InputOption::VALUE_NONE, 'Autoriser le téléchargement des originaux dans les albums créés (interdit par défaut)')
			->addOption('bbcode', null, InputOption::VALUE_NONE, 'Faire passer ensuite le BBCode [image] par le Portfolio');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);
		if (!$this->migrator->gallery_available())
		{
			$io->error('Les tables de phpBB Gallery sont introuvables.');

			return 1;
		}

		$scope = 'all';
		$value = 0;
		if ($input->getOption('user') !== '')
		{
			$scope = 'user';
			$sql = 'SELECT user_id FROM ' . USERS_TABLE . " WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($input->getOption('user'))) . "'";
			$result = $this->db->sql_query($sql);
			$value = (int) $this->db->sql_fetchfield('user_id');
			$this->db->sql_freeresult($result);
			if (!$value)
			{
				$io->error('Membre introuvable.');

				return 1;
			}
		}
		else if ((int) $input->getOption('group'))
		{
			$scope = 'group';
			$value = (int) $input->getOption('group');
		}

		$users = $scope === 'all' ? null : $this->migrator->scope_users($scope, $value);
		$public = !$input->getOption('no-public');
		$plan = $this->migrator->plan($users, $public);
		$io->text(sprintf('Périmètre : %s — %d photos dans %d albums, %d déjà basculées, %d à copier.', $scope, $plan['images'], $plan['albums'], $plan['done'], $plan['remaining']));

		if ($input->getOption('simulate'))
		{
			$rows = [];
			foreach ($plan['per_user'] as $row)
			{
				$rows[] = [$row['username'], $row['total'], $row['done']];
			}
			$io->table(['Membre', 'Photos', 'Déjà basculées'], $rows);

			return 0;
		}

		$strip = !$input->getOption('keep-exif') && (bool) $this->settings['portfolio_strip_exif'];
		$batch = max(1, (int) $this->settings['portfolio_migrate_batch']);

		// Albums créés : réglage de l'ACP, sinon membres inscrits et sans téléchargement (v1.9.8)
		$visibility = (string) $input->getOption('visibility');
		if ($visibility === '')
		{
			$visibility = (string) $this->settings['portfolio_migrate_visibility'];
		}
		if (!in_array($visibility, ['registered', 'keep', 'private'], true))
		{
			$visibility = 'registered';
		}
		$download = $input->getOption('download') || (int) $this->settings['portfolio_migrate_download'];
		$this->migrator->set_album_defaults($visibility, $download);
		$output->writeln('Albums créés : visibilité ' . $visibility . ', téléchargement des originaux ' . ($download ? 'autorisé' : 'interdit'));
		$copied = 0;
		$skipped = 0;
		do
		{
			$result = $this->migrator->run_batch($users, $public, $batch, $strip);
			$copied += $result['migrated'];
			$skipped += $result['skipped'];
			$io->text(sprintf('… %d / %d', $result['total'] - $result['remaining'], $result['total']));
		}
		while (!$result['done'] && ($result['migrated'] || $result['skipped']));

		if ($input->getOption('bbcode'))
		{
			$this->migrator->install_bbcode();
			$io->text('BBCode [image] repris par le Portfolio.');
		}

		$io->success(sprintf('%d photos copiées, %d sans fichier (ignorées).', $copied, $skipped));

		return 0;
	}
}
