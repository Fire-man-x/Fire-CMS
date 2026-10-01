<?php
declare(strict_types=1);

namespace App\Cron;

/**
 * Výsledek jedné úlohy při jednom volání cronu
 */
final class CronTaskResult
{
	public const string StatusOk = 'ok';
	public const string StatusError = 'error';
	public const string StatusSkipped = 'skipped';


	public function __construct(
		public readonly string $name,
		public readonly string $status,
		public readonly ?string $message = null,
		public readonly ?float $duration = null,
	) {
	}


	/**
	 * @return array{name: string, status: string, message: string|null, duration: float|null}
	 */
	public function toArray(): array
	{
		return [
			'name' => $this->name,
			'status' => $this->status,
			'message' => $this->message,
			'duration' => $this->duration,
		];
	}

}
