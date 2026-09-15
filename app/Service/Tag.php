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
					//insert to tag_id universal name
					$this->tagsModel->insertTranslation($finded["tag_id"], $language, ArrayHash::from(array(
							"name" => $finded["grid_name"]
					)));
					$tagId = $finded["tag_id"];
				}
			}

			if (!isset($tagId)) { //normal insert
				$exist = $this->tagsModel->getTranslationTable()
					->where("name", $tag)
					->where("language_id", $language)
					->fetch();
				if (!$exist) {
					$tagId = $this->tagsModel->insert(ArrayHash::from(array()));
					$this->tagsModel->insertTranslation($tagId, $language, ArrayHash::from(array(
							"name" => $tag
					)));
				} else {
					$tagId = $exist->tag_id;
				}
			}

			//insert relation
			$model->insertRelationTags($columnId, $tagId);
		}


		/*
		$tagTable = "tags";
		$items = $this->tagsModel->getAll()
			->select($tagTable.".language_id")
			->select($tagTable.".key")
			->select($tagTable.".value AS default_value")
			->select("category.*")
			->joinWhere(":".$model->getTableName(), $model->getReferenceColumn()." = ?", $id)
			->order("is_main DESC")
			->order("category.grid_name");

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
			->select("tag.grid_name")
			->select("tag.tag_id")
			->select("tag:".Model\Tags::TRANSLATION_TABLE_NAME.".language_id")
			->select("tag:".Model\Tags::TRANSLATION_TABLE_NAME.".name")
			->select("IF(:" . Model\Tags::TRANSLATION_TABLE_NAME . ".name IS NULL, CONCAT(tags.grid_name, ?), :" . Model\Tags::TRANSLATION_TABLE_NAME . ".name) AS label", $defaultText)
			->joinWhere("tag:".Model\Tags::TRANSLATION_TABLE_NAME, "language_id IS NULL OR language_id = ?", $language)
			->where($model->getColumnId(), $id)
			->fetchAll();
		*/
		$column = '';
		if($model instanceof Model\BaseModel){
			$column = $model->getColumnId();
		}

		//replacement
		$items = $this->db->query("SELECT `tags`.`grid_name`, `tags`.`tag_id`, `". Model\Tags::TRANSLATION_TABLE_NAME."`.`language_id`, `". Model\Tags::TRANSLATION_TABLE_NAME."`.`name`,
				IF(`". Model\Tags::TRANSLATION_TABLE_NAME."`.`name` IS NULL, CONCAT(`tags`.`grid_name`, ?), `". Model\Tags::TRANSLATION_TABLE_NAME."`.`name`) AS `label`
			FROM `".$model->getRelationTagsTable()->getName()."`
			LEFT JOIN `tags` ON `".$model->getRelationTagsTable()->getName()."`.`tag_id` = `tags`.`tag_id`
			LEFT JOIN `". Model\Tags::TRANSLATION_TABLE_NAME."` ON `tags`.`tag_id` = `". Model\Tags::TRANSLATION_TABLE_NAME."`.`tag_id` AND (`language_id` IS NULL OR `language_id` = ?)
			WHERE `".$column."` = ?",
			$this->getDefaultText(), $language, $id)
			->fetchAll();

		return $items;
	}



	/**
	 * Find by name
	 */
	public function findByName(string $language, string $name): array
	{
		$items = $this->tagsModel->findAll()
			->select("tags.grid_name")
			->select("tags.tag_id")
			->select(":" . Model\Tags::TRANSLATION_TABLE_NAME . ".language_id")
			->select(":" . Model\Tags::TRANSLATION_TABLE_NAME . ".name")
			->select("IF(:" . Model\Tags::TRANSLATION_TABLE_NAME . ".name IS NULL, CONCAT(tags.grid_name, ?), :" . Model\Tags::TRANSLATION_TABLE_NAME . ".name) AS label", $this->getDefaultText())
			->joinWhere(":" . Model\Tags::TRANSLATION_TABLE_NAME, "language_id IS NULL OR language_id = ?", $language)
			->whereOr(array(
				"grid_name LIKE ?" => "%" . $name . "%",
				"name LIKE ?" => "%" . $name . "%"
			))
			->order("name")
			->fetchAll();
		$rows = array_map(iterator_to_array(...), $items);
		return (array) Arrays::associate($rows, 'tag_id');
	}

}
