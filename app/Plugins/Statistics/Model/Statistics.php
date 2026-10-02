<?php
declare(strict_types=1);

namespace App\Plugins\Statistics\Model;

use App\Model\Database\BaseModel;
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

		$this->setTableName('firecms_plugin_statistics');
		$this->setForeignKeyColumn('statisticsId');
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
	 * Průměrný počet zásahů za den (na 2 desetinná místa, 0 bez dat). CAST(... AS DATE) místo MySQL DATE()
	 * a identifikátory přes delimite() - MariaDB i PostgreSQL. Dřív deklarovalo int, ale vracelo desetinné číslo.
	 */
	public function getAvgPerDay(): float
	{
		$average = $this->database->query('SELECT ROUND(AVG(sub.hits), 2) FROM'
			. ' (SELECT COUNT(*) AS hits FROM ' . $this->delimite($this->getTableName())
			. ' GROUP BY CAST(' . $this->delimite('createDate') . ' AS DATE)) AS sub')->fetchField();

		return is_numeric($average) ? (float) $average : 0.0;
	}

}
