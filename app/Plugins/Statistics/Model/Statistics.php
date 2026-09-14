<?php
declare(strict_types=1);

namespace App\Plugins\Statistics\Model;

use App\Model\BaseModel;
use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;

/**
 * Statistics Model
 */
class Statistics extends BaseModel
{


	/**
	 * Constructor
	 * @param Explorer $database
	 */
	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('statistics');
		$this->setColumnId('statistics_id');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		return parent::insert($data);

		/*$updateStatement["hits"] =  new SqlLiteral("hits + 1");

		$this->database->query("INSERT INTO `" . $this->getTableName() . "` ? ON DUPLICATE KEY UPDATE ? ", $data, $updateStatement);*/
	}


	/**
	 * Get average per day
	 */
	public function getAvgPerDay(): int
	{
		return $this->database->query("SELECT ROUND( AVG(sub.sub_count), 2) FROM "
			. "(SELECT COUNT(*) sub_count FROM `" . $this->getTableName() . "` GROUP BY DATE(`create_date`)) AS sub")->fetchField();
	}

}
