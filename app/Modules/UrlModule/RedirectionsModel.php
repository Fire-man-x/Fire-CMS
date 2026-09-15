<?php
declare(strict_types=1);

namespace App\Modules\UrlModule;


use App\Model\BaseModel;
use Nette\Database\Explorer;

/**
 * Url redirections Model
 */
class RedirectionsModel extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('url_redirections');
		$this->setColumnId('url_redirection_id');
	}

	public function getAllForGrid(): \Nette\Database\Table\Selection
	{
		return $this->findAll()->order("old_url ASC")->order($this->getColumnId());
	}
}
