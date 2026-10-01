<?php
declare(strict_types=1);

namespace App\Cron;

/**
 * Úloha spouštěná cronem přes endpoint `/cron` (App\Presenters\CronPresenter -> CronRunner).
 *
 * Stačí zaregistrovat službu implementující toto rozhraní (v config.neon jádra nebo v config.plugin.neon
 * pluginu). CronRunner si všechny takové služby najde sám (`typed(App\Cron\CronTask)`).
 */
interface CronTask
{

	/**
	 * Unikátní název úlohy - pod ním se v `firecms_cronTasks` eviduje poslední běh. Po přejmenování
	 * se úloha bere jako nová (poběží při nejbližším volání cronu).
	 */
	public function getName(): string;


	/**
	 * Kdy se má úloha spouštět - cron výraz (minuta hodina den měsíc den-v-týdnu), např. `0 3 * * *` = denně
	 * ve 3:00, `30 * * * *` = každou hodinu ve :30. Krok se zapisuje lomítkem za hvězdičkou (každých 5 minut).
	 */
	public function getSchedule(): string;


	/**
	 * Provede úlohu. Výjimka = úloha selhala (zaloguje se a uloží do `lastMessage`, ostatní úlohy běží dál).
	 *
	 * @return string|null krátká zpráva o výsledku (např. „odesláno 3 e-mailů“), uloží se do `lastMessage`
	 */
	public function run(\DateTimeImmutable $now): ?string;

}
