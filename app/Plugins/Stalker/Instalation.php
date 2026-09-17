<?php
declare(strict_types=1);

namespace App\Plugins\Stalker;

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
			"CREATE TABLE `firecms_plugin_stalkers` (
			  `stalker_id` int(11) unsigned NOT null AUTO_INCREMENT,
			  `create_date` timestamp NOT null DEFAULT CURRENT_TIMESTAMP,
			  `created_by` int(10) unsigned NOT null,
			  `url` text NOT null,
			  PRIMARY KEY (`stalker_id`),
			  KEY `created_by` (`created_by`),
			  CONSTRAINT `stalkers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `firecms_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
		);
	}
	public function uninstall(): void
	{
		$this->database->query(
			"DROP TABLE IF EXISTS `firecms_plugin_stalkers`"
		);
	}



}
