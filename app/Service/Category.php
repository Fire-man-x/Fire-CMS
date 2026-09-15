<?php
declare(strict_types=1);

namespace App\Service;

use App\Model;
use Nette\Application\ForbiddenRequestException;
use Nette\Utils\ArrayHash;

/**
 * Category Service
 */
class Category
{

	private Model\Categories $categoriesModel;


	/**
	 * Constructor
	 */
	public function __construct(Model\Categories $categoriesModel)
	{
		$this->categoriesModel = $categoriesModel;
	}


	/**
	 * Insert category
	 */
	public function insert(ArrayHash $values, string $language, ArrayHash $translationValues)
	{
		//begin
		$this->categoriesModel->getDatabase()->beginTransaction();

		$hasParent = isset($values["parent_id"]);
		
		//last right
		$rightQuery = $this->categoriesModel->getAllForMenu();
		if($hasParent){
			$rightQuery->select("categories.category_right AS max_right");
			$rightQuery->where("category_id", $values["parent_id"]);
		}else{
			$rightQuery->select("IFNULL(MAX(categories.category_right), 0) AS max_right");
		}
		$right = $rightQuery->fetchField();
		if ($hasParent) {
			$values["category_left"] = $right;
			$values["category_right"] = $right + 1;

			$this->categoriesModel->getAllForMenu()
				->where("category_right >= ?", $right)
				->update(array("category_right" => new \Nette\Database\SqlLiteral("`category_right` + 2")));

			$this->categoriesModel->getAllForMenu()
				->where("category_left >= ?", $right)
				->update(array("category_left" => new \Nette\Database\SqlLiteral("`category_left` + 2")));
		} else {
			$values["category_left"] = $right + 1;
			$values["category_right"] = $right + 2;
		}

		$id = $this->categoriesModel->insert($values);
		$this->categoriesModel->insertTranslation($id, $language, $translationValues);

		//commit
		$this->categoriesModel->getDatabase()->commit();

		return $id;
	}


	/**
	 * Update category by id
	 */
	public function update(int $categoryId, array $values, string $language, ArrayHash $translationValues): void
	{
		//make backup
		$this->makeBackup($categoryId);

		$this->categoriesModel->update($categoryId, $values);
		$this->categoriesModel->updateTranslation($categoryId, $language, $translationValues);
	}


	/**
	 * Update category by id
	 * @throws ForbiddenRequestException
	 */
	public function delete(int $categoryId): int
	{
		$categoryInfo = $this->categoriesModel->getById($categoryId);
		if($categoryInfo && $categoryInfo->type == "homepage"){
			throw new ForbiddenRequestException("You can not delete homepage category.");
		}

		//delete
		$this->categoriesModel->update($categoryId, array("status"=>"trash"));

		//repair parent
		$this->categoriesModel->getAllForMenu()
			->where("parent_id", $categoryId)
			->update(array("parent_id" => $categoryInfo->parent_id));

		//repair left-right
		$left = $categoryInfo->category_left;
		$right = $categoryInfo->category_right;
		$width = $right - $left + 1;

		$this->categoriesModel->findAll()
			->where("category_right >= ?", $right)
			->update(array("category_right"=> new \Nette\Database\SqlLiteral("`category_right` - ".$width)));

		$this->categoriesModel->findAll()
			->where("category_left >= ?", $left)
			->update(array("category_left"=> new \Nette\Database\SqlLiteral("`category_left` - ".$width)));

		return $categoryInfo->parent_id;
	}


	/**
	 * Update tree positions
	 */
	public function updateTreePositions(array $treePositions)
	{
		//update position
		$this->categoriesModel->updateTreePositions($treePositions);

		$this->recalculateTree();
	}


	/**
	 * Find by category id
	 */
	public function getRevisionsCount(int $id): int
	{
		if ($id == null) {
			return 0;
		}
		return $this->categoriesModel->findAll()->where("history_id", $id)->count();
	}


	/**
	 * Find by category id
	 */
	public function makeBackup(int $id): void
	{
		//$this->categorysModel->getDatabase()->query("INSERT INTO ".$this->categorysModel->getTableName()." SELECT *, null AS category_id FROM ".$this->categorysModel->getTableName()." WHERE category_id = 10");
		$categoryToDuplicate = ArrayHash::from($this->categoriesModel->getById($id)?->toArray());
		$categoryToDuplicate->history_id = $categoryToDuplicate->category_id;
		$categoryToDuplicate->category_id = null;

		//category
		$newId = $this->categoriesModel->insert($categoryToDuplicate);

		//description
		$allWithTranslations = $this->categoriesModel->getTranslationTable()
			->select(Model\Categories::TRANSLATION_TABLE_NAME.".*")
			->where("category_descriptions.category_id", $id)
			->fetchAll();
		foreach ($allWithTranslations as $allWithTranslation){
			$translation = ArrayHash::from($allWithTranslation->toArray());
			unset($translation->category_id);
			unset($translation->update_date);
			$this->categoriesModel->insertTranslation($newId, $allWithTranslation->language, $translation);
		}

		//category_file
		$allFiles = $this->categoriesModel->getRelationFile($id)->fetchAll();
		foreach ($allFiles as $allFile){
			$file = ArrayHash::from(array(
				"file_id" => $allFile->file_id,
				"is_main" => $allFile->is_main,
				"position" => $allFile->position,
			));
			$this->categoriesModel->insertRelationFile($newId, $file->file_id, $file);
		}

		//category_article
		$allArticles = $this->categoriesModel->getDatabase()->table(Model\Categories::RELATION_ARTICLE_TABLE_NAME)
			->select(Model\Categories::RELATION_ARTICLE_TABLE_NAME.".*")
			->where("category_id", $id)
			->fetchAll();
		/** @var array $allArticle */
		foreach ($allArticles as $allArticle){
			$article = ArrayHash::from($allArticle);
			unset($article->category_id);
			$this->categoriesModel->insertRelationArticle($newId, $article->article_id, $article);
		}

		//category_meta
		/*$allMetas = $this->categorysModel->getRelationCategory($id)->fetchAll();
		foreach ($allMetas as $allMeta){
			$meta = ArrayHash::from($allMeta);
			unset($meta->category_id);
			$this->categoriesModel->insertRelationCategory($meta->category_id, $newId, $meta);
		}*/

		//category_tag
		$allTags = $this->categoriesModel->getRelationTagsTable()
			->select($this->categoriesModel->getRelationTagsTable()->getName().".*")
			->where("category_id", $id)
			->fetchAll();
		/** @var array $allTag */
		foreach ($allTags as $allTag){
			$tag = ArrayHash::from($allTag);
			unset($tag->category_id);
			$this->categoriesModel->insertRelationTags($newId, $tag->tag_id);
		}

	}


	/**
	 * Find by category id
	 */
	public function getRevisions(int $id): ?array
	{
		if($id == null){
			return null;
		}
		return $this->categoriesModel->findAll()->where("history_id", $id)->order("create_date")->fetchAll();
	}


	/**
	 * Create tree from list
	 */
	public function recalculateTree(): void
	{
		//list
		$categoriesList = $this->categoriesModel->getAllForMenu()
			->select("categories.category_id, categories.parent_id")
			->order("position ASC")
			->fetchAssoc("parent_id|category_id");

		$parents = $categoriesList[null];
		unset($categoriesList[null]);
		/**
		 * @var int $parentId
		 * @var array $parent
		 */
		foreach ($parents as $parentId => $parent){
			$parents[$parentId] = $this->createTree($parent, $categoriesList);
		}

		//set left-right
		$left = 1;
		foreach ($parents as $parentId => &$parent){
			$left = $this->setLeftRight($parent, $left);
		}
	}


	/**
	 * Set left-right to node
	 */
	private function setLeftRight(array $node, int $left): int
	{
		$node['left'] = $left++;
		foreach ($node['childs'] as $child){
			$left = $this->setLeftRight($child, $left);
		}
		$node['right'] = $left++;

		//save to DB
		$this->categoriesModel->findAll()
			->where("category_id", $node['category_id'])
			->update(array(
				"category_left"=> $node['left'],
				"category_right"=> $node['right'],
			));

		return $left;
	}



	/**
	 * Get all comments with all childs
	 */
	public function getAllCommentsWithChilds($language, string $type, int $columnId, \Nette\Utils\Paginator $paginator): array
	{

		$comments = $this->categoriesModel->getAllInLanguage($language);

		$commentsChilds = clone $comments;

		$comments->where("parent_id", null)
			->order("create_date DESC");
		//item count
		$itemsCount = $comments->count();
		$paginator->setItemCount($itemsCount);
		//normal list
		$commentsList = $comments->limit($paginator->getItemsPerPage(), $paginator->getOffset())->fetchAssoc("comment_id");

		//child list
		$childList = $commentsChilds->where("parent_id IS NOT NULL")->order("left ASC")->fetchAssoc("parent_id|comment_id");

		foreach ($commentsList as $commentId => $comment) {
			$commentsList[$commentId] = $this->createTree($comment, $childList);
		}

		return $commentsList;
	}


	/**
	 * Create tree from list
	 */
	protected function createTree(array $parent, array $childList)
	{
		$parentComment = \Nette\Utils\ArrayHash::from($parent);
		$parentCommentId = $parentComment->category_id;
		if (in_array($parentCommentId, array_keys($childList))) {
			$parentComment->childs = $childList[$parentCommentId];
			//walk over all childs
			foreach ($parentComment->childs as $childId => $child) {
				$parentComment->childs[$childId] = $this->createTree($child, $childList);
			}
		} else {
			$parentComment->childs = array();
		}

		return $parentComment;
	}

}
