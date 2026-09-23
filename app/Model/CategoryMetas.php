<?php
declare(strict_types=1);

namespace App\Model;

use App\Service\LanguageService;
use Nette\Database\Table\Selection;

/**
 * CategoryMetas Model
 */
class CategoryMetas extends BaseSubMetas
{


	/**
	 * Constructor
	 */
	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages, \Nette\Security\User $user)
	{
		parent::__construct($database, $languages, $user);

		$this->setTableName('firecms_categoryMetas');
		$this->setForeignKeyColumn('categoryMetaId');
		$this->setReferenceColumn('categoryId');
	}


	/**
	 * Find by category id
	 */
	public function findByCategoryId(int $id): Selection
	{
		return $this->findByColumnId($id);
	}

}
