<?php

declare(strict_types=1);

namespace Tests\Model;

use App\Model\Languages;
use Nette\Database\Explorer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Languages::setDefault() - výchozí jazyk je vždy právě jeden a aktivní. Do 2026-09-23 formulář jazyka
 * ukládal zaškrtnuté "Default" přímo a výchozí pak byly dva jazyky.
 */
final class LanguagesSetDefaultTest extends TestCase
{
	private Explorer $db;
	private Languages $languages;


	protected function setUp(): void
	{
		$this->db = SqliteDatabase::create([
			'CREATE TABLE firecms_languages (
				languageId TEXT PRIMARY KEY,
				active INTEGER NOT NULL DEFAULT 1,
				"default" INTEGER NOT NULL DEFAULT 0,
				position INTEGER NOT NULL,
				name TEXT NOT NULL,
				shortcut TEXT NOT NULL
			)',
		]);
		foreach ([['en', 1, 1, 1], ['jn', 0, 0, 2], ['cs', 1, 0, 3]] as [$id, $active, $default, $position]) {
			$this->db->query(
				'INSERT INTO firecms_languages (languageId, active, "default", position, name, shortcut) VALUES (?, ?, ?, ?, ?, ?)',
				$id, $active, $default, $position, $id, $id,
			);
		}
		$this->languages = new Languages($this->db);
	}


	/**
	 * @return array<string, int>
	 */
	private function defaults(): array
	{
		return $this->db->query('SELECT languageId, "default" FROM firecms_languages ORDER BY languageId')->fetchPairs();
	}


	public function testOnlyOneLanguageIsDefault(): void
	{
		$this->languages->setDefault('cs');

		Assert::equal(['cs' => 1, 'en' => 0, 'jn' => 0], $this->defaults());
	}


	public function testInactiveLanguageIsActivatedWhenSetAsDefault(): void
	{
		$this->languages->setDefault('jn');

		Assert::equal(['cs' => 0, 'en' => 0, 'jn' => 1], $this->defaults());
		Assert::same(1, (int) $this->db->query('SELECT active FROM firecms_languages WHERE languageId = ?', 'jn')->fetchField());
	}
}

(new LanguagesSetDefaultTest())->run();
