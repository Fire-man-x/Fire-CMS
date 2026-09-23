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
		private Model\Tags $tagsModel,
		private Translator $translator,
		private Model\Articles $articleTagsModel,
		private Model\Categories $categoryTagsModel,
		private Explorer $db
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
	public function getModelByType(string $type): Model\ISubTags
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
		/* @var $model Model\ISubTags */
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
		if($model instanceof Model\BaseModel){
			$column = $model->getForeignKeyColumn();
		}

		//replacement
		//název ve výchozím jazyce webu - pro štítky bez překladu do $language (s poznámkou "(default)")
		$defaultNameSql = $this->tagsModel->getTitleSql("`firecms_tags`.`id`");
		$titleParams = $this->tagsModel->getTitleParams();
		$items = $this->db->query("SELECT ".$defaultNameSql." AS `defaultName`, `firecms_tags`.`id`, `". Model\Tags::TRANSLATION_TABLE_NAME."`.`languageId`, `". Model\Tags::TRANSLATION_TABLE_NAME."`.`name`,
				IF(`". Model\Tags::TRANSLATION_TABLE_NAME."`.`name` IS NULL, CONCAT(".$defaultNameSql.", ?), `". Model\Tags::TRANSLATION_TABLE_NAME."`.`name`) AS `label`
			FROM `".$model->getRelationTagsTable()->getName()."`
			LEFT JOIN `firecms_tags` ON `".$model->getRelationTagsTable()->getName()."`.`tagId` = `firecms_tags`.`id`
			LEFT JOIN `". Model\Tags::TRANSLATION_TABLE_NAME."` ON `firecms_tags`.`id` = `". Model\Tags::TRANSLATION_TABLE_NAME."`.`tagId` AND (`languageId` IS NULL OR `languageId` = ?)
			WHERE `".$column."` = ?",
			...[...$titleParams, ...$titleParams, $this->getDefaultText(), $language, $id])
			->fetchAll();

		return $items;
	}



	/**
	 * Find by name
	 */
	public function findByName(string $language, string $name): array
	{
		//název ve výchozím jazyce webu - pro štítky bez překladu do $language (s poznámkou "(default)")
		$defaultNameSql = $this->tagsModel->getTitleSql("`" . $this->tagsModel->getTableName() . "`.`id`");
		$titleParams = $this->tagsModel->getTitleParams();
		$items = $this->tagsModel->findAll()
			->select($defaultNameSql . " AS `defaultName`", ...$titleParams)
			->select($this->tagsModel->getTableName().".id")
			->select(":" . Model\Tags::TRANSLATION_TABLE_NAME . ".languageId")
			->select(":" . Model\Tags::TRANSLATION_TABLE_NAME . ".name")
			->select("IF(:" . Model\Tags::TRANSLATION_TABLE_NAME . ".name IS NULL, CONCAT(" . $defaultNameSql . ", ?), :" . Model\Tags::TRANSLATION_TABLE_NAME . ".name) AS label", ...[...$titleParams, $this->getDefaultText()])
			->joinWhere(":" . Model\Tags::TRANSLATION_TABLE_NAME, "languageId IS NULL OR languageId = ?", $language)
			->where($defaultNameSql . " LIKE ? OR name LIKE ?", ...[...$titleParams, "%" . $name . "%", "%" . $name . "%"])
			->order("name")
			->fetchAll();
		$rows = array_map(iterator_to_array(...), $items);
		return (array) Arrays::associate($rows, 'id');
	}

}
