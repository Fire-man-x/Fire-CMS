<?php
declare(strict_types=1);

namespace App\Model\Database;


use Nette\Database\Explorer;

/**
 * Url redirections Model
 */
class Redirections extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_urlRedirections');
		$this->setForeignKeyColumn('urlRedirectionId');
	}

	public function getAllForGrid(): \Nette\Database\Table\Selection
	{
		return $this->findAll()->order("oldUrl ASC")->order($this->getColumnId());
	}
}
