<?php
declare(strict_types=1);

namespace App\Model;

use App\Service\LanguageService;
use Nette\Utils\ArrayHash;

interface ISubTags
{


	/**
	 * Relation tags table
	 */
	public function getRelationTagsTable(): \Nette\Database\Table\Selection;


	/**
	 * Inserts relation with tag
	 */
	public function insertRelationTags(int $id, int $tagId): void;


	/**
	 * Delete all relation with tags
	 */
	public function deleteAllRelationTags(int $id);


	/**
	 * Delete relation with tags
	 */
	public function deleteRelationTag(int $id, int $tagId);

}
