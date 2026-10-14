<?php
declare(strict_types=1);

namespace App\Model\Database;


use Nette\Database\Explorer;

/**
 * Urls Model
 */
class Urls extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_urls');
		$this->setForeignKeyColumn('urlId');
	}

	public function getAllForGrid(): \Nette\Database\Table\Selection
	{
		return $this->findAll()->order("url ASC")->order($this->getColumnId());
	}


}
