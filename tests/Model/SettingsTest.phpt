<?php

declare(strict_types=1);

namespace Tests\Model;

use App\Model\Database\Languages;
use App\Model\Database\Settings;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Nastavení (structures/20261015120000.sql): e-mail a telefon v obou tabulkách, prázdná hodnota jazyka = globální.
 */
final class SettingsTest extends TestCase
{
	private Explorer $db;
	private Settings $settings;


	protected function setUp(): void
	{
		$this->db = SqliteDatabase::create([
			'CREATE TABLE firecms_languages (
				languageId TEXT PRIMARY KEY, active INTEGER NOT NULL DEFAULT 1, "default" INTEGER NOT NULL DEFAULT 0,
				position INTEGER NOT NULL, name TEXT NOT NULL, shortcut TEXT NOT NULL
			)',
			'CREATE TABLE firecms_settings (
				id INTEGER PRIMARY KEY AUTOINCREMENT, imageResolution TEXT NOT NULL, themePath TEXT NULL, mainEmail TEXT NULL,
				contactPhone TEXT NULL, mapLatitude NUMERIC NULL, mapLongitude NUMERIC NULL
			)',
			'CREATE TABLE firecms_settingDescriptions (
				settingId INTEGER NOT NULL, languageId TEXT NOT NULL, mainTitle TEXT NULL, mainDescription TEXT NULL,
				mainEmail TEXT NULL, contactPhone TEXT NULL, contactAddress TEXT NULL, seoTitle TEXT NULL,
				seoDescription TEXT NULL, seoKeywords TEXT NULL, PRIMARY KEY (settingId, languageId)
			)',
		]);
		$this->db->query("INSERT INTO firecms_languages (languageId, active, \"default\", position, name, shortcut) VALUES ('cs', 1, 1, 1, 'Čeština', 'CS'), ('en', 1, 0, 2, 'English', 'EN'), ('de', 1, 0, 3, 'Deutsch', 'DE')");
		$this->db->query("INSERT INTO firecms_settings (id, imageResolution, themePath, mainEmail, contactPhone) VALUES (1, '1000x1000', 'default', 'info@example.com', '+420 111 222 333')");
		$this->db->query("INSERT INTO firecms_settingDescriptions (settingId, languageId, mainTitle, mainEmail, contactPhone) VALUES (1, 'cs', 'Web', 'cs@example.com', NULL), (1, 'en', 'Site', NULL, '+44 20 1234')");

		$this->settings = new Settings($this->db, new LanguageService(new Languages($this->db)));
	}


	public function testLanguageValueWinsOverGlobal(): void
	{
		$cs = $this->settings->getAllForLanguage('cs');
		Assert::same('cs@example.com', $cs['main_email']);

		$en = $this->settings->getAllForLanguage('en');
		Assert::same('+44 20 1234', $en['contact_phone']);
	}


	public function testEmptyLanguageValueFallsBackToGlobal(): void
	{
		Assert::same('+420 111 222 333', $this->settings->getAllForLanguage('cs')['contact_phone']);
		Assert::same('info@example.com', $this->settings->getAllForLanguage('en')['main_email']);
		Assert::same('info@example.com', $this->settings->getByKey('main_email', 'en'));
		Assert::same('+420 111 222 333', $this->settings->getByKey('contact_phone'));
	}


	public function testLanguageWithoutRowUsesDefaultLanguageThenGlobal(): void
	{
		$de = $this->settings->getAllForLanguage('de');
		Assert::same('Web', $de['main_title']);
		Assert::same('cs@example.com', $de['main_email']);
		Assert::same('+420 111 222 333', $de['contact_phone']);
	}


	public function testFormValuesAreNotMixed(): void
	{
		Assert::same('info@example.com', $this->settings->getGlobalValues()['main_email']);
		// hodnoty jazyka bez doplnění globální - formulář by ji jinak uložil k jazyku
		Assert::same('', $this->settings->getLanguageValues('cs')['contact_phone']);
		Assert::same('', $this->settings->getLanguageValues('en')['main_email']);
	}


	public function testSaveForLanguageKeepsGlobalPhoneAndEmail(): void
	{
		$this->settings->saveForLanguage(1, 'cs', ArrayHash::from(['main_email' => null, 'contact_phone' => '+420 999']));

		$global = $this->db->query('SELECT mainEmail, contactPhone FROM firecms_settings WHERE id = 1')->fetch();
		Assert::same('info@example.com', $global?->mainEmail);
		Assert::same('+420 111 222 333', $global?->contactPhone);

		$cs = $this->settings->getAllForLanguage('cs');
		Assert::same('info@example.com', $cs['main_email']);
		Assert::same('+420 999', $cs['contact_phone']);
	}


	public function testSaveGlobalWritesEmail(): void
	{
		$this->settings->saveGlobal(1, ArrayHash::from(['main_email' => 'new@example.com', 'contact_phone' => null]));

		Assert::same('new@example.com', $this->settings->getAllForLanguage('en')['main_email']);
		Assert::same('', $this->settings->getAllForLanguage('cs')['contact_phone']);
	}
}


(new SettingsTest())->run();
