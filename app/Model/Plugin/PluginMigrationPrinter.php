<?php
declare(strict_types=1);

namespace App\Model\Plugin;

use Nextras\Migrations\Entities\File;
use Nextras\Migrations\Exception;
use Nextras\Migrations\IPrinter;

/**
 * Captures the outcome of a Runner::run() call instead of writing to stdout
 * like Printers\Console does - needed because PluginMigrator runs migrations
 * from a normal web request (Plugins admin toggle), not from bin/console.
 */
final class PluginMigrationPrinter implements IPrinter
{
	private int $executedCount = 0;

	private ?string $error = null;


	public function printIntro(string $mode): void
	{
	}


	public function printToExecute(array $toExecute): void
	{
	}


	public function printExecute(File $file, int $count, float $time): void
	{
		$this->executedCount++;
	}


	public function printDone(): void
	{
	}


	public function printError(Exception $e): void
	{
		$this->error = $e->getMessage();
	}


	public function printSource(string $code): void
	{
	}


	public function getExecutedCount(): int
	{
		return $this->executedCount;
	}


	public function getError(): ?string
	{
		return $this->error;
	}
}
