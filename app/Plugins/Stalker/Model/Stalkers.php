<?php
declare(strict_types=1);

namespace App\Plugins\Stalker\Model;

use App\Model\BaseModel;
use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;

/**
 * Stalkers Model
 */
class Stalkers extends BaseModel
{
	/**
	 * Constructor
	 * @param Explorer $database
	 */
	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_plugin_stalkers');
		$this->setForeignKeyColumn('stalkerId');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		//@note: cannot be used user - is "guest" with id null
		//$data->createdBy = $this->user->getId();
		return parent::insert($data);
	}

}
