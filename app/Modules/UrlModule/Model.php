<?php
declare(strict_types=1);

namespace App\Modules\UrlModule;


use App\Model\BaseModel;
use Nette\Database\Explorer;

/**
 * Urls Model
 */
class Model extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('urls');
		$this->setColumnId('url_id');
	}

	public function getAllForGrid(): \Nette\Database\Table\Selection
	{
		return $this->getAll()->order("url ASC")->order($this->getColumnId());
	}


}
