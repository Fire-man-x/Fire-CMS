<?php
declare(strict_types=1);

namespace App\Plugins\Statistics;

use Nette\Database\Explorer;

class Instalation
{

	/** @inject */
	public Explorer $database;

	public function __construct(Explorer $database)
	{
		$this->database = $database;
	}

	public function install(): void
	{
		$this->database->query(
			"CREATE TABLE `statistics` (
			  `statistics_id` int(11) unsigned NOT null AUTO_INCREMENT,
			  `create_date` timestamp NOT null DEFAULT CURRENT_TIMESTAMP,
			  `session` int(10) unsigned NOT null,
			  `agent` text COLLATE utf8_czech_ci NOT null,
			  `hits` int NOT null,
			  PRIMARY KEY (`statistics_id`),
			  KEY `created_by` (`created_by`),
			  CONSTRAINT `statistics_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci"
		);
	}
	public function uninstall(): void
	{
		$this->database->query(
			"DROP TABLE IF EXISTS `statistics`"
		);
	}



}
