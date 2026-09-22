<?php
declare(strict_types=1);

namespace App\Model;

use App\Service\LanguageService;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

/**
 * Settings model - one global `firecms_settings` row (an anchor, see getMainSettingId()) with its
 * actual values held per language in `firecms_settingDescriptions`. Replaces the old flat
 * key/value `firecms_options` table; mirrors the firecms_tags/firecms_tagDescriptions pattern.
 */
class Settings extends BaseModel
{

	public const TRANSLATION_TABLE_NAME = 'firecms_settingDescriptions';

	/** Column names that live on firecms_settingDescriptions (besides setting_id/language_id). */
	public const DESCRIPTION_COLUMNS = [
		'image_resolution', 'main_description', 'main_email', 'main_title',
		'seo_description', 'seo_keywords', 'seo_title', 'themePath',
	];


	public function __construct(\Nette\Database\Explorer $database, private LanguageService $languages)
	{
		parent::__construct($database);

		$this->setTableName('firecms_settings');
		$this->setColumnId('setting_id');
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
	 * All setting values for one language, e.g. ['main_title' => '...', 'themePath' => 'default', ...].
	 * Falls back to the site's default language when $language has no row of its own yet.
	 */
	public function getAllForLanguage(string $language): array
	{
		$row = $this->getTranslationTable()->where('language_id', $language)->fetch();
		if (!$row && $language !== $this->languages->getDefaultLanguage()) {
			$row = $this->getTranslationTable()->where('language_id', $this->languages->getDefaultLanguage())->fetch();
		}

		if (!$row) {
			return array_fill_keys(self::DESCRIPTION_COLUMNS, '');
		}

		$values = array_intersect_key($row->toArray(), array_flip(self::DESCRIPTION_COLUMNS));
		foreach ($values as $key => $value) {
			$values[$key] = (string) ($value ?? '');
		}

		return $values;
	}


	/**
	 * Get a single value, defaulting to the site's default language - for callers that don't have
	 * a "current language" of their own (a DI-constructed model, a mailer job, ...).
	 */
	public function getByKey(string $key, ?string $language = null): string
	{
		if (!in_array($key, self::DESCRIPTION_COLUMNS, true)) {
			throw new \InvalidArgumentException("Unknown setting key '$key'.");
		}

		return $this->getAllForLanguage($language ?? $this->languages->getDefaultLanguage())[$key];
	}


	/**
	 * Saves one language's worth of setting values (used by the admin Settings form).
	 */
	public function saveForLanguage(int $settingId, string $language, ArrayHash $data): void
	{
		$existing = $this->getTranslationTable()
			->where($this->getColumnId(), $settingId)
			->where('language_id', $language)
			->fetch();

		if ($existing) {
			$existing->update((array) $data);
			return;
		}

		$data->{$this->getColumnId()} = $settingId;
		$data->language_id = $language;
		$this->getTranslationTable()->insert($data);
	}

}
