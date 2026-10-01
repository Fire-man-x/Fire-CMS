<?php

declare(strict_types=1);

namespace Tests\LiveTranslator;

use LiveTranslator\Storage\File;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Regrese na "ErrorException: unserialize(): Extra data starting at offset 39 of 40 bytes".
 * Storage\File::getAllTranslations()/getTranslation() volaly unserialize() na celý řádek ze
 * souboru VČETNĚ koncového "\n" z fgets()/file() — unserialize() bere cokoliv za koncem
 * serializovaného pole (i jediný bajt navíc) jako "extra data" a vyhodí warning. Tracy i Nette
 * Tester (viz Environment::setupErrors()) takové warningy v dev/testovacím módu převádí na
 * ErrorException, takže appka spadla při každém sáhnutí na reálný přeložený řetězec.
 *
 * Chyba se projevila konkrétně na víceznakových UTF-8 řetězcích (diakritika) jen proto, že
 * `s:N:"..."` udává délku v BAJTECH, ne ve znacích — u čistě ASCII textu by "extra data"
 * warning nastal úplně stejně, jen by ho bylo hůř vidět v příkladech.
 */
final class FileStorageUnserializeTest extends TestCase
{
	private File $storage;

	protected function setUp(): void
	{
		parent::setUp();

		$storageDir = TEMP_DIR . '/livetranslator-file-' . uniqid();
		mkdir($storageDir, 0777, true);

		file_put_contents($storageDir . '/cs.admin', serialize(['Main', 'Hlavní']) . "\n");

		$this->storage = new File($storageDir);
	}

	public function testGetAllTranslationsDoesNotWarnOnMultibyteString(): void
	{
		Assert::noError(function () {
			Assert::same(['Main' => ['Hlavní']], $this->storage->getAllTranslations('cs', 'admin'));
		});
	}

	public function testGetTranslationDoesNotWarnOnMultibyteString(): void
	{
		Assert::noError(function () {
			Assert::same('Hlavní', $this->storage->getTranslation('Main', 'cs', 0, 'admin'));
		});
	}
}

(new FileStorageUnserializeTest())->run();
