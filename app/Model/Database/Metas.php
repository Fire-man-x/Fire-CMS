<?php
declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;

/**
 * Metas Model
 */
class Metas extends BaseModel
{
	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_metas');
		$this->setForeignKeyColumn('metaId');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		return parent::insert($data);
	}

}
