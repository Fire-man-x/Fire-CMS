<?php
declare(strict_types=1);

namespace App\Model;

use App\Model\TranslatedTitleTrait\TranslatedTitleTrait;
use App\Service\LanguageService;
use Nette\Utils\ArrayHash;

/**
 * Tags Model
 */
class Tags extends BaseModel implements Translatable
{
	use TranslatedTitleTrait;

	const TRANSLATION_TABLE_NAME = 'firecms_tagDescriptions';

	private LanguageService $languages;

	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages)
	{
		parent::__construct($database);
		$this->languages = $languages;

		$this->setTableName('firecms_tags');
		$this->setForeignKeyColumn('tagId');
	}


	/**
	 * Get table
	 */
	public function getTranslationTable(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::TRANSLATION_TABLE_NAME)
			->where(self::TRANSLATION_TABLE_NAME.".languageId", array_keys($this->languages->getActiveLanguages())); //only active languages
	}


	/**
	 * Inserts new
	 */
	public function insertTranslation(int $tagId, string $language, ArrayHash $data): int
	{
		$data->{$this->getForeignKeyColumn()} = $tagId;
		$data->languageId = $language;
		$inserted = $this->getTranslationTable()->insert($data);
		return $inserted instanceof \Nette\Database\Table\ActiveRow ? 1 : (int) $inserted;
	}


	/**
	 * Update translation
	 */
	public function updateTranslation(int $tagId, string $language, ArrayHash $data): void
	{
		$finded = $this->findTranslationBy($tagId, $language);
		if ($finded->fetch()) {
			$finded->update($data);
		} else {
			$this->insertTranslation($tagId, $language, $data);
		}
	}


	/**
	 * Sloupec překladové tabulky s názvem položky (viz TranslatedTitleTrait)
	 */
	protected function getTitleColumn(): string
	{
		return 'name';
	}


	/**
	 * Find translation
	 */
	public function findTranslationBy(int $tagId, string $language): \Nette\Database\Table\Selection
	{
		return $this->getTranslationTable()
				->where($this->getForeignKeyColumn(), $tagId)
				->where("languageId", $language);
	}

}
