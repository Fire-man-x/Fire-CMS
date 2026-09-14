<?php
declare(strict_types=1);

namespace App\Modules\CommentsModule;

use App\Model;
use App\Modules\CommentsModule\Model\Comments;
use App\Modules\CommentsModule\Model\ISubComments;
use Nette\Security\User;

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
		private Model\Articles $articlesModel,
		private Model\Categories $categoriesModel)
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
		$articles = $this->articlesModel->getRelationCommentsTable()->where("comment_id", $commentId);
		if($articles){
			return self::TYPE_ARTICLE;
		}

		$categories = $this->categoriesModel->getRelationCommentsTable()->where("comment_id", $commentId);
		if($categories){
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
			$data["created_by"] = $this->user->getId();
			$data["author"] = null;
			$data["author_email"] = null;
		} else {
			if(empty($data["author"]) || empty($data["author_email"])){
				throw new \InvalidArgumentException("Author or email is empty.");
			}
			$data["created_by"] = null;
		}

		//begin
		$this->commentsModel->getDatabase()->beginTransaction();

		//right
		$right = $model->getRelationComments($relationColumnId)
			->select("IFNULL(MAX(comment.right), 0) AS max_right")
			->fetchField();
		$data["left"] = $right+1;
		$data["right"] = $right+2;

		$commentId = $this->commentsModel->insert($data);

		$model->insertRelationComments($relationColumnId, $commentId);

		//commit
		$this->commentsModel->getDatabase()->commit();

		return $commentId;
	}


	/**
	 * Insert
	 * @param \Nette\Utils\ArrayHash
	 */
	public function insertReply($replyToId, $data): int
	{
		$type = $this->getTypeByStoredItem($replyToId);
		$model = $this->getModelByType($type);

		$data["parent_id"] = $replyToId;

		if ($this->user->isLoggedIn() && $this->user->getId() != null) {
			$data["created_by"] = $this->user->getId();
			$data["author"] = null;
			$data["author_email"] = null;
		} else {
			if(empty($data["author"]) || empty($data["author_email"])){
				throw new \InvalidArgumentException("Author or email is empty.");
			}
			$data["created_by"] = null;
		}

		//begin
		$this->commentsModel->getDatabase()->beginTransaction();

		//right
		$parentComment = $model->getRelationCommentsTable()
			->select("article_id")
			->select("comment.right")
			->where("comment.comment_id", $replyToId)
			->fetch();

		$articleId = $parentComment["article_id"];
		$right = $parentComment["right"];

		$data["left"] = $right;
		$data["right"] = $right+1;

		$allComments = $model->getRelationComments($articleId)->fetchPairs("comment_id", "article_id");
		$allCommentsIds = array_keys($allComments);

		$this->commentsModel->getAll()
			->where("comment_id", $allCommentsIds)
			->where("right >= ?", $right)
			->update(array("right"=> new \Nette\Database\SqlLiteral("`right` + 2")));

		$this->commentsModel->getAll()
			->where("comment_id", $allCommentsIds)
			->where("left >= ?", $right)
			->update(array("left"=> new \Nette\Database\SqlLiteral("`left` + 2")));

		$commentId = $this->commentsModel->insert($data);

		$model->insertRelationComments($articleId, $commentId);

		//commit
		$this->commentsModel->getDatabase()->commit();

		return $commentId;
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

		$articleId = $model->getRelationCommentsTable()
			->select("article_id")
			->where("comment_id", $commentId)
			->fetchField("article_id");

		$allComments = $model->getRelationComments($articleId)->fetchPairs("comment_id", "article_id");
		$allCommentsIds = array_keys($allComments);

		$comment = $this->commentsModel->findById($commentId)->fetch();

		$left = $comment["left"];
		$right = $comment["right"];
		$width = $right - $left + 1;

		$this->commentsModel->getAll()
			->where("left BETWEEN ? AND ?", $left, $right)
			->delete();

		$this->commentsModel->getAll()
			->where("comment_id", $allCommentsIds)
			->where("right >= ?", $right)
			->update(array("right"=> new \Nette\Database\SqlLiteral("`right` - ".$width)));

		$this->commentsModel->getAll()
			->where("comment_id", $allCommentsIds)
			->where("left >= ?", $left)
			->update(array("left"=> new \Nette\Database\SqlLiteral("`left` - ".$width)));
	}


	/**
	 * Create tree from list
	 * @param v $language
	 */
	public function recalculateTree($language, string $type, int $columnId)
	{
		$comments = $this->commentsModel->getAllInLanguage($language)
			->order("create_date ASC");
		if($type == self::TYPE_CATEGORY){
			$comments->where(":category_comments.category_id", $columnId);
		}
		if($type == self::TYPE_ARTICLE){
			$comments->where(":article_comments.article_id", $columnId);
		}
		//list
		$commentsList = $comments->select("comments.comment_id, comments.parent_id")->fetchAssoc("parent_id|comment_id");

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
		$this->commentsModel->getAll()
			->where("comment_id", $node->comment_id)
			->update(array(
				"left"=> $node->left,
				"right"=> $node->right
			));

		return $left;
	}



	/**
	 * Get all comments with all childs
	 * @param v $language
	 */
	public function getAllCommentsWithChilds($language, string $type, int $columnId, \Nette\Utils\Paginator $paginator): array
	{

		$comments = $this->commentsModel->getAllInLanguage($language);
		if($type == self::TYPE_CATEGORY){
			$comments->where(":category_comments.category_id", $columnId);
		}
		if($type == self::TYPE_ARTICLE){
			$comments->where(":article_comments.article_id", $columnId);
		}

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
		$parentCommentId = $parentComment->comment_id;
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
