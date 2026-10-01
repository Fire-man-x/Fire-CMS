<?php
declare(strict_types=1);

namespace App\Components\FileManager\Console;

use App\Components\FileManager\Exceptions\HashException;
use App\Components\FileManager\Files\HashFile;
use App\Components\FileManager\Storages\FlysystemStorage;
use App\Model\Database\Files;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Nette\Database\Table\ActiveRow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Převede soubory správce souborů (`firecms_files`) z dřívějších struktur na lokálním disku do úložiště
 * FlysystemStorage (lokální disk i S3) ve struktuře `<h0>/<h1>/<hash>.<přípona>`.
 *
 * Ve zdrojovém adresáři se hledá (v tomto pořadí):
 * - `<h0>/<h1>/<hash>.<přípona>` - už nová struktura (při převodu na S3 se jen zkopíruje)
 * - `<h01>/<h23>/<hash>.<přípona>` - nejstarší úložiště (dva znaky hashe na úroveň)
 * - `<h0>/<h1>/<původní název>.<přípona>` - HashFileStorage do 2026-09 (název souboru = originalName, resp. newName)
 *
 * Staré náhledy (`cache/`) se nepřevádějí - vygenerují se znovu.
 */
#[AsCommand(
	name: 'files:migrate',
	description: 'Převede soubory správce souborů z dřívějších struktur do úložiště fileManager (lokální disk nebo S3)',
)]
final class MigrateFilesCommand extends Command
{
	public function __construct(
		private readonly FlysystemStorage $storage,
		private readonly Files $filesModel,
		private readonly ?string $defaultSource = null,
	)
	{
		parent::__construct();
	}


	protected function configure(): void
	{
		$this
			->addOption('source', null, InputOption::VALUE_REQUIRED, 'Adresář se soubory v dřívější struktuře', $this->defaultSource)
			->addOption('delete-source', null, InputOption::VALUE_NONE, 'Po úspěšném převodu smazat soubor ze zdroje (na stejném disku = přesun)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Jen vypsat, co by se udělalo');
	}


	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$sourceDir = $input->getOption('source');
		if (!is_string($sourceDir) || !is_dir($sourceDir)) {
			$io->error('Zdrojový adresář neexistuje, zadejte ho přes --source.');
			return self::FAILURE;
		}

		$dryRun = (bool) $input->getOption('dry-run');
		$deleteSource = (bool) $input->getOption('delete-source');
		$source = new Filesystem(new LocalFilesystemAdapter($sourceDir));
		$target = $this->storage->getFilesystem();

		$counts = ['hotovo' => 0, 'převedeno' => 0, 'chybí' => 0, 'chyba' => 0];
		foreach ($this->filesModel->findAll()->order('id') as $row) {
			$id = self::column($row, 'id');
			$targetPath = self::column($row, 'diskName');
			try {
				$file = $this->filesModel->toFileEntity($row);
				$targetPath = $this->storage->getOriginalPath($file);
				if ($target->fileExists($targetPath)) {
					$counts['hotovo']++;
					continue;
				}

				$sourcePath = $this->findSource($source, $file, $row);
				if ($sourcePath === null) {
					$counts['chybí']++;
					$io->warning(sprintf('#%s %s: soubor ve zdroji nenalezen', $id, $targetPath));
					continue;
				}

				$io->writeln(sprintf('#%s %s -> %s', $id, $sourcePath, $targetPath), OutputInterface::VERBOSITY_VERBOSE);
				if (!$dryRun) {
					$this->copy($source, $sourcePath, $target, $targetPath, $file->getMimeType());
					if ($deleteSource) {
						$source->delete($sourcePath);
					}
				}

				$counts['převedeno']++;
			} catch (FilesystemException | HashException $e) {
				$counts['chyba']++;
				$io->error(sprintf('#%s %s: %s', $id, $targetPath, $e->getMessage()));
			}
		}

		$io->table(array_keys($counts), [array_values($counts)]);
		if ($dryRun) {
			$io->note('--dry-run: nic se nezměnilo.');
		}

		$io->note('Staré náhledy v ' . $sourceDir . '/' . FlysystemStorage::CacheDirectory . '/ už se nepoužívají - vygenerují se znovu v nové struktuře. Smažte je, až ověříte převod.');

		return $counts['chyba'] > 0 ? self::FAILURE : self::SUCCESS;
	}


	/**
	 * @throws FilesystemException
	 */
	private function findSource(FilesystemOperator $source, HashFile $file, ActiveRow $row): ?string
	{
		$hash = $file->getHash();
		$extension = $file->getExtension() !== '' ? '.' . $file->getExtension() : '';
		$candidates = [
			$this->storage->getOriginalPath($file),
			substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . $extension,
			$hash[0] . '/' . $hash[1] . '/' . self::column($row, 'originalName') . $extension,
		];
		$newName = self::column($row, 'newName');
		if ($newName !== '') {
			$candidates[] = $hash[0] . '/' . $hash[1] . '/' . $newName . $extension;
		}

		foreach ($candidates as $candidate) {
			if ($source->fileExists($candidate)) {
				return $candidate;
			}
		}

		return null;
	}


	private static function column(ActiveRow $row, string $column): string
	{
		$value = $row[$column];

		return is_scalar($value) ? (string) $value : '';
	}


	/**
	 * @throws FilesystemException
	 */
	private function copy(FilesystemOperator $source, string $sourcePath, FilesystemOperator $target, string $targetPath, string $mimeType): void
	{
		$stream = $source->readStream($sourcePath);
		try {
			$target->writeStream($targetPath, $stream, ['mimetype' => $mimeType]);
		} finally {
			if (is_resource($stream)) {
				fclose($stream);
			}
		}
	}
}
