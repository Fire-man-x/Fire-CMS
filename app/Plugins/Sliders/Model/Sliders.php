<?php
declare(strict_types=1);

namespace App\Plugins\Sliders\Model;

use App\Model\BaseModel;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Utils\ArrayHash;

/**
 * Sliders Model
 */
class Sliders extends BaseModel
{
	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('sliders');
		$this->setColumnId('slider_id');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->create_date = new SqlLiteral("NOW()");
		return parent::insert($data);
	}

}
