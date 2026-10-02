**[Zpět](../Readme.md)**

# GitHub automatické nasazení [deploy]

Nasazení má každý klientský projekt ve vlastním `.github/workflows/deploy.yml`. Jádro Fire CMS dodává jen vzor
`.github/workflows/deploy.yml.dist`. GitHub soubory s příponou `.dist` nespouští, takže v jádru nic nenasazuje.
Vlastní `deploy.yml` do jádra nepatří, merge jádra by jinak přepisoval nasazení projektů.

## Co nasazení dělá
1. Spustí testy a PHPStan z `ci.yml` jádra. Když neprojdou, nenasazuje se.
2. Na GitHubu nainstaluje závislosti (`composer install --no-dev`). Server nepotřebuje Composer ani přístup
   k repozitáři.
3. Ověří, že cílový adresář na serveru obsahuje `theme/config/config.local.neon`. Je to ochrana proti chybně
   nastavené cestě, `rsync --delete` by jinak smazal cizí data.
4. Nahraje soubory z repozitáře včetně `vendor/` přes `rsync`. Soubory smazané z repozitáře smaže i na serveru.
5. Smaže `temp/cache/`, spustí `bin/console migrations:continue` (nebo `migrations:reset`, viz níže) a
   `temp/cache/` smaže znovu. Produkční režim změny konfigurace ani šablon nehlídá, viz
   `docs/AI-Context/gotchas.md`.

Na serveru zůstává (nepřenáší se ani nemaže):

| Cesta | Co to je |
|---|---|
| `theme/config/config.local.neon` | připojení k DB, SMTP, S3, … — zakládá se ručně na serveru |
| `www/files/` | nahrané soubory a náhledy obrázků (kromě `.htaccess`) |
| `log/`, `temp/` | logy, sessions, cache (`temp/cache/` se při nasazení maže) |
| `data/localization/*.untranslated` | nepřeložené řetězce zapsané za běhu |

Na server se nenahrává: `.git`, `.github`, `docker`, `doc`, `docs`, `tests`, `theme/docs`, `CLAUDE.md`.

**Pozor:**
- `theme/config/plugins.neon` se nasazuje z gitu. Zapnutí pluginu je tedy commit. Přepnutí pluginu v
  administraci na serveru přepíše další nasazení, migrace nebo `deactivate` skript pluginu ale v DB už proběhly.
- Adresáře, které vznikají jen na serveru (exporty, vlastní úložiště mimo `www/files/`), doplňte v
  `deploy.yml` mezi `--exclude`, jinak je `--delete` smaže.

## Zapnutí v projektu
1. Zkopírujte vzor a v `on: push: branches:` nastavte větev, ze které se nasazuje:
	```shell
	cp .github/workflows/deploy.yml.dist .github/workflows/deploy.yml
	```
2. V repozitáři projektu založte prostředí `Settings -> Environments -> New environment` s názvem `production`
   (musí sedět s `environment:` v `deploy.yml`). U produkce doporučujeme `Required reviewers` a
   `Deployment branches` omezené na nasazovací větev.
3. V prostředí nastavte:

	| Název | Typ | Hodnota |
	|---|---|---|
	| `DEPLOY_KEY` | Secret | privátní SSH klíč (celý obsah souboru), viz Konfigurace SSH |
	| `DEPLOY_HOST` | Variable | SSH uživatel a server, `demo.cz@server.cz` |
	| `DEPLOY_PATH` | Variable | kořen projektu, relativně k domovskému adresáři `www/demo.cz` nebo absolutně `/var/www/demo.cz/data/www/demo.cz` |
	| `DEPLOY_KNOWN_HOSTS` | Variable | klíč serveru z `ssh-keyscan`, viz Konfigurace SSH |
	| `DEPLOY_PORT` | Variable | nepovinné, výchozí `22` |
	| `DEPLOY_PHP` | Variable | nepovinné, cesta k PHP 8.3, pokud je v SSH jiná verze (viz níže) |
	| `DEPLOY_RUN_AS` | Variable | nepovinné, uživatel, pod kterým běží PHP webu, pokud se liší od SSH uživatele (viz níže) |

4. Na serveru před prvním nasazením založte adresář `DEPLOY_PATH` a v něm `theme/config/config.local.neon`
   podle `theme/config/config.local.neon.dist`. Kořen webu (document root) směřujte na `www/`. Na serveru musí
   být `bash`, `rsync` a PHP 8.3.
5. Commitněte `deploy.yml` a pushněte do nasazovací větve. Ručně jde nasazení spustit v
   `Actions -> Deploy -> Run workflow`.

Převod z GitLabu: `COMPOSER_TOKEN` není potřeba (Composer použije `GITHUB_TOKEN` workflow), `DEPLOY_RESET`
nahradila volba `reset` při ručním spuštění.

## Reset databáze
`Actions -> Deploy -> Run workflow` se zaškrtnutou volbou `reset` spustí místo `migrations:continue` příkaz
`migrations:reset`. Ten **smaže všechny tabulky** a založí databázi znovu z migrací (s parametrem `dummyData`
včetně ukázkových dat). Je určený jen pro demo a testovací weby. Produkci chraňte schvalováním nasazení
(`Required reviewers`).

## Konfigurace SSH
1. Vytvořte dvojici SSH klíčů bez hesla
	```shell
	ssh-keygen -t ed25519 -C "demo.cz deploy" -N "" -f deploy_key
	```
2. Zkopírujte veřejný klíč na server
	```shell
	ssh-copy-id -i deploy_key.pub demo.cz@server.cz

	# případně s portem
	ssh-copy-id -i deploy_key.pub -p 22 demo.cz@server.cz
	```
3. Otestujte připojení
	```shell
	ssh -o "IdentitiesOnly=yes" -o "PreferredAuthentications=publickey" -i deploy_key demo.cz@server.cz

	# případně s portem
	ssh -p 22 -o "IdentitiesOnly=yes" -o "PreferredAuthentications=publickey" -i deploy_key demo.cz@server.cz
	```
4. Získejte klíč serveru do `DEPLOY_KNOWN_HOSTS`. Otisk porovnejte s údajem od hostingu nebo se serverem
   (`ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub`), workflow se pak k podvrženému serveru nepřipojí.
	```shell
	ssh-keyscan server.cz            # případně: ssh-keyscan -p 2222 server.cz
	ssh-keyscan server.cz | ssh-keygen -lf -
	```
5. Obsah `deploy_key` vložte do secretu `DEPLOY_KEY` a lokální kopii klíče smažte.

## PHP v prostředí SSH
Pokud se v SSH spouští jiná verze PHP, než aplikace vyžaduje:
1. Zjistěte cesty k dostupným verzím PHP
	```shell
	whereis php
	# php: /usr/bin/php /usr/lib64/php /usr/share/php /opt/php83/bin/php /usr/share/man/man1/php.1.gz
	```
2. Nastavte proměnnou prostředí `DEPLOY_PHP` na cestu k PHP 8.3, např. `/opt/php83/bin/php`.

## Uživatel webového serveru
Na běžném hostingu běží PHP webu pod stejným uživatelem jako SSH, `DEPLOY_RUN_AS` pak nechte prázdné. Na
vlastním serveru, kde web běží pod `www-data` a SSH pod jiným uživatelem, musí cache v `temp/` patřit
`www-data`. Jinak ji web nemůže přepsat a deploy uživatel ji nemůže smazat.
1. Nastavte `DEPLOY_RUN_AS=www-data`. Mazání cache a `bin/console` se pak spouští přes `sudo -n -u www-data`.
2. Povolte to deploy uživateli bez hesla (`visudo -f /etc/sudoers.d/deploy`):
	```
	deploy ALL=(www-data) NOPASSWD: /usr/bin/php, /usr/bin/find
	```
   Deploy uživatel tím smí spouštět PHP pod `www-data`. Nasazuje ale kód webu, takže tím nic dalšího nezíská.
3. `www-data` musí nahrané soubory číst a do `temp/`, `log/` a `www/files/` zapisovat.

## Omezení
- Nasazení není atomické. Změněné soubory se vymění najednou až na konci přenosu, migrace a smazání cache ale
  proběhnou až potom. Po několik sekund tak může web běžet s novým kódem nad starou databází. U změn, které to
  nesnesou, zapněte na dobu nasazení údržbu (`www/.maintenance.php`, viz `www/index.php`).
- Server s `opcache.validate_timestamps=0` nové soubory bez restartu PHP-FPM nepozná. Doplňte restart do
  posledního kroku `deploy.yml` podle hostingu.
- Když se nasazuje z `master`, `ci.yml` po pushi poběží dvakrát: samostatně a jako součást nasazení.
- Další prostředí (např. staging) = kopie `deploy.yml` s jinou větví, jiným `environment:` a jinou skupinou
  `concurrency:`.
- Úpravy vzoru `deploy.yml.dist` v jádru se do `deploy.yml` projektů samy nepropíšou. Po merge jádra je
  porovnejte ručně (`git diff <starý commit jádra> fire-cms/master -- .github/workflows/deploy.yml.dist`).
