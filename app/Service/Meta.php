<?php
declare(strict_types=1);

namespace App\Service;

use App\Model;
use Nette\Utils\ArrayHash;
use Nette\Utils\Arrays;

/**
 * Meta Service
 */
class Meta
{

	const TYPE_ARTICLE = "article";
	const TYPE_CATEGORY = "category";
	const TYPE_FILE = "file";

	private Model\Metas $metasModel;

	private Model\ArticleMetas $articleMetasModel;

	private Model\CategoryMetas $categoryMetasModel;

	private Model\Files $filesModel;


	/**
	 * Constructor
	 */
	public function __construct(Model\Metas $metasModel, Model\ArticleMetas $articlesModel, Model\CategoryMetas $categoryMetasModel, Model\Files $filesModel)
	{
		$this->metasModel = $metasModel;
		$this->articleMetasModel = $articlesModel;
		$this->categoryMetasModel = $categoryMetasModel;
		$this->filesModel = $filesModel;
	}


	/**
	 * Get model by type
	 * @throws \InvalidArgumentException
	 */
	public function getModelByType(string $type): Model\BaseSubMetas
	{
		switch ($type) {
			case self::TYPE_ARTICLE:
				return $this->articleMetasModel;
			case self::TYPE_CATEGORY:
				return $this->categoryMetasModel;
			case self::TYPE_FILE:
				return $this->filesModel;
			default:
				throw new \InvalidArgumentException("Model for type '$type' doesn't exist.");
		}
	}



	/**
	 * Find by article id
	 */
	public function getStructureByColumnId(string $type, string $language, int $id, bool $allValuesForAdmin = false): ArrayHash
	{
		$model = $this->getModelByType($type);
		/*$metaTable = $this->metasModel->getTableName();
		$metaTable = "meta";
		$items = $model->findByColumnId($id)
			//->select("article_metas.*")
			->select($metaTable.".language_id")
			->select($metaTable.".key")
			->select("IF(".$model->getTableName().".value = '', ".$metaTable.".value, ".$model->getTableName().".value) AS value")
			->select($metaTable.".value AS default_value")
			->where("language_id = ? OR language_id IS NULL", $language);*/

		$metaTable = "firecms_metas";
		$items = $this->metasModel->findAll()
			->select($metaTable . ".language_id")
			->select($metaTable . ".key")
			->select($metaTable . ".value AS default_value")
			->where("type = ?", $type)
			->where("language_id = ? OR language_id IS NULL", $language)
			->joinWhere(":" . $model->getTableName(), $model->getReferenceColumn() . " = ?", $id);

		if ($allValuesForAdmin) {
			$items->select($metaTable . ".meta_id");
			$items->select(":" . $model->getTableName() . ".value");
		} else {
			$items->select("IF(:" . $model->getTableName() . ".value = '' OR :" . $model->getTableName() . ".value IS NULL, IF(" . $metaTable . ".value = '', null, " . $metaTable . ".value), :" . $model->getTableName() . ".value) AS value");
		}

		$rows = array_map(iterator_to_array(...), $items->fetchAll());
		return ArrayHash::from((array) Arrays::associate($rows, 'key'));
	}

}
