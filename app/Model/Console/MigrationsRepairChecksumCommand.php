<?php declare(strict_types = 1);

namespace App\Model\Console;

use Nette\Database\Explorer;
use Nette\InvalidArgumentException;
use Nextras\Migrations\Engine\Finder;
use Nextras\Migrations\Entities\Group;
use Nextras\Migrations\IConfiguration;
use Nextras\Migrations\LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
	name: 'migrations:repairChecksum',
	description: 'Repair migrations checksums'
)]
final class MigrationsRepairChecksumCommand extends Command
{

	public function __construct(protected Explorer $db, protected IConfiguration $config)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		$this->setHelp('This command allows you to clear cache');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$progressBar = new ProgressBar($io);

		$io->text('Repair checksum in migrations');

		try {
			$migrations = $this->db->table('migrations')->fetchAssoc('group|file->');
		}catch(InvalidArgumentException $e){
			$io->warning("Table 'migrations' doesn't exist.");
			return self::SUCCESS;
		}
		$sum = 0;
		foreach($migrations as $migrationGroups) {
			$sum += count($migrationGroups);
		}
		$progressBar->start($sum);

		/** @var list<Group> $groups */
		$groups = array_values($this->config->getGroups());
		$groupsByName = [];
		foreach ($groups as $group) {
			$groupsByName[$group->name] = $group;
		}
		$extensionsHandlers = [];
		foreach ($this->config->getExtensionHandlers() as $ext => $handler) {
			$extensionsHandlers[$ext] = $handler;
		}

		$finder = new Finder();
		$files = $finder->find($groups, array_keys($extensionsHandlers));
		$assoc = [];
		foreach ($files as $file) {
			$assoc[$file->group->name][$file->name] = $file;
		}
		$files = $assoc;

		foreach ($migrations as $groupName => $mg) {
			if (!isset($groupsByName[$groupName])) {
				throw new LogicException(sprintf(
					'Existing migrations depend on unknown group "%s".',
					$groupName
				));
			}

			$group = $groupsByName[$groupName];
			foreach ($mg as $filename => $migration) {
				$progressBar->advance();
				if (isset($files[$groupName][$filename])) {
					$file = $files[$groupName][$filename];
					if ($migration->checksum !== $file->checksum) {
						$io->text(sprintf('Migration "%s/%s" changed file checksum from "%s" to "%s".',
							$groupName, $filename, $migration->checksum, $file->checksum));
						$this->db->table('migrations')->where(['id' => $migration->id])->update(['checksum'=>$file->checksum]);
					}
					unset($files[$groupName][$filename]);

				} /*elseif ($group->enabled) {
					throw new LogicException(sprintf(
						'Previously executed migration "%s/%s" is missing.',
						$groupName, $filename
					));
				}*/
			}
		}

		$io->success('Migrations repaired');

		return self::SUCCESS;
	}

}
