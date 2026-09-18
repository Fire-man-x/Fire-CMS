<?php

declare(strict_types=1);

namespace Tests\Router;

use App\Model\Domains;
use App\Model\Languages;
use App\Modules\UrlModule\Model;
use App\Modules\UrlModule\RedirectionsModel;
use App\Modules\UrlModule\UrlManager;
use App\Router\CustomRouter;
use App\Service\DomainService;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\RequestFactory;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Integrační test nad in-memory SQLite. `CustomRouter::match()` je klíčová routovací logika
 * jádra (viz docs/AI-Context/gotchas.md) — 2026-09-17 v ní byl opraven bug, kdy se spočtený
 * presenter nikdy nezapisoval do vráceného pole. Cílem je hlídat regresi přesně v týhle
 * finální části metody a zdokumentovat netriviální chování rozpoznávání jazyka.
 */
final class CustomRouterTest extends TestCase
{
	private Explorer $db;
	private CustomRouter $router;

	protected function setUp(): void
	{
		parent::setUp();

		$this->db = SqliteDatabase::create([
			'CREATE TABLE firecms_languages (
				language_id TEXT PRIMARY KEY,
				active INTEGER NOT NULL DEFAULT 1,
				"default" INTEGER NOT NULL DEFAULT 0,
				position INTEGER NOT NULL,
				name TEXT NOT NULL,
				shortcut TEXT NOT NULL
			)',
			'CREATE TABLE firecms_domains (
				domain_id INTEGER PRIMARY KEY AUTOINCREMENT,
				language_id TEXT NOT NULL,
				domain TEXT NOT NULL,
				active INTEGER NOT NULL DEFAULT 1,
				"default" INTEGER NOT NULL DEFAULT 1,
				position INTEGER NOT NULL DEFAULT 0
			)',
			'CREATE TABLE firecms_urls (
				url_id INTEGER PRIMARY KEY AUTOINCREMENT,
				language_id TEXT NOT NULL,
				type TEXT NOT NULL,
				key INTEGER NOT NULL,
				url TEXT NOT NULL
			)',
			'CREATE TABLE firecms_urlRedirections (
				url_redirection_id INTEGER PRIMARY KEY AUTOINCREMENT,
				language_id TEXT NOT NULL,
				old_url TEXT NOT NULL,
				new_url TEXT NOT NULL
			)',
		]);

		$this->insertLanguage('cs', active: true, default: true, position: 1);
		$this->insertLanguage('en', active: true, default: false, position: 2);

		$urlManager = new UrlManager(
			new Model($this->db),
			new RedirectionsModel($this->db),
			new LanguageService(new Languages($this->db)),
			$this->db,
		);

		$this->router = new CustomRouter(
			$urlManager,
			new LanguageService(new Languages($this->db)),
			new DomainService(new Domains($this->db)),
		);
	}

	public function testDirectHitOnDefaultLanguageFillsAllParams(): void
	{
		$this->insertUrl('cs', 'article', 10, 'clanek-test');

		$params = $this->router->match(RequestFactory::fromUrl('http://example.com/clanek-test'));

		Assert::same([
			'action' => 'detail',
			'presenter' => 'Front:Articles',
			'locale' => 'cs',
			'id' => 10,
		], $params);
	}

	public function testUnknownUrlReturnsNull(): void
	{
		Assert::null($this->router->match(RequestFactory::fromUrl('http://example.com/nic-takoveho')));
	}

	public function testLocalePrefixWithoutOwnDomainStripsPrefixAndResolvesLanguage(): void
	{
		$this->insertUrl('en', 'category', 2, 'some-page');

		$params = $this->router->match(RequestFactory::fromUrl('http://example.com/en/some-page'));

		Assert::same([
			'action' => 'detail',
			'presenter' => 'Front:Categories',
			'locale' => 'en',
			'id' => 2,
		], $params);
	}

	public function testUnregisteredTwoLetterPrefixFailsHardInsteadOfFallingBackToPlainLookup(): void
	{
		// Past: URL segment tvaru "xx/..." je vždy interpretován jako pokus o jazykový
		// prefix (viz regex v CustomRouter::match()) — pokud "xx" není aktivní jazyk,
		// routa se vůbec nezkusí najít jako obyčejná URL, match() rovnou vrátí null.
		$this->insertUrl('cs', 'article', 1, 'xx/nejaky-clanek');

		Assert::null($this->router->match(RequestFactory::fromUrl('http://example.com/xx/nejaky-clanek')));
	}

	public function testOldPrefixedLinkRedirectsToLanguagesOwnDomain(): void
	{
		$this->insertDomain('en', 'en.example.com', active: true, default: true);

		$params = $this->router->match(RequestFactory::fromUrl('http://example.com/en/some-page?foo=bar'));

		Assert::same('Front:Redirect', $params['presenter'] ?? null);
		Assert::same('default', $params['action'] ?? null);
		Assert::contains('http://en.example.com/some-page', $params['url'] ?? '');
	}

	public function testLanguageResolvedByRequestDomainTakesPrecedenceOverUrlPrefix(): void
	{
		$this->insertDomain('en', 'en.example.com', active: true, default: true);
		$this->insertUrl('en', 'article', 5, 'some-page');

		$params = $this->router->match(RequestFactory::fromUrl('http://en.example.com/some-page'));

		Assert::same([
			'action' => 'detail',
			'presenter' => 'Front:Articles',
			'locale' => 'en',
			'id' => 5,
		], $params);
	}

	private function insertLanguage(string $languageId, bool $active, bool $default, int $position): void
	{
		$this->db->query(
			'INSERT INTO firecms_languages (language_id, active, "default", position, name, shortcut) VALUES (?, ?, ?, ?, ?, ?)',
			$languageId,
			$active,
			$default,
			$position,
			$languageId,
			$languageId,
		);
	}

	private function insertDomain(string $languageId, string $domain, bool $active, bool $default): void
	{
		$this->db->query(
			'INSERT INTO firecms_domains (language_id, domain, active, "default") VALUES (?, ?, ?, ?)',
			$languageId,
			$domain,
			$active,
			$default,
		);
	}

	private function insertUrl(string $languageId, string $type, int $key, string $url): void
	{
		$this->db->query(
			'INSERT INTO firecms_urls (language_id, type, key, url) VALUES (?, ?, ?, ?)',
			$languageId,
			$type,
			$key,
			$url,
		);
	}
}

(new CustomRouterTest())->run();
