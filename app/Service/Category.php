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

		$hasParent = isset($values["parentId"]);
		
		//last right
		$rightQuery = $this->categoriesModel->getAllForMenu();
		if($hasParent){
			$rightQuery->select($this->categoriesModel->getTableName().".categoryRight AS max_right");
			$rightQuery->where("id", $values["parentId"]);
		}else{
			$rightQuery->select("IFNULL(MAX(".$this->categoriesModel->getTableName().".categoryRight), 0) AS max_right");
		}
		$right = $rightQuery->fetchField();
		if ($hasParent) {
			$values["categoryLeft"] = $right;
			$values["categoryRight"] = $right + 1;

			$this->categoriesModel->getAllForMenu()
				->where("categoryRight >= ?", $right)
				->update(array("categoryRight" => new \Nette\Database\SqlLiteral("`categoryRight` + 2")));

			$this->categoriesModel->getAllForMenu()
				->where("categoryLeft >= ?", $right)
				->update(array("categoryLeft" => new \Nette\Database\SqlLiteral("`categoryLeft` + 2")));
		} else {
			$values["categoryLeft"] = $right + 1;
			$values["categoryRight"] = $right + 2;
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
			->where("parentId", $categoryId)
			->update(array("parentId" => $categoryInfo->parentId));

		//repair left-right
		$left = $categoryInfo->categoryLeft;
		$right = $categoryInfo->categoryRight;
		$width = $right - $left + 1;

		$this->categoriesModel->findAll()
			->where("categoryRight >= ?", $right)
			->update(array("categoryRight"=> new \Nette\Database\SqlLiteral("`categoryRight` - ".$width)));

		$this->categoriesModel->findAll()
			->where("categoryLeft >= ?", $left)
			->update(array("categoryLeft"=> new \Nette\Database\SqlLiteral("`categoryLeft` - ".$width)));

		return $categoryInfo->parentId;
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
		return $this->categoriesModel->findAll()->where("historyId", $id)->count();
	}


	/**
	 * Find by category id
	 */
	public function makeBackup(int $id): void
	{
		//$this->categorysModel->getDatabase()->query("INSERT INTO ".$this->categorysModel->getTableName()." SELECT *, null AS categoryId FROM ".$this->categorysModel->getTableName()." WHERE categoryId = 10");
		$categoryToDuplicate = ArrayHash::from($this->categoriesModel->getById($id)?->toArray());
		$categoryToDuplicate->historyId = $categoryToDuplicate->id;
		$categoryToDuplicate->id = null;

		//category
		$newId = $this->categoriesModel->insert($categoryToDuplicate);

		//description
		$allWithTranslations = $this->categoriesModel->getTranslationTable()
			->select(Model\Categories::TRANSLATION_TABLE_NAME.".*")
			->where(Model\Categories::TRANSLATION_TABLE_NAME.".categoryId", $id)
			->fetchAll();
		foreach ($allWithTranslations as $allWithTranslation){
			$translation = ArrayHash::from($allWithTranslation->toArray());
			unset($translation->categoryId);
			unset($translation->updateDate);
			$this->categoriesModel->insertTranslation($newId, $allWithTranslation->language, $translation);
		}

		//category_file
		$allFiles = $this->categoriesModel->getRelationFile($id)->fetchAll();
		foreach ($allFiles as $allFile){
			$file = ArrayHash::from(array(
				"fileId" => $allFile->fileId,
				"isMain" => $allFile->isMain,
				"position" => $allFile->position,
			));
			$this->categoriesModel->insertRelationFile($newId, $file->fileId, $file);
		}

		//category_article
		$allArticles = $this->categoriesModel->getDatabase()->table(Model\Categories::RELATION_ARTICLE_TABLE_NAME)
			->select(Model\Categories::RELATION_ARTICLE_TABLE_NAME.".*")
			->where("categoryId", $id)
			->fetchAll();
		/** @var \Nette\Database\Table\ActiveRow $allArticle */
		foreach ($allArticles as $allArticle){
			$article = ArrayHash::from($allArticle->toArray());
			unset($article->categoryId);
			$this->categoriesModel->insertRelationArticle($newId, $article->articleId, $article);
		}

		//category_meta
		/*$allMetas = $this->categorysModel->getRelationCategory($id)->fetchAll();
		foreach ($allMetas as $allMeta){
			$meta = ArrayHash::from($allMeta);
			unset($meta->categoryId);
			$this->categoriesModel->insertRelationCategory($meta->categoryId, $newId, $meta);
		}*/

		//category_tag
		$allTags = $this->categoriesModel->getRelationTagsTable()
			->select($this->categoriesModel->getRelationTagsTable()->getName().".*")
			->where("categoryId", $id)
			->fetchAll();
		/** @var \Nette\Database\Table\ActiveRow $allTag */
		foreach ($allTags as $allTag){
			$tag = ArrayHash::from($allTag->toArray());
			unset($tag->categoryId);
			$this->categoriesModel->insertRelationTags($newId, $tag->tagId);
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
		return $this->categoriesModel->findAll()->where("historyId", $id)->order("createDate")->fetchAll();
	}


	/**
	 * Create tree from list
	 */
	public function recalculateTree(): void
	{
		//list
		$categoriesList = $this->categoriesModel->getAllForMenu()
			->select("categories.id, categories.parentId")
			->order("position ASC")
			->fetchAssoc("parentId|id");

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
			->where("id", $node['id'])
			->update(array(
				"categoryLeft"=> $node['left'],
				"categoryRight"=> $node['right'],
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

		$comments->where("parentId", null)
			->order("createDate DESC");
		//item count
		$itemsCount = $comments->count();
		$paginator->setItemCount($itemsCount);
		//normal list
		$commentsList = $comments->limit($paginator->getItemsPerPage(), $paginator->getOffset())->fetchAssoc("id");

		//child list
		$childList = $commentsChilds->where("parentId IS NOT NULL")->order("left ASC")->fetchAssoc("parentId|id");

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
		$parentCommentId = $parentComment->id;
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
