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
		return $this->articlesModel->getAll()->where("history_id", $id)->count();
	}


	/**
	 * Find by article id
	 */
	public function makeBackup(int $id): void
	{
		//$this->db->query("INSERT INTO ".$this->articlesModel->getTableName()." SELECT *, null AS article_id FROM ".$this->articlesModel->getTableName()." WHERE article_id = 10");
		$articleToDuplicate = ArrayHash::from($this->articlesModel->findById($id)->fetch()->toArray());
		$articleToDuplicate->history_id = $articleToDuplicate->article_id;
		$articleToDuplicate->article_id = null;

		//article
		$newId = $this->articlesModel->insert($articleToDuplicate);

		//description
		$allWithTranslations = $this->articlesModel->getTranslationTable()
			->select(Model\Articles::TRANSLATION_TABLE_NAME.".*")
			->where("article_descriptions.article_id", $id)
			->fetchAll();
		foreach ($allWithTranslations as $allWithTranslation){
			$translation = ArrayHash::from($allWithTranslation->toArray());
			unset($translation->article_id);
			unset($translation->update_date);
			$this->articlesModel->insertTranslation($newId, $allWithTranslation->language, $translation);
		}

		//article_file
		$allFiles = $this->articlesModel->getRelationFile($id)->fetchAll();
		foreach ($allFiles as $allFile){
			$file = ArrayHash::from(array(
				"file_id" => $allFile->file_id,
				"is_main" => $allFile->is_main,
				"position" => $allFile->position,
			));
			$this->articlesModel->insertRelationFile($newId, $file->file_id, $file);
		}

		//article_category
		$allCategories = $this->db->table(Model\Categories::RELATION_ARTICLE_TABLE_NAME)
			->select(Model\Categories::RELATION_ARTICLE_TABLE_NAME.".*")
			->where("article_id", $id)
			->fetchAll();
		/** @var array $allCategory */
		foreach ($allCategories as $allCategory){
			$category = ArrayHash::from($allCategory);
			unset($category->article_id);
			$this->categoriesModel->insertRelationArticle($category->category_id, $newId, $category);
		}

		//article_meta
		/*$allMetas = $this->articlesModel->getRelationCategory($id)->fetchAll();
		foreach ($allMetas as $allMeta){
			$meta = ArrayHash::from($allMeta);
			unset($meta->article_id);
			$this->categoriesModel->insertRelationArticle($meta->category_id, $newId, $meta);
		}*/

		//article_tag
		$allTags = $this->articlesModel->getRelationTagsTable()
			->select($this->articlesModel->getRelationTagsTable()->getName().".*")
			->where("article_id", $id)
			->fetchAll();
		/** @var array $allTag */
		foreach ($allTags as $allTag){
			$tag = ArrayHash::from($allTag);
			unset($tag->article_id);
			$this->articlesModel->insertRelationTags($newId, $tag->tag_id);
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
		return $this->articlesModel->getAll()->where("history_id", $id)->order("create_date")->fetchAll();
	}

}
