<?php
declare(strict_types=1);

namespace App\Modules\CommentsModule\Model;

use App\Model\BaseModel;
use App\Service\LanguageService;
use Nette\Database\Explorer;
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

		$this->setTableName('comments');
		$this->setColumnId('comment_id');
	}


	/**
	 * Get database
	 * @return \Nette\Database\Context
	 */
	public function getDatabase()
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
	 * @return \Nette\Database\Table\Selection
	 */
	public function getAllInLanguage($language)
	{
		return $this->findAll()->where("language_id", $language);
	}

}
