# Cron — plánované úlohy (`app/Cron/`)

Jádro má jeden HTTP endpoint pro systémový cron: `GET /cron?token=<cronToken>` (nebo hlavička `X-Cron-Token`).
Při každém zavolání spustí všechny úlohy, které jsou na řadě, a vrátí JSON s výsledkem.

## Nastavení na serveru

1. V `config.local.neon` nastavte `parameters: cronToken: '<náhodný řetězec>'` (každé prostředí vlastní).
   Prázdný token (výchozí v `config.neon`) = endpoint je vypnutý a vrací 403.
2. Systémový cron volá endpoint **každou minutu** přes veřejnou doménu webu:
   `* * * * * curl -fsS "https://<doména>/cron?token=<token>" > /dev/null`
   Volejte ho přes skutečnou doménu, ne `127.0.0.1`. Odkazy v e-mailech z cron úloh (`LinkGenerator`) se
   skládají z URL požadavku.
3. `curl -f` vrátí chybu, když endpoint odpoví jinak než 2xx. Kódy: 200 OK, 500 = aspoň jedna úloha selhala
   (detail v logu Tracy a v `firecms_cronTasks.lastMessage`), 409 = předchozí běh ještě neskončil, 403 = token.

## Jak přidat úlohu

Služba implementující `App\Cron\CronTask` (`getName()`, `getSchedule()` = cron výraz, `run()`), zaregistrovaná
v `config.neon` nebo v `config.plugin.neon` pluginu. `CronRunner` dostává všechny takové služby přes
`typed(App\Cron\CronTask)`, nikde jinde se úloha nevyjmenovává. Příklad: `App\Session\GarbageCollectorTask`
(úloha `session-gc`, viz [sessions.md](sessions.md)).

Úloha, která posílá e-maily nebo mění data podle času, by měla být opakovatelná: při chybě (např. SMTP) nechte
záznam ve stavu „nevyřízeno“ a cron ho zkusí při dalším běhu. Pokud existuje záloha bez cronu (např. úprava
stavu při požadavku), zůstává jen pro projekty, kde cron ještě neběží.

## Proč HTTP endpoint, ne `bin/console`

CLI kontejner na serverech bývá kvůli právům `www-data` nespolehlivý (`temp/cache` patří webovému uživateli,
viz `gotchas.md`) a CLI neví, na jaké doméně web běží. Endpoint volaný přes veřejnou doménu tyhle problémy nemá.

## Kdy úloha poběží

- `CronRunner::isDue()`: úloha poběží, když její poslední plánovaný čas (cron výraz na minuty,
  `dragonmantank/cron-expression`) nastal později než její poslední běh (`firecms_cronTasks.lastRunAt`).
  Propásnutý běh (výpadek cronu) se tak dožene jednou, ne tolikrát, kolikrát byl propásnut.
- Úloha, která ještě nikdy neběžela (nová nebo přejmenovaná), se spustí při nejbližším volání, i denní úloha.
- `lastRunAt` se zapisuje **před** spuštěním úlohy. Úloha, která shodí celý proces, se proto nespouští znovu při
  každém volání, ale až v dalším plánovaném čase.
- Výjimka v úloze se zaloguje a ostatní úlohy běží dál.
- Souběh brání zámek `temp/cron.lock` (`flock`). Druhé volání během běhu vrátí 409.

## Soubory

- `app/Cron/CronTask.php`, `CronRunner.php`, `CronTaskResult.php`, `CronSettings.php`
- `app/Model/Database/CronTasks.php` — tabulka `firecms_cronTasks` (`data/migrations/structures/20261001000000.sql`)
- `app/Presenters/CronPresenter.php` — holý `IPresenter` (bez layoutu, session, jazyků)
- `app/Router/CronRouter.php` — routa `cron`, priorita 90 v `router.priorities` (před `FrontRouter`, jinak by
  `cron` zkoušel `CustomRouter` jako hezkou URL)
