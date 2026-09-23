<?php

declare(strict_types=1);

namespace Tests\Localization;

use App\Localization\ChainTranslatorStorage;
use App\Localization\ReadOnlyFileStorage;
use LiveTranslator\Storage\File;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Hlavní úložiště překladů (data/localization/) + read-only překlady tématu a pluginů. Nad dočasnými kopiemi
 * souborů, aby test nezapisoval do skutečných překladů. Ověřuje i skutečné soubory PetHotel.
 */
final class ChainTranslatorStorageTest extends TestCase
{
	private string $mainDir;
	private string $themeDir;
	private string $pluginDir;


	protected function setUp(): void
	{
		$this->mainDir = TEMP_DIR . '/main';
		$this->themeDir = TEMP_DIR . '/theme';
		$this->pluginDir = TEMP_DIR . '/plugin';
		foreach ([$this->mainDir, $this->themeDir, $this->pluginDir] as $dir) {
			@mkdir($dir);
			array_map('unlink', glob($dir . '/*') ?: []);
		}

		file_put_contents($this->mainDir . '/cs.front', serialize(['Search', 'Vyhledat']) . "\n");
		file_put_contents(
			$this->pluginDir . '/cs.front',
			serialize(['Search', 'Hledat']) . "\n"
				. serialize(['Pet accommodation', 'Ubytování pro mazlíčky']) . "\n"
				. serialize(['Show all hotels', 'Všechny hotely']) . "\n",
		);
		file_put_contents($this->themeDir . '/cs.front', serialize(['Show all hotels', 'Zobrazit všechny hotely']) . "\n");
	}


	private function createStorage(): ChainTranslatorStorage
	{
		return new ChainTranslatorStorage(
			new File($this->mainDir),
			[new ReadOnlyFileStorage($this->themeDir)],
			[new ReadOnlyFileStorage($this->pluginDir)],
		);
	}


	public function testMainStorageWinsOverPlugin(): void
	{
		$storage = $this->createStorage();

		Assert::same('Vyhledat', $storage->getTranslation('Search', 'cs', 0, 'front'));
		Assert::same('Ubytování pro mazlíčky', $storage->getTranslation('Pet accommodation', 'cs', 0, 'front'));
		Assert::null($storage->getTranslation('Unknown', 'cs', 0, 'front'));
	}


	public function testThemeWinsOverPlugin(): void
	{
		$storage = $this->createStorage();

		Assert::same('Zobrazit všechny hotely', $storage->getTranslation('Show all hotels', 'cs', 0, 'front'));
		Assert::same(['Zobrazit všechny hotely'], $storage->getAllTranslations('cs', 'front')['Show all hotels']);
	}


	public function testAllTranslationsAreMergedWithMainPriority(): void
	{
		$all = $this->createStorage()->getAllTranslations('cs', 'front');

		Assert::same(['Vyhledat'], $all['Search']);
		Assert::same(['Ubytování pro mazlíčky'], $all['Pet accommodation']);
	}


	public function testWritesGoToMainStorageOnly(): void
	{
		$storage = $this->createStorage();
		$storage->setTranslation('Pet accommodation', 'Upraveno v projektu', 'cs', 0, 'front');
		unset($storage); // File zapisuje změny v __destruct()
		gc_collect_cycles();

		Assert::contains('Upraveno v projektu', (string) file_get_contents($this->mainDir . '/cs.front'));
		Assert::notContains('Upraveno v projektu', (string) file_get_contents($this->pluginDir . '/cs.front'));
		Assert::same('Upraveno v projektu', $this->createStorage()->getTranslation('Pet accommodation', 'cs', 0, 'front'));
	}


	public function testPluginStorageDoesNotCreateMissingFiles(): void
	{
		$storage = $this->createStorage();

		Assert::null($storage->getTranslation('Search', 'de', 0, 'admin'));
		Assert::false(is_file($this->pluginDir . '/de.admin'));
	}


	public function testPluginStorageIsReadOnly(): void
	{
		Assert::exception(
			fn() => (new ReadOnlyFileStorage($this->pluginDir))->setTranslation('Search', 'X', 'cs', 0, 'front'),
			\LogicException::class,
		);
	}


	public function testThemeTranslationFileIsReadable(): void
	{
		$theme = new ReadOnlyFileStorage(__DIR__ . '/../../theme/data/localization');

		Assert::same('Jak to funguje', $theme->getTranslation('How it works', 'cs', 0, 'front'));
	}


	public function testPetHotelTranslationFilesAreReadable(): void
	{
		$petHotel = new ReadOnlyFileStorage(__DIR__ . '/../../theme/Plugins/PetHotel/data/localization');

		foreach (['admin', 'front'] as $namespace) {
			$all = $petHotel->getAllTranslations('cs', $namespace);
			Assert::true(count($all) > 50, "cs.$namespace");
			foreach ($all as $original => $variants) {
				Assert::type('string', $original);
				Assert::type('string', $variants[0]);
			}
		}

		Assert::same('Ubytovací jednotky', $petHotel->getTranslation('Facilities', 'cs', 0, 'admin'));
		Assert::same('Ubytování pro mazlíčky', $petHotel->getTranslation('Pet accommodation', 'cs', 0, 'front'));
	}
}

(new ChainTranslatorStorageTest())->run();
