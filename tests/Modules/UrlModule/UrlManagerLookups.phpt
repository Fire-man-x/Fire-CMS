<?php

declare(strict_types=1);

namespace Tests\Modules\UrlModule;

use App\Model\Languages;
use App\Modules\UrlModule\Model;
use App\Modules\UrlModule\RedirectionsModel;
use App\Modules\UrlModule\UrlManager;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\InvalidArgumentException;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../../bootstrap.php';

/**
 * Zbylé čtecí metody UrlManageru (getUrlInfoByTypeAndKey/existUrlByTypeAndKey/
 * getUrlByTypeAndKey/getRedirectionInfoByUrl) — jen SELECT dotazy, takže je jde otestovat
 * stejným SQLite Explorerem jako UrlManagerValidateUrl.phpt. `saveUrl()`/insert větve
 * netestovány, protože interně volají BaseModel::insert(), které je na MySQL specifické
 * (SELECT LAST_INSERT_ID()) — viz docs/AI-Context/gotchas.md.
 */
final class UrlManagerLookupsTest extends TestCase
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

	public function testGetUrlInfoByTypeAndKeyReturnsMatchingRow(): void
	{
		$this->insertUrl('cs', 'article', 10, 'clanek-test');

		$row = $this->urlManager->getUrlInfoByTypeAndKey('article', 10, 'cs');

		Assert::same('clanek-test', $row['url']);
	}

	public function testGetUrlInfoByTypeAndKeyThrowsWhenMissing(): void
	{
		Assert::exception(
			fn() => $this->urlManager->getUrlInfoByTypeAndKey('article', 999, 'cs'),
			InvalidArgumentException::class,
		);
	}

	public function testExistUrlByTypeAndKey(): void
	{
		$this->insertUrl('cs', 'article', 10, 'clanek-test');

		Assert::true($this->urlManager->existUrlByTypeAndKey('article', 10, 'cs'));
		Assert::false($this->urlManager->existUrlByTypeAndKey('article', 999, 'cs'));
	}

	public function testGetUrlByTypeAndKey(): void
	{
		$this->insertUrl('cs', 'category', 3, 'kategorie-test');

		Assert::same('kategorie-test', $this->urlManager->getUrlByTypeAndKey('category', 3, 'cs'));
	}

	public function testGetRedirectionInfoByUrlReturnsMatchingRow(): void
	{
		$this->insertRedirection('cs', 'stary-clanek', 'clanek-test');

		$row = $this->urlManager->getRedirectionInfoByUrl('stary-clanek', 'cs');

		Assert::same('clanek-test', $row['newUrl']);
	}

	public function testGetRedirectionInfoByUrlThrowsWhenMissing(): void
	{
		Assert::exception(
			fn() => $this->urlManager->getRedirectionInfoByUrl('neexistujici-stara-url', 'cs'),
			InvalidArgumentException::class,
		);
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

	private function insertRedirection(string $languageId, string $oldUrl, string $newUrl): void
	{
		$this->db->query(
			'INSERT INTO firecms_urlRedirections (languageId, oldUrl, newUrl) VALUES (?, ?, ?)',
			$languageId,
			$oldUrl,
			$newUrl,
		);
	}
}

(new UrlManagerLookupsTest())->run();
