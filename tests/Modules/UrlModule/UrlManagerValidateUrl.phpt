<?php

declare(strict_types=1);

namespace Tests\Modules\UrlModule;

use App\Model\Languages;
use App\Modules\UrlModule\Model;
use App\Modules\UrlModule\RedirectionsModel;
use App\Modules\UrlModule\UrlManager;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../../bootstrap.php';

/**
 * Integrační test nad in-memory SQLite: ověřuje, že UrlManager::validateUrl()
 * hlídá unikátnost URL napříč typy/jazyky (viz gotcha v docs/AI-Context/gotchas.md)
 * a že `$notInKey` správně vyloučí vlastní záznam při editaci.
 */
final class UrlManagerValidateUrlTest extends TestCase
{
	private Explorer $db;
	private UrlManager $urlManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->db = SqliteDatabase::create([
			'CREATE TABLE firecms_urls (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				languageId TEXT NOT NULL,
				type TEXT NOT NULL,
				key INTEGER NOT NULL,
				url TEXT NOT NULL
			)',
			'CREATE TABLE firecms_urlRedirections (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				languageId TEXT NOT NULL,
				oldUrl TEXT NOT NULL,
				newUrl TEXT NOT NULL
			)',
		]);

		$this->urlManager = new UrlManager(
			new Model($this->db),
			new RedirectionsModel($this->db),
			new LanguageService(new Languages($this->db)),
			$this->db,
		);
	}

	public function testUniqueUrlIsKeptAsIs(): void
	{
		Assert::same('clanek-test', $this->urlManager->validateUrl('Článek Test', 'article', 1));
	}

	public function testEditingOwnUrlDoesNotCollideWithItself(): void
	{
		$this->insertUrl('cs', 'article', 5, 'clanek-test');

		// notInKey === key, stejně jako to volá UrlManager::saveUrl() při editaci
		Assert::same('clanek-test', $this->urlManager->validateUrl('Článek Test', 'article', 5, 5));
	}

	public function testCollidingUrlGetsIncrementedSuffix(): void
	{
		$this->insertUrl('cs', 'article', 5, 'clanek-test');

		Assert::same('clanek-test-1', $this->urlManager->validateUrl('Článek Test', 'article', 7, 7));
	}

	public function testCollidingUrlSkipsMultipleTakenSuffixes(): void
	{
		$this->insertUrl('cs', 'article', 5, 'clanek-test');
		$this->insertUrl('cs', 'article', 8, 'clanek-test-1');

		Assert::same('clanek-test-2', $this->urlManager->validateUrl('Článek Test', 'article', 9, 9));
	}

	public function testUniquenessIsGlobalAcrossTypesAndLanguages(): void
	{
		// Pozor: validateUrl() nefiltruje podle `type` ani `languageId` — URL musí
		// být unikátní napříč celou tabulkou firecms_urls, ne jen v rámci
		// stejného typu/jazyka. Viz docs/AI-Context/gotchas.md.
		$this->insertUrl('en', 'page', 1, 'foo');

		Assert::same('foo-1', $this->urlManager->validateUrl('Foo', 'article', 2, 2));
	}

	private function insertUrl(string $languageId, string $type, int $key, string $url): void
	{
		$this->db->query(
			'INSERT INTO firecms_urls (languageId, type, key, url) VALUES (?, ?, ?, ?)',
			$languageId,
			$type,
			$key,
			$url,
		);
	}
}

(new UrlManagerValidateUrlTest())->run();
