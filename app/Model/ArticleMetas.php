<?php
declare(strict_types=1);

namespace App\Model;

use App\Service\LanguageService;

/**
 * ArticleMetas Model
 */
class ArticleMetas extends BaseSubMetas
{


	/**
	 * Constructor
	 * @param \Nette\Database\Explorer $database
	 * @param LanguageService $languages
	 */
	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages, \Nette\Security\User $user)
	{
		parent::__construct($database, $languages, $user);

		$this->setTableName('firecms_articleMetas');
		$this->setColumnId('article_meta_id');
		$this->setReferenceColumn('article_id');
	}


	/**
	 * Find by article id
	 * @param int $id
	 * @return \Nette\Database\Table\Selection
	 */
	public function findByArticleId($id)
	{
		return $this->findByColumnId($id);
	}

}
