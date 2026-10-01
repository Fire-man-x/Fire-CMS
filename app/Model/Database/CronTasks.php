<?php
declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Explorer;

/**
 * Evidence běhů cron úloh (`firecms_cronTasks`, klíč = CronTask::getName()). Podle `lastRunAt` CronRunner
 * pozná, jestli úloha od posledního běhu propásla plánovaný čas a má se spustit.
 */
class CronTasks extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_cronTasks');
		$this->setColumnId('name');
	}


	public function getLastRunAt(string $name): ?\DateTimeImmutable
	{
		$lastRunAt = $this->findAll()->where('name', $name)->fetch()?->lastRunAt;

		return $lastRunAt instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($lastRunAt) : null;
	}


	/**
	 * Zapíše začátek běhu - ještě před spuštěním úlohy, aby úloha, která shodí celý proces, neběžela
	 * při každém dalším volání cronu znovu.
	 */
	public function markStarted(string $name, \DateTimeImmutable $now): void
	{
		$this->database->query(
			'INSERT INTO ?name ?values ON DUPLICATE KEY UPDATE lastRunAt = VALUES(lastRunAt)',
			$this->getTableName(),
			['name' => $name, 'lastRunAt' => $now],
		);
	}


	public function markFinished(string $name, string $status, ?string $message, float $duration): void
	{
		$this->findAll()->where('name', $name)->update([
			'lastStatus' => $status,
			'lastMessage' => $message !== null ? mb_substr($message, 0, 1000) : null,
			'lastDuration' => round($duration, 3),
			'lastFinishedAt' => new \DateTimeImmutable(),
		]);
	}

}
