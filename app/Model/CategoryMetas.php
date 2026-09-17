<?php
declare(strict_types=1);

namespace App\Model;

use App\Service\LanguageService;

/**
 * CategoryMetas Model
 */
class CategoryMetas extends BaseSubMetas
{


	/**
	 * Constructor
	 * @param \Nette\Database\Explorer $database
	 * @param LanguageService $languages
	 */
	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages, \Nette\Security\User $user)
	{
		parent::__construct($database, $languages, $user);

		$this->setTableName('firecms_categoryMetas');
		$this->setColumnId('category_meta_id');
		$this->setReferenceColumn('category_id');
	}


	/**
	 * Find by category id
	 * @param int $id
	 * @return \Nette\Database\Table\Selection
	 */
	public function findByCategoryId($id)
	{
		return $this->findByColumnId($id);
	}

}
