<?php

declare(strict_types=1);

namespace Tests\LiveTranslator;

use LiveTranslator\Storage\File;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Regrese na "TypeError: Unsupported operand types: array + bool" v
 * Storage\File::__destruct() — nastala po kliknutí na "smazat" (erase) v Tracy panelu u
 * řetězce, který ještě NIKDY nebyl přeložen (žádný řádek pro něj v souboru neexistuje).
 *
 * Root cause: `removeTranslation()` uloží `$this->newTranslations[$original] = false`.
 * Pokud se `$original` nenajde v existujícím souboru (`array_search()` nic nenajde), zůstane
 * v `$originals`, a `__destruct()` se ho pak nepodmíněně pokusí zapsat jako NOVÝ řádek přes
 * `array($original) + $this->newTranslations[$original]` — tedy `array + false`, což je pod
 * PHP 8 fatální TypeError místo tichého no-opu, jaký to bylo očividně zamýšlené (smazat
 * něco, co nikdy neexistovalo, nemá co dělat).
 */
final class FileStorageRemoveUntranslatedTest extends TestCase
{
	private string $storageDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->storageDir = TEMP_DIR . '/livetranslator-remove-untranslated-' . uniqid();
		mkdir($this->storageDir, 0777, true);
	}

	public function testRemovingNeverTranslatedStringIsNoop(): void
	{
		$storage = new File($this->storageDir);

		Assert::noError(function () use ($storage) {
			$storage->removeTranslation('Nikdy nepřeložený řetězec', 'cs');
			$storage = null; // vyvolá __destruct(), kde bug reálně nastával
		});

		Assert::false(file_exists($this->storageDir . '/cs'));
	}

	public function testRemovingExistingTranslationStillWorks(): void
	{
		$storage = new File($this->storageDir);
		$storage->setTranslation('Ahoj', 'Hello', 'cs');
		$storage->setTranslation('Cau', 'Bye', 'cs');
		$storage = null;

		$storage = new File($this->storageDir);
		$storage->removeTranslation('Cau', 'cs');
		$storage = null;

		$storage = new File($this->storageDir);
		Assert::same('Hello', $storage->getTranslation('Ahoj', 'cs'));
		Assert::null($storage->getTranslation('Cau', 'cs'));
	}
}

(new FileStorageRemoveUntranslatedTest())->run();
