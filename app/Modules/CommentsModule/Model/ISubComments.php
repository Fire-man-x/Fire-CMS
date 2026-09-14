<?php
declare(strict_types=1);

namespace App\Modules\CommentsModule\Model;

/**
 * Interface for relation comments table
 */
interface ISubComments
{


	/**
	 * Relation comments table
	 */
	public function getRelationCommentsTable(): \Nette\Database\Table\Selection;


	/**
	 * Get relation with comment
	 */
	public function getRelationComments(int $id): \Nette\Database\Table\Selection;


	/**
	 * Inserts relation with comment
	 */
	public function insertRelationComments(int $id, int $commentId): void;


	/**
	 * Delete relation with comments
	 */
	public function deleteRelationComment(int $id, int $commentId);

}
