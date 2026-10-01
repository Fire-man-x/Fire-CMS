# Sessions — úložiště (soubory, MySQL, PostgreSQL, Redis)

Výchozí stav: PHP ukládá sessions do souborů v `temp/sessions` (`session: savePath` v `app/config/config.neon`).
Jinak se to nastavuje v `theme.neon` projektu přes rozšíření `sessionHandler:`
(`App\Session\DI\SessionHandlerExtension`, registrované v config.neon jádra). Bez nastavení se nic nemění.

## Nastavení v theme.neon

```neon
# MySQL/MariaDB - připojení ze sekce database: (app/config), bez `connection` hlavní připojení
sessionHandler:
	driver: mysql
	connection: @database.default
```

```neon
# PostgreSQL - připojení je potřeba nejdřív nadefinovat v database: (a na serveru PHP rozšíření pdo_pgsql)
database:
	sessions:
		dsn: 'pgsql:host=127.0.0.1;dbname=sessions'
		user: app
		password: ...
		options:
			lazy: yes

sessionHandler:
	driver: postgresql
	connection: @database.sessions
```

```neon
# Redis - bez PHP rozšíření redis (vlastní klient přes socket)
sessionHandler:
	driver: redis
	redis:
		host: 127.0.0.1        # nebo tls://host, nebo /cesta/k/redis.sock
		port: 6379
		password: null         # username: pro Redis 6+ ACL
		database: 0
		prefix: 'projektPrefix:session:'  # každý projekt na sdíleném Redisu vlastní prefix nebo database
```

Společné volby: `lockTimeout` (výchozí 30 s, jak dlouho čekat na zámek session, kterou drží jiný požadavek), `table`
(výchozí `system_sessions`, jen MySQL/PostgreSQL), `redis: timeout` (2 s) a `redis: lockTtl` (60 s). Platnost
sessions se řídí `session: expiration` (14 dní v config.neon jádra).

`connection` přijme název připojení ze sekce `database:` (`@database.default` → služba `database.default.connection`)
nebo přímo službu `Nette\Database\Connection`. Neexistující připojení skončí chybou už při sestavení kontejneru.
Připojení na jinou databázi, než handler umí (např. MySQL pro `postgresql`), skončí chybou při prvním startu session.

Po změně nastavení smazat `temp/cache/nette.configurator/`, protože v produkčním režimu se změna neonu neprojeví
(viz `docs/AI-Context/gotchas.md`). Přechod na jiné úložiště odhlásí všechny uživatele, sessions se nepřevádějí.

## Tabulky

MySQL: SQL tabulky `system_sessions` je v `app/Session/Session.createTable.sql`. Migrace jádra ji nezakládá,
před zapnutím handleru ji vytvořte ručně (v hlavní DB, nebo v DB z `connection`).

PostgreSQL se nemigruje (nextras/migrations běží nad hlavní MySQL), tabulku je potřeba vytvořit ručně:

```sql
CREATE TABLE system_sessions (
	id char(64) NOT NULL PRIMARY KEY,  -- SHA-256 hash ID session
	data text NOT NULL,                -- data session v base64
	expiresAt bigint NOT NULL          -- platnost do (unix timestamp)
);
CREATE INDEX system_sessions_expiresAt ON system_sessions (expiresAt);
```

Názvy sloupců se píšou bez uvozovek v tabulce i v dotazech, v PostgreSQL jsou tedy malými písmeny (`expiresat`).

## Jak to funguje

- Třídy: `App\Session\DatabaseSessionHandler` (společný základ) → `DatabaseSessionHandler\SqlSessionHandler`
  (`MysqlSessionHandler`, `PostgreSqlSessionHandler`) a `DatabaseSessionHandler\RedisSessionHandler`
  (+ `RedisConnection`, minimální klient protokolu RESP).
- ID session se ukládá jen jako SHA-256 hash. Z výpisu DB nebo ze zálohy tak nejde převzít cizí přihlášení.
- Data jsou v SQL uložená jako base64 (serializovaná session může obsahovat libovolné bajty), v Redisu binárně.
- Platnost je unix timestamp (`expiresAt`) počítaný v PHP ze `session.gc_maxlifetime`. Nezávisí tedy na časové
  zóně DB.
- **Zámek** drží session po celý požadavek (od `read()` do `close()`), stejně jako u souborů. Bez něj by si souběžné
  požadavky (AJAX) přepisovaly data.
  - MySQL: `GET_LOCK()`.
  - PostgreSQL: advisory lock. Nefunguje přes PgBouncer v režimu transaction pooling.
  - Redis: klíč `<prefix>lock:<hash>` s tokenem a vlastní expirací `lockTtl`.
  - U MySQL a PostgreSQL drží zámek připojení, při pádu požadavku se tedy uvolní samo.
  - Když se zámek nepodaří získat do `lockTimeout`, start session skončí výjimkou.
- `updateTimestamp()` (data se nezměnila): SQL zapíše novou platnost, jen pokud je stará o víc než 60 s. Redis
  prodlouží klíči TTL.
- Strict mode (`session.use_strict_mode`, zapíná ho Nette): `validateId()` odmítne neznámé nebo prošlé ID a PHP
  vygeneruje nové.

## Mazání prošlých sessions

MySQL/PostgreSQL: cron úloha `session-gc` (`App\Session\GarbageCollectorTask`, každou hodinu ve :20). Rozšíření ji
registruje jen pro tyto handlery, spouští ji endpoint `/cron` (viz [cron.md](cron.md)). Na Debianu/Ubuntu je
`session.gc_probability = 0`, takže PHP samo `gc()` nevolá a bez cronu by tabulka rostla donekonečna. Redis maže
prošlé klíče sám.

## Rizika a omezení

- **Každá založená session je zápis a zámek.** LiveTranslator 3.0 (verze v `composer.json`) session sám
  nespouští, nepřeložené řetězce ukládá do souboru `*.untranslated`. Session tedy vzniká jen tam, kde ji aplikace
  opravdu potřebuje. Kdo na frontendu session spustí u každého požadavku (komponenta, formulář s `addProtection()`
  v layoutu), založí s DB/Redis handlerem řádek pro každého návštěvníka i robota na celou `expiration` (14 dní).
  Session spuštěná až při vykreslování šablony může skončit chybou „Session cannot be started after headers have
  already been sent“, viz `gotchas.md`.
- Databáze/Redis se stává podmínkou pro celý web. Bez úložiště session nefunguje přihlášení a Nette vyhodí výjimku.
- MySQL handler používá stejné připojení jako aplikace, pokud v `connection` nenastavíte jiné. Zápis session na
  konci požadavku tak proběhne i uvnitř případné neukončené transakce aplikace.
