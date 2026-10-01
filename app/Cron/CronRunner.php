<?php
declare(strict_types=1);

namespace App\Cron;

use App\Model\Database\CronTasks;
use Cron\CronExpression;
use Tracy\Debugger;

/**
 * Spustí úlohy, které jsou na řadě. Úloha je na řadě, když její poslední plánovaný čas (podle cron výrazu,
 * na minuty) nastal později než její poslední běh. Díky tomu nevadí, když cron volá endpoint nepravidelně
 * nebo vynechá - propásnutý běh se dožene jednou, ne víckrát. Úloha, která ještě nikdy neběžela, se spustí
 * při nejbližším volání.
 *
 * Souběžné volání (např. předchozí běh ještě neskončil) se odmítne zámkem v `temp/cron.lock`.
 */
class CronRunner
{

	/**
	 * @param CronTask[] $tasks
	 */
	public function __construct(
		private readonly array $tasks,
		private readonly CronTasks $cronTasks,
		private readonly string $tempDir,
	) {
	}


	/**
	 * @return list<CronTaskResult>|null null = jiný běh cronu ještě neskončil, nic se nespustilo
	 */
	public function run(?\DateTimeImmutable $now = null): ?array
	{
		$now ??= new \DateTimeImmutable();

		$lock = fopen($this->tempDir . '/cron.lock', 'c');
		if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
			return null;
		}

		try {
			$results = [];
			foreach ($this->tasks as $task) {
				$results[] = $this->isDue($task, $now)
					? $this->runTask($task, $now)
					: new CronTaskResult($task->getName(), CronTaskResult::StatusSkipped);
			}

			return $results;
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}


	/**
	 * @return CronTask[]
	 */
	public function getTasks(): array
	{
		return $this->tasks;
	}


	public function isDue(CronTask $task, \DateTimeImmutable $now): bool
	{
		$lastRunAt = $this->cronTasks->getLastRunAt($task->getName());
		if ($lastRunAt === null) {
			return true;
		}

		$lastScheduled = (new CronExpression($task->getSchedule()))->getPreviousRunDate($now, 0, true);

		return $lastScheduled > $lastRunAt;
	}


	private function runTask(CronTask $task, \DateTimeImmutable $now): CronTaskResult
	{
		$name = $task->getName();
		$this->cronTasks->markStarted($name, $now);
		$start = microtime(true);

		try {
			$message = $task->run($now);
			$status = CronTaskResult::StatusOk;
		} catch (\Throwable $e) {
			Debugger::log($e, Debugger::EXCEPTION);
			$message = get_class($e) . ': ' . $e->getMessage();
			$status = CronTaskResult::StatusError;
		}

		$duration = microtime(true) - $start;
		$this->cronTasks->markFinished($name, $status, $message, $duration);

		return new CronTaskResult($name, $status, $message, round($duration, 3));
	}

}
