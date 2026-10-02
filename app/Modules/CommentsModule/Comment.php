<?php
declare(strict_types=1);

namespace App\Modules\CommentsModule;

use App\Model;
use App\Modules\CommentsModule\Model\Comments;
use App\Modules\CommentsModule\Model\ISubComments;
use Nette\Security\User;
use Nette\Utils\ArrayHash;

/**
 * Comment Service
 */
class Comment
{

	const TYPE_ARTICLE = "article";
	const TYPE_CATEGORY = "category";

	/**
	 * Constructor
	 */
	public function __construct(
		private User $user,
		private Comments $commentsModel,
		private Model\Database\Articles $articlesModel,
		private Model\Database\Categories $categoriesModel)
	{
	}


	/**
	 * Get model by type
	 * @throws \InvalidArgumentException
	 */
	public function getModelByType(string $type):ISubComments
	{
		switch ($type) {
			case self::TYPE_ARTICLE:
				return $this->articlesModel;
			case self::TYPE_CATEGORY:
				return $this->categoriesModel;
			default:
				throw new \InvalidArgumentException("Model for type '$type' doesn't exist.");
		}
	}


	/**
	 * Get model by stored item
	 * @throws \InvalidArgumentException
	 */
	public function getTypeByStoredItem(int $commentId): string
	{
		// count(), ne if ($selection) - objekt Selection je vždy pravdivý (dřív vždy vyšel článek)
		if ($this->articlesModel->getRelationCommentsTable()->where("commentId", $commentId)->count('*') > 0) {
			return self::TYPE_ARTICLE;
		}

		if ($this->categoriesModel->getRelationCommentsTable()->where("commentId", $commentId)->count('*') > 0) {
			return self::TYPE_CATEGORY;
		}

		throw new \InvalidArgumentException("Model not found.");
	}


	/**
	 * Insert
	 * @param \Nette\Utils\ArrayHash
	 */
	public function insert($type, $relationColumnId, $data): int
	{
		$model = $this->getModelByType($type);

		if ($this->user->isLoggedIn() && $this->user->getId() != null) {
			$data["createdBy"] = $this->user->getId();
			$data["author"] = null;
			$data["authorEmail"] = null;
		} else {
			if(empty($data["author"]) || empty($data["authorEmail"])){
				throw new \InvalidArgumentException("Author or email is empty.");
			}
			$data["createdBy"] = null;
		}

		// transaction(): při výjimce rollback (dřív zůstala transakce otevřená), vnořené volání nevadí
		return $this->commentsModel->getDatabase()->transaction(function () use ($model, $relationColumnId, $data): int {
			//right
			$right = $model->getRelationComments($relationColumnId)
				->select("COALESCE(MAX(comment.right), 0) AS max_right")
				->fetchField();
			$data["left"] = $right+1;
			$data["right"] = $right+2;

			$commentId = $this->commentsModel->insert($data);

			$model->insertRelationComments($relationColumnId, $commentId);

			return $commentId;
		});
	}


	/**
	 * Insert
	 * @param \Nette\Utils\ArrayHash
	 */
	public function insertReply($replyToId, $data): int
	{
		$type = $this->getTypeByStoredItem($replyToId);
		$model = $this->getModelByType($type);

		$data["parentId"] = $replyToId;

		if ($this->user->isLoggedIn() && $this->user->getId() != null) {
			$data["createdBy"] = $this->user->getId();
			$data["author"] = null;
			$data["authorEmail"] = null;
		} else {
			if(empty($data["author"]) || empty($data["authorEmail"])){
				throw new \InvalidArgumentException("Author or email is empty.");
			}
			$data["createdBy"] = null;
		}

		// transaction(): při výjimce rollback (dřív zůstala transakce otevřená), vnořené volání nevadí
		// sloupec vazby podle typu (articleId/categoryId) - dřív natvrdo articleId, odpověď v kategorii nefungovala
		$column = $model->getForeignKeyColumn();

		return $this->commentsModel->getDatabase()->transaction(function () use ($model, $column, $replyToId, $data): int {
			//right
			$parentComment = $model->getRelationCommentsTable()
				->select($column)
				->select("comment.right")
				->where("comment.id", $replyToId)
				->fetch();
			if (!$parentComment instanceof \Nette\Database\Table\ActiveRow) {
				throw new \InvalidArgumentException("Comment '$replyToId' doesn't exist.");
			}

			$relationId = (int) $parentComment[$column];
			$right = $parentComment["right"];

			$data["left"] = $right;
			$data["right"] = $right+1;

			$allComments = $model->getRelationComments($relationId)->fetchPairs("commentId", $column);
			$allCommentsIds = array_keys($allComments);

			$this->commentsModel->findAll()
				->where("id", $allCommentsIds)
				->where("right >= ?", $right)
				->update(array("right"=> new \Nette\Database\SqlLiteral($this->commentsModel->delimite("right") . " + 2")));

			$this->commentsModel->findAll()
				->where("id", $allCommentsIds)
				->where("left >= ?", $right)
				->update(array("left"=> new \Nette\Database\SqlLiteral($this->commentsModel->delimite("left") . " + 2")));

			$commentId = $this->commentsModel->insert($data);

			$model->insertRelationComments($relationId, $commentId);

			return $commentId;
		});
	}


	/**
	 * Delete
	 */
	public function delete(int $commentId)
	{
		/*
		SELECT @myLeft := lft, @myRight := rgt, @myWidth := rgt - lft + 1
		FROM nested_category
		WHERE name = 'GAME CONSOLES';

		DELETE FROM nested_category WHERE lft BETWEEN @myLeft AND @myRight;

		UPDATE nested_category SET rgt = rgt - @myWidth WHERE rgt > @myRight;
		UPDATE nested_category SET lft = lft - @myWidth WHERE lft > @myRight;
		 */
		$type = $this->getTypeByStoredItem($commentId);
		$model = $this->getModelByType($type);
		$column = $model->getForeignKeyColumn();

		$this->commentsModel->getDatabase()->transaction(function () use ($model, $column, $commentId): void {
			$relationId = $model->getRelationCommentsTable()
				->where("commentId", $commentId)
				->fetchField($column);
			$comment = $this->commentsModel->getById($commentId);
			if ($relationId === null || !$comment instanceof \Nette\Database\Table\ActiveRow) {
				return;
			}

			// left/right se čísluje zvlášť pro každý článek/kategorii - mazat a posouvat jen v rámci téže položky
			// (dřív "left BETWEEN" nad všemi komentáři smazal i komentáře jiných článků se stejnými čísly)
			$allCommentsIds = array_keys($model->getRelationComments((int) $relationId)->fetchPairs("commentId", $column));

			$left = (int) $comment->left;
			$right = (int) $comment->right;
			$width = $right - $left + 1;

			$this->commentsModel->findAll()
				->where("id", $allCommentsIds)
				->where("left BETWEEN ? AND ?", $left, $right)
				->delete();

			$this->commentsModel->findAll()
				->where("id", $allCommentsIds)
				->where("right >= ?", $right)
				->update(array("right"=> new \Nette\Database\SqlLiteral($this->commentsModel->delimite("right") . " - " . $width)));

			$this->commentsModel->findAll()
				->where("id", $allCommentsIds)
				->where("left >= ?", $left)
				->update(array("left"=> new \Nette\Database\SqlLiteral($this->commentsModel->delimite("left") . " - " . $width)));
		});
	}


	/**
	 * Create tree from list
	 */
	public function recalculateTree(string $language, string $type, int $columnId): void
	{
		$comments = $this->commentsModel->getAllInLanguage($language)
			->order("createDate ASC");
		if($type == self::TYPE_CATEGORY){
			$comments->where(":" . Model\Database\Categories::RELATION_COMMENT_CATEGORY_TABLE_NAME . ".categoryId", $columnId);
		}
		if($type == self::TYPE_ARTICLE){
			$comments->where(":" . Model\Database\Articles::RELATION_COMMENT_TABLE_NAME . ".articleId", $columnId);
		}
		//list
		$commentsList = $comments->select("comments.id, comments.parentId")->fetchAssoc("parentId|id");

		\Tracy\Debugger::$maxDepth=6;
		//dump($commentsList);
		$parents = $commentsList[null];
		unset($commentsList[null]);
		foreach ($parents as $parentId => $parent){
			$parents[$parentId] = $this->createTree($parent, $commentsList);
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
		$node->left = $left++;
		foreach ($node->childs as $child){
			$left = $this->setLeftRight($child, $left);
		}
		$node->right = $left++;

		//save to DB
		$this->commentsModel->findAll()
			->where("id", $node->id)
			->update(array(
				"left"=> $node->left,
				"right"=> $node->right
			));

		return $left;
	}



	/**
	 * Get all comments with all childs
	 * Jen schválené komentáře (status "publish") - výpis je pro web, pending/trash vidí jen administrace.
	 * Odpovědi neschváleného komentáře se nezobrazí ani když jsou schválené (nemají kam se zavěsit).
	 */
	public function getAllCommentsWithChilds(string $language, string $type, int $columnId, \Nette\Utils\Paginator $paginator): array
	{

		$comments = $this->commentsModel->getAllInLanguage($language)
			->where($this->commentsModel->getTableName() . ".status", "publish");
		if($type == self::TYPE_CATEGORY){
			$comments->where(":" . Model\Database\Categories::RELATION_COMMENT_CATEGORY_TABLE_NAME . ".categoryId", $columnId);
		}
		if($type == self::TYPE_ARTICLE){
			$comments->where(":" . Model\Database\Articles::RELATION_COMMENT_TABLE_NAME . ".articleId", $columnId);
		}

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
	protected function createTree(array $parent, array $childList): ArrayHash
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
