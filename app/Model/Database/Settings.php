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
 */
class Settings extends BaseModel implements Translatable
{

	public const TRANSLATION_TABLE_NAME = 'firecms_settingDescriptions';

	/** Public (historical) key => real column on firecms_settings - global, not translatable. */
	public const GLOBAL_COLUMN_MAP = [
		'image_resolution' => 'imageResolution',
		'themePath' => 'themePath',
		'contact_phone' => 'contactPhone',
		'map_latitude' => 'mapLatitude',
		'map_longitude' => 'mapLongitude',
	];

	/** Public (historical) key => real column on firecms_settingDescriptions - one row per language. */
	public const DESCRIPTION_COLUMN_MAP = [
		'main_title' => 'mainTitle',
		'main_description' => 'mainDescription',
		'main_email' => 'mainEmail',
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
	 * Falls back to the site's default language when $language has no description row of its own yet.
	 */
	public function getAllForLanguage(string $language): array
	{
		$values = array_fill_keys(array_keys(self::DESCRIPTION_COLUMN_MAP), '');

		$row = $this->getTranslationTable()->where('languageId', $language)->fetch();
		if (!$row && $language !== $this->languages->getDefaultLanguage()) {
			$row = $this->getTranslationTable()->where('languageId', $this->languages->getDefaultLanguage())->fetch();
		}
		// toArray() místo $row->{$column} ?? '': Nette Database si pamatuje dřív použité sloupce a čte jen je,
		// `??` (__isset) chybějící sloupec nedotáhne - nově přidaný sloupec by zůstal prázdný až do smazání temp/
		$data = $row ? $row->toArray() : [];
		foreach (self::DESCRIPTION_COLUMN_MAP as $publicKey => $column) {
			$values[$publicKey] = (string) ($data[$column] ?? '');
		}

		$settings = $this->getTable()->fetch();
		$globalData = $settings ? $settings->toArray() : [];
		foreach (self::GLOBAL_COLUMN_MAP as $publicKey => $column) {
			$values[$publicKey] = (string) ($globalData[$column] ?? '');
		}

		return $values;
	}


	/**
	 * Get a single value, defaulting to the site's default language - for callers that don't have
	 * a "current language" of their own (a DI-constructed model, a mailer job, ...). Works for both
	 * the global keys (image_resolution, themePath) and the per-language ones.
	 */
	public function getByKey(string $key, ?string $language = null): string
	{
		if (isset(self::GLOBAL_COLUMN_MAP[$key])) {
			$settings = $this->getTable()->fetch();
			return (string) (($settings ? $settings->toArray() : [])[self::GLOBAL_COLUMN_MAP[$key]] ?? ''); // toArray(), viz getAllForLanguage()
		}

		if (!isset(self::DESCRIPTION_COLUMN_MAP[$key])) {
			throw new \InvalidArgumentException("Unknown setting key '$key'.");
		}

		return $this->getAllForLanguage($language ?? $this->languages->getDefaultLanguage())[$key];
	}


	/**
	 * Uloží globální (nepřekládané) hodnoty - klíče z GLOBAL_COLUMN_MAP, ostatní ignoruje. Formulář
	 * Nastavení je má ve vlastním kontejneru `global`, aby se nepřepisovaly hodnotou z jiného jazyka.
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
	 */
	public function saveForLanguage(int $settingId, string $language, ArrayHash $data): void
	{
		$globalData = [];
		foreach (self::GLOBAL_COLUMN_MAP as $publicKey => $column) {
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
