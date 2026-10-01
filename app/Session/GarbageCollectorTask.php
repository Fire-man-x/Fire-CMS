<?php
declare(strict_types=1);

namespace App\Session;

use App\Cron\CronTask;

/**
 * Maže prošlé sessions v databázi (MySQL/PostgreSQL handler). Registruje ji App\Session\DI\SessionHandlerExtension jen
 * pro tyto handlery - Redis maže prošlé klíče sám a u souborů to dělá PHP/systémový cron.
 *
 * Nutné na serverech s `session.gc_probability = 0` (Debian/Ubuntu): tam PHP handler->gc() nikdy nezavolá a tabulka
 * by rostla donekonečna.
 */
final class GarbageCollectorTask implements CronTask
{

	public function __construct(
		private readonly DatabaseSessionHandler $handler,
	) {
	}


	public function getName(): string
	{
		return 'session-gc';
	}


	public function getSchedule(): string
	{
		return '20 * * * *'; // každou hodinu ve :20
	}


	public function run(\DateTimeImmutable $now): string
	{
		return sprintf('smazáno %d prošlých sessions', $this->handler->deleteExpired());
	}

}
