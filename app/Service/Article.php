<?php
declare(strict_types=1);

namespace App\Service;

use App\Model;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

/**
 * Article Service
 */
class Article
{
	public function __construct(
		private Model\Articles $articlesModel,
		private Model\Categories $categoriesModel,
		private Explorer $db
	)
	{
	}


	/**
	 * Find by article id
	 */
	public function getRevisionsCount(int $id): int
	{
		if($id == null){
			return 0;
		}
		return $this->articlesModel->findAll()->where("historyId", $id)->count();
	}


	/**
	 * Find by article id
	 */
	public function makeBackup(int $id): void
	{
		//$this->db->query("INSERT INTO ".$this->articlesModel->getTableName()." SELECT *, null AS articleId FROM ".$this->articlesModel->getTableName()." WHERE articleId = 10");
		$articleToDuplicate = ArrayHash::from($this->articlesModel->getById($id)?->toArray());
		$articleToDuplicate->historyId = $articleToDuplicate->id;
		$articleToDuplicate->id = null;

		//article
		$newId = $this->articlesModel->insert($articleToDuplicate);

		//description
		$allWithTranslations = $this->articlesModel->getTranslationTable()
			->select(Model\Articles::TRANSLATION_TABLE_NAME.".*")
			->where(Model\Articles::TRANSLATION_TABLE_NAME.".articleId", $id)
			->fetchAll();
		foreach ($allWithTranslations as $allWithTranslation){
			$translation = ArrayHash::from($allWithTranslation->toArray());
			unset($translation->articleId);
			unset($translation->updateDate);
			$this->articlesModel->insertTranslation($newId, $allWithTranslation->languageId, $translation);
		}

		//article_file
		$allFiles = $this->articlesModel->getRelationFile($id)->fetchAll();
		foreach ($allFiles as $allFile){
			$file = ArrayHash::from(array(
				"fileId" => $allFile->fileId,
				"isMain" => $allFile->isMain,
				"position" => $allFile->position,
			));
			$this->articlesModel->insertRelationFile($newId, $file->fileId, $file);
		}

		//article_category
		$allCategories = $this->db->table(Model\Categories::RELATION_ARTICLE_TABLE_NAME)
			->select(Model\Categories::RELATION_ARTICLE_TABLE_NAME.".*")
			->where("articleId", $id)
			->fetchAll();
		/** @var \Nette\Database\Table\ActiveRow $allCategory */
		foreach ($allCategories as $allCategory){
			$category = ArrayHash::from($allCategory->toArray());
			unset($category->articleId);
			$this->categoriesModel->insertRelationArticle($category->categoryId, $newId, $category);
		}

		//article_meta
		/*$allMetas = $this->articlesModel->getRelationCategory($id)->fetchAll();
		foreach ($allMetas as $allMeta){
			$meta = ArrayHash::from($allMeta);
			unset($meta->articleId);
			$this->categoriesModel->insertRelationArticle($meta->categoryId, $newId, $meta);
		}*/

		//article_tag
		$allTags = $this->articlesModel->getRelationTagsTable()
			->select($this->articlesModel->getRelationTagsTable()->getName().".*")
			->where("articleId", $id)
			->fetchAll();
		/** @var \Nette\Database\Table\ActiveRow $allTag */
		foreach ($allTags as $allTag){
			$tag = ArrayHash::from($allTag->toArray());
			unset($tag->articleId);
			$this->articlesModel->insertRelationTags($newId, $tag->tagId);
		}

	}


	/**
	 * Find by category id
	 * @return ?array<int,ActiveRow>
	 */
	public function getRevisions(int $id): ?array
	{
		if($id == null){
			return null;
		}
		return $this->articlesModel->findAll()->where("historyId", $id)->order("createDate")->fetchAll();
	}

}
