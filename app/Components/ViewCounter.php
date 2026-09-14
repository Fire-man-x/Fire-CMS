<?php
declare(strict_types=1);

namespace App\Components;

use App\Model;
use Nette\InvalidArgumentException;
use Nette\SmartObject;

/**
 * Class ViewCounter
 */
class ViewCounter
{
	use SmartObject;

	const TYPE_ARTICLE = "article";
	const TYPE_CATEGORY = "category";
	const TYPE_FILE = "file";

	/**
	 * Categories model
	 */
	private Model\Categories $modelCategory;

	/**
	 * Articles model
	 */
	private Model\Articles $modelArticle;

	/**
	 * Files model
	 */
	private Model\Files $modelFile;


	/**
	 * Constructor
	 */
	public function __construct(Model\Categories $modelCategory, Model\Articles $modelArticle, Model\Files $modelFile)
	{
		$this->modelCategory = $modelCategory;
		$this->modelArticle = $modelArticle;
		$this->modelFile = $modelFile;
	}


	/**
	 * Model getter
	 * @throws InvalidArgumentException
	 */
	private function getModelByType(string $type): Model\Articles|Model\Files|Model\Categories
	{
		switch ($type) {
			case self::TYPE_ARTICLE:
				return $this->modelArticle;
			case self::TYPE_CATEGORY:
				return $this->modelCategory;
			case self::TYPE_FILE:
				return $this->modelFile;

			default:
				throw new InvalidArgumentException("Type '$type' is not allowed.");
		}
	}


	/**
	 * Add view count to counter
	 */
	public function itemViewed(string $type, int $itemId, string $language): void
	{
		$model = $this->getModelByType($type);

		if($model instanceof IViewCounter){
			$model->addViewCount($itemId, $language);
		}
	}

}

interface IViewCounter{
	public function addViewCount(int $itemId, string $language): void;
}