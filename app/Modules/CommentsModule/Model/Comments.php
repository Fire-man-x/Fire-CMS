<?php
declare(strict_types=1);

namespace App\Modules\CommentsModule\Model;

use App\Model\BaseModel;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

/**
 * Comments Model
 */
class Comments extends BaseModel
{

	/**
	 * Constructor
	 */
	public function __construct(Explorer $database, protected LanguageService $languages)
	{
		parent::__construct($database);

		$this->setTableName('firecms_comments');
		$this->setForeignKeyColumn('commentId');
	}


	/**
	 * Get database
	 */
	public function getDatabase(): Explorer
	{
		return $this->database;
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		return parent::insert($data);
	}

	/**
	 * Get all rows
	 */
	public function getAllInLanguage($language): Selection
	{
		return $this->findAll()->where("languageId", $language);
	}

}
