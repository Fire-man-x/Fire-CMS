<?php
declare(strict_types=1);

namespace App\Model;

use App\Service\LanguageService;
use Nette\Utils\ArrayHash;

/**
 * Tags Model
 */
class Tags extends BaseModel
{

	const TRANSLATION_TABLE_NAME = 'tag_descriptions';

	private LanguageService $languages;

	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages)
	{
		parent::__construct($database);
		$this->languages = $languages;

		$this->setTableName('tags');
		$this->setColumnId('tag_id');
	}


	/**
	 * Get table
	 */
	public function getTranslationTable(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::TRANSLATION_TABLE_NAME)
			->where(self::TRANSLATION_TABLE_NAME.".language_id", array_keys($this->languages->getActiveLanguages())); //only active languages
	}


	/**
	 * Inserts new
	 */
	public function insertTranslation(int $tagId, string $language, ArrayHash $data): int
	{
		$data->{$this->getColumnId()} = $tagId;
		$data->language_id = $language;
		$this->updateGridName($tagId, $language, $data->name);
		return $this->getTranslationTable()->insert($data);
	}


	/**
	 * Update translation
	 */
	public function updateTranslation(int $tagId, string $language, ArrayHash $data): void
	{
		$finded = $this->findTranslationBy($tagId, $language);
		if ($finded->fetch()) {
			$finded->update($data);
			$this->updateGridName($tagId, $language, $data->name);
		} else {
			$this->insertTranslation($tagId, $language, $data);
		}
	}


	/**
	 * Update grid name
	 */
	public function updateGridName(int $tagId, string $language, string $name)
	{
		if($language == $this->languages->getDefaultLanguage() ||
			($language != $this->languages->getDefaultLanguage() && $this->getById($tagId)?->grid_name == null)){
			$this->update($tagId, array("grid_name"=>$name));
		}
	}


	/**
	 * Find translation
	 */
	public function findTranslationBy(int $tagId, string $language): \Nette\Database\Table\Selection
	{
		return $this->getTranslationTable()
				->where($this->getColumnId(), $tagId)
				->where("language_id", $language);
	}

}
