<?php
declare(strict_types=1);

namespace App\Model\Database;

use App\Service\LanguageService;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

/**
 * Settings model - one global `firecms_settings` row (an anchor, see getMainSettingId()) with two
 * kinds of values: a couple of genuinely global ones living as columns directly on that row
 * (GLOBAL_COLUMN_MAP - image_resolution/themePath, not translatable), and the rest held per
 * language in `firecms_settingDescriptions` (DESCRIPTION_COLUMN_MAP - main_title, seo_title, ...).
 * Replaces the old flat key/value `firecms_options` table; mirrors the firecms_tags/
 * firecms_tagDescriptions pattern for the per-language part.
 *
 * Both DB tables use camelCase columns (settingId, languageId, mainTitle, imageResolution, ...),
 * but every caller of this model (BasePresenter, the @layout.latte templates, SettingFormFactory,
 * ContactFormFactory, MultiFileUploadModel, ...) still uses the historical snake_case keys
 * (`main_title`, `seo_title`, `image_resolution`, `themePath`) predating that rename. Rather than
 * touch every one of those call sites, the two *_COLUMN_MAP consts below translate between the
 * public snake_case key and the real camelCase column, entirely inside this class.
 *
 * Klíč v obou mapách (main_email, contact_phone) je v obou tabulkách: hodnota jazyka, a když je prázdná,
 * globální (getAllForLanguage(), getByKey()). Formulář Nastavení edituje každou zvlášť (getGlobalValues(),
 * getLanguageValues()).
 */
class Settings extends BaseModel implements Translatable
{

	public const TRANSLATION_TABLE_NAME = 'firecms_settingDescriptions';

	/** Public (historical) key => real column on firecms_settings - global, not translatable. */
	public const GLOBAL_COLUMN_MAP = [
		'image_resolution' => 'imageResolution',
		'themePath' => 'themePath',
		'main_email' => 'mainEmail',
		'contact_phone' => 'contactPhone',
		'map_latitude' => 'mapLatitude',
		'map_longitude' => 'mapLongitude',
	];

	/** Public (historical) key => real column on firecms_settingDescriptions - one row per language. */
	public const DESCRIPTION_COLUMN_MAP = [
		'main_title' => 'mainTitle',
		'main_description' => 'mainDescription',
		'main_email' => 'mainEmail',
		'contact_phone' => 'contactPhone',
		'contact_address' => 'contactAddress',
		'seo_title' => 'seoTitle',
		'seo_description' => 'seoDescription',
		'seo_keywords' => 'seoKeywords',
	];


	public function __construct(\Nette\Database\Explorer $database, private LanguageService $languages)
	{
		parent::__construct($database);

		$this->setTableName('firecms_settings');
		$this->setForeignKeyColumn('settingId');
	}


	public function getTranslationTable(): Selection
	{
		return $this->database->table(self::TRANSLATION_TABLE_NAME);
	}


	/**
	 * The single settings row every part of the app operates on. There is normally always exactly
	 * one (seeded by the structures migration); this only inserts one if that's somehow missing.
	 */
	public function getMainSettingId(): int
	{
		$row = $this->getTable()->fetch();
		if ($row) {
			return (int) $row->{$this->getColumnId()};
		}

		return $this->insert(new ArrayHash());
	}


	/**
	 * All setting values - the global ones plus this language's translated ones - under their
	 * historical snake_case keys, e.g. ['main_title' => '...', 'themePath' => 'default', ...].
	 * Falls back to the site's default language when $language has no description row of its own yet,
	 * a key held in both tables (main_email, contact_phone) falls back to the global value when empty.
	 * @return array<string, string>
	 */
	public function getAllForLanguage(string $language): array
	{
		$values = $this->getGlobalValues();
		foreach ($this->getLanguageValues($language) as $publicKey => $value) {
			if ($value !== '' || !isset(self::GLOBAL_COLUMN_MAP[$publicKey])) {
				$values[$publicKey] = $value;
			}
		}

		return $values;
	}


	/**
	 * Globální hodnoty z `firecms_settings` (klíče z GLOBAL_COLUMN_MAP), bez hodnot jazyka.
	 * @return array<string, string>
	 */
	public function getGlobalValues(): array
	{
		$settings = $this->getTable()->fetch();
		// toArray() místo $row->{$column} ?? '': Nette Database si pamatuje dřív použité sloupce a čte jen je,
		// `??` (__isset) chybějící sloupec nedotáhne - nově přidaný sloupec by zůstal prázdný až do smazání temp/
		$data = $settings ? $settings->toArray() : [];

		$values = [];
		foreach (self::GLOBAL_COLUMN_MAP as $publicKey => $column) {
			$values[$publicKey] = self::toText($data[$column] ?? null);
		}

		return $values;
	}


	/**
	 * Hodnoty jazyka z `firecms_settingDescriptions` (klíče z DESCRIPTION_COLUMN_MAP), bez doplnění globálních.
	 * Jazyk bez vlastního řádku dostane řádek výchozího jazyka.
	 * @return array<string, string>
	 */
	public function getLanguageValues(string $language): array
	{
		$row = $this->getTranslationTable()->where('languageId', $language)->fetch();
		if (!$row && $language !== $this->languages->getDefaultLanguage()) {
			$row = $this->getTranslationTable()->where('languageId', $this->languages->getDefaultLanguage())->fetch();
		}
		$data = $row ? $row->toArray() : []; // toArray(), viz getGlobalValues()

		$values = [];
		foreach (self::DESCRIPTION_COLUMN_MAP as $publicKey => $column) {
			$values[$publicKey] = self::toText($data[$column] ?? null);
		}

		return $values;
	}


	/** Hodnota sloupce jako text, NULL (a cokoliv neskalárního) = prázdný řetězec */
	private static function toText(mixed $value): string
	{
		return is_scalar($value) ? (string) $value : '';
	}


	/**
	 * Get a single value, defaulting to the site's default language - for callers that don't have
	 * a "current language" of their own (a DI-constructed model, a mailer job, ...). Works for both
	 * the global keys (image_resolution, themePath) and the per-language ones (with the global fallback).
	 */
	public function getByKey(string $key, ?string $language = null): string
	{
		if (isset(self::GLOBAL_COLUMN_MAP[$key]) && !isset(self::DESCRIPTION_COLUMN_MAP[$key])) {
			return $this->getGlobalValues()[$key];
		}

		if (!isset(self::DESCRIPTION_COLUMN_MAP[$key])) {
			throw new \InvalidArgumentException("Unknown setting key '$key'.");
		}

		return $this->getAllForLanguage($language ?? $this->languages->getDefaultLanguage())[$key];
	}


	/**
	 * Uloží globální (nepřekládané) hodnoty - klíče z GLOBAL_COLUMN_MAP, ostatní ignoruje. Formulář
	 * Nastavení je má ve vlastním kontejneru `data` (jazyky jsou v `locales`), aby se nepřepisovaly hodnotou
	 * z jiného jazyka.
	 */
	public function saveGlobal(int $settingId, ArrayHash $data): void
	{
		$globalData = [];
		foreach (self::GLOBAL_COLUMN_MAP as $publicKey => $column) {
			if (property_exists($data, $publicKey)) {
				$globalData[$column] = $data->{$publicKey};
			}
		}
		if ($globalData) {
			$this->update($settingId, $globalData);
		}
	}


	/**
	 * Saves one language's worth of setting values (used by the admin Settings form, once per
	 * language container - see SettingFormFactory). $data may also carry the global keys (older
	 * callers); those are written to the one settings row - the admin form saves them via saveGlobal().
	 * Klíče v obou mapách (main_email, contact_phone) se tu ukládají jen k jazyku.
	 */
	public function saveForLanguage(int $settingId, string $language, ArrayHash $data): void
	{
		$globalData = [];
		foreach (array_diff_key(self::GLOBAL_COLUMN_MAP, self::DESCRIPTION_COLUMN_MAP) as $publicKey => $column) {
			if (property_exists($data, $publicKey)) { // i null = vymazané pole (isset by ho přeskočil)
				$globalData[$column] = $data->{$publicKey};
			}
		}
		if ($globalData) {
			$this->update($settingId, $globalData);
		}

		$descriptionData = [];
		foreach (self::DESCRIPTION_COLUMN_MAP as $publicKey => $column) {
			if (property_exists($data, $publicKey)) { // i null = vymazané pole (isset by ho přeskočil)
				$descriptionData[$column] = $data->{$publicKey};
			}
		}

		$existing = $this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $settingId)
			->where('languageId', $language)
			->fetch();

		if ($existing) {
			$existing->update($descriptionData);
			return;
		}

		$descriptionData[$this->getForeignKeyColumn()] = $settingId;
		$descriptionData['languageId'] = $language;
		$this->getTranslationTable()->insert($descriptionData);
	}

}
