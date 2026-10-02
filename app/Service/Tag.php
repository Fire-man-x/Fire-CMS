<?php
declare(strict_types=1);

namespace App\Service;

use App\Model;
use Nette\Database\Explorer;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nette\Utils\Arrays;

/**
 * Tag Service
 */
class Tag
{

	const string TYPE_ARTICLE = "article";
	const string TYPE_CATEGORY = "category";

	/**
	 * Constructor
	 */
	public function __construct(
		private Model\Database\Tags       $tagsModel,
		private Translator                $translator,
		private Model\Database\Articles   $articleTagsModel,
		private Model\Database\Categories $categoryTagsModel,
		private Explorer                  $db
	)
	{
	}


	/**
	 * Get default text
	 */
	protected function getDefaultText(): string
	{
		return " (" . $this->translator->translate("default") . ")";
	}


	/**
	 * Get model by type
	 * @throws \InvalidArgumentException
	 */
	public function getModelByType(string $type): Model\Database\ISubTags
	{
		switch ($type) {
			case self::TYPE_ARTICLE:
				return $this->articleTagsModel;
			case self::TYPE_CATEGORY:
				return $this->categoryTagsModel;
			default:
				throw new \InvalidArgumentException("Model for type '$type' doesn't exist.");
		}
	}


	/**
	 * Insert or update tags
	 */
	public function useTags(string $type, int $columnId, string $language, array $tags)
	{
		/* @var $model \App\Model\Database\ISubTags */
		$model = $this->getModelByType($type);

		//delete all
		$model->deleteAllRelationTags($columnId);

		//create in base table
		foreach ($tags as $tag){
			$tagId = null;
			//some tag with "(default)" notice
			if (str_ends_with($tag, $this->getDefaultText())) {
				$originalName = \Nette\Utils\Strings::replace($tag, '~'.preg_quote($this->getDefaultText()).'$~', "", 1); //limit to 1
				$relationTags = $this->findByName($language, $originalName);
				$finded = false;
				foreach ($relationTags as $relationTag) {
					if ($relationTag["label"] == $tag) {
						$finded = $relationTag;
						break;
					}
				}

				if ($finded) {
					//insert to tagId universal name
					$this->tagsModel->insertTranslation($finded["id"], $language, ArrayHash::from(array(
							"name" => $finded["defaultName"]
					)));
					$tagId = $finded["id"];
				}
			}

			if (!isset($tagId)) { //normal insert
				$exist = $this->tagsModel->getTranslationTable()
					->where("name", $tag)
					->where("languageId", $language)
					->fetch();
				if (!$exist) {
					$tagId = $this->tagsModel->insert(ArrayHash::from(array()));
					$this->tagsModel->insertTranslation($tagId, $language, ArrayHash::from(array(
							"name" => $tag
					)));
				} else {
					$tagId = $exist->tagId;
				}
			}

			//insert relation
			$model->insertRelationTags($columnId, $tagId);
		}


		/*
		$tagTable = "tags";
		$items = $this->tagsModel->getAll()
			->select($tagTable.".languageId")
			->select($tagTable.".key")
			->select($tagTable.".value AS default_value")
			->select("category.*")
			->joinWhere(":".$model->getTableName(), $model->getReferenceColumn()." = ?", $id)
			->order("isMain DESC");

		return $items;*/
	}


	/**
	 * Relation tags
	 */
	public function getRelationTags(string $type, string $language, int $id): array
	{
		$model = $this->getModelByType($type);

		//@note: not working
		/*$items = $model->getRelationTagsTable()
			->select("tag.tagId")
			->select("tag:".Model\Tags::TRANSLATION_TABLE_NAME.".languageId")
			->select("tag:".Model\Tags::TRANSLATION_TABLE_NAME.".name")
			->select("IF(:" . Model\Tags::TRANSLATION_TABLE_NAME . ".name IS NULL, CONCAT(tags.name, ?), :" . Model\Tags::TRANSLATION_TABLE_NAME . ".name) AS label", $defaultText)
			->joinWhere("tag:".Model\Tags::TRANSLATION_TABLE_NAME, "languageId IS NULL OR languageId = ?", $language)
			->where($model->getColumnId(), $id)
			->fetchAll();
		*/
		$column = '';
		if($model instanceof Model\Database\BaseModel){
			$column = $model->getForeignKeyColumn();
		}

		// název ve výchozím jazyce webu - pro štítky bez překladu do $language (s poznámkou "(default)")
		// identifikátory přes delimite(), popisek se skládá v PHP (withLabels()) - dotaz běží na MariaDB i PostgreSQL
		$tags = $this->tagsModel;
		$tagTable = $tags->getTableName();
		$relationTable = $model->getRelationTagsTable()->getName();
		$translationTable = Model\Database\Tags::TRANSLATION_TABLE_NAME;
		$defaultNameSql = $tags->getTitleSql($tagTable . '.id');
		$titleParams = $tags->getTitleParams();
		$translatedName = $tags->delimite($translationTable . '.name');
		$translationLanguage = $tags->delimite($translationTable . '.languageId');
		$items = $this->db->query(
			'SELECT ' . $defaultNameSql . ' AS ' . $tags->delimite('defaultName') . ', ' . $tags->delimite($tagTable . '.id') . ', '
				. $translationLanguage . ', ' . $translatedName
				. ' FROM ' . $tags->delimite($relationTable)
				. ' LEFT JOIN ' . $tags->delimite($tagTable) . ' ON ' . $tags->delimite($relationTable . '.tagId') . ' = ' . $tags->delimite($tagTable . '.id')
				. ' LEFT JOIN ' . $tags->delimite($translationTable) . ' ON ' . $tags->delimite($tagTable . '.id') . ' = ' . $tags->delimite($translationTable . '.tagId')
				. ' AND (' . $translationLanguage . ' IS NULL OR ' . $translationLanguage . ' = ?)'
				. ' WHERE ' . $tags->delimite($relationTable . '.' . $column) . ' = ?',
			...[...$titleParams, $language, $id],
		)->fetchAll();
		$this->withLabels($items);

		return $items;
	}



	/**
	 * Find by name
	 */
	public function findByName(string $language, string $name): array
	{
		//název ve výchozím jazyce webu - pro štítky bez překladu do $language (s poznámkou "(default)")
		$defaultNameSql = $this->tagsModel->getTitleSql($this->tagsModel->getTableName() . ".id");
		$titleParams = $this->tagsModel->getTitleParams();
		$items = $this->tagsModel->findAll()
			->select($defaultNameSql . " AS defaultName", ...$titleParams)
			->select($this->tagsModel->getTableName().".id")
			->select(":" . Model\Database\Tags::TRANSLATION_TABLE_NAME . ".languageId")
			->select(":" . Model\Database\Tags::TRANSLATION_TABLE_NAME . ".name")
			->joinWhere(":" . Model\Database\Tags::TRANSLATION_TABLE_NAME, "languageId IS NULL OR languageId = ?", $language)
			// LOWER(): LIKE je v PostgreSQL citlivý na velikost písmen (MariaDB s _ci kolací ne); hledaný text
			// na malá písmena v PHP - LOWER(?) by PostgreSQL odmítl (neurčí typ parametru)
			->where("LOWER(" . $defaultNameSql . ") LIKE ? OR LOWER(name) LIKE ?", ...[...$titleParams, "%" . mb_strtolower($name) . "%", "%" . mb_strtolower($name) . "%"])
			->order("name")
			->fetchAll();
		$rows = array_map(iterator_to_array(...), $items);
		$this->withLabels($rows);
		return (array) Arrays::associate($rows, 'id');
	}


	/**
	 * Doplní `label`: název v jazyce dotazu, jinak název ve výchozím jazyce webu s poznámkou getDefaultText().
	 * Skládá se v PHP, ne v SQL - CONCAT(poddotaz, ?) PostgreSQL odmítne (neurčí typ parametru).
	 * @param array<int|string, \Nette\Database\Row|array<mixed>> $rows řádky z query() (Row) nebo z findByName() (pole)
	 */
	private function withLabels(array &$rows): void
	{
		foreach ($rows as &$row) {
			$name = $row['name'] ?? null;
			$defaultName = $row['defaultName'] ?? null;
			$row['label'] = $name ?? ($defaultName !== null ? $defaultName . $this->getDefaultText() : null);
		}
	}

}
