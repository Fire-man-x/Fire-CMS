# 2026-09-18 — Založena testovací infrastruktura (Nette Tester)

**Co:** Přidán `tests/` adresář s `bootstrap.php`, pomocníkem `tests/Helpers/SqliteDatabase.php` pro
integrační testy nad in-memory SQLite Explorerem a pilotním testem
`tests/Modules/UrlModule/UrlManagerValidateUrl.phpt`. Nový composer skript `composer test` spouští
`vendor/bin/tester` nad `tests/`. Doplněn `autoload-dev.psr-4` (`Tests\\` → `tests`) a `.gitignore` záznam
pro `tests/temp`.

**Proč:** Projekt měl `nette/tester` jen jako nevyužitou dev-závislost, žádné vlastní testy nad `app/`
neexistovaly (viz `docs/Architecture/overview.md`). Uživatel chtěl ověřit, jestli a jak lze Nette Tester
reálně nasadit nad kódem v `app/`.

**Dotčené soubory/oblasti:**
- `tests/bootstrap.php` — standardní Tester bootstrap (vzor podle `app/Components/VisualPaginator-3.0/tests/bootstrap.php`).
- `tests/Helpers/SqliteDatabase.php` — ruční sestavení `Nette\Database\Explorer` proti `sqlite::memory:`, bez DI kontejneru a bez `config.local.neon`.
- `tests/Modules/UrlModule/UrlManagerValidateUrl.phpt` — pilotní integrační test nad `App\Modules\UrlModule\UrlManager::validateUrl()`.
- `composer.json` — `autoload-dev.psr-4` (`Tests\\`), skript `test` + popis.
- `.gitignore` — `tests/temp`.
- `docs/Architecture/overview.md`, `docs/AI-Context/quick-reference.md` — opraveno tvrzení "žádné testy".
- `docs/AI-Context/gotchas.md` — nová sekce "Testování přes Nette Tester" (proč SQLite, past `BaseModel::insert()` + `LAST_INSERT_ID()`, autoload-dev).

**Rozhodnutí a kompromisy:**
- Zvažoval jsem mockování `Nette\Database\Explorer`, ale je to konkrétní třída (ne interface) — zbytečně
  křehké. In-memory SQLite je rychlejší i jednodušší na údržbu než reálná testovací MySQL instance a
  nevyžaduje žádnou infrastrukturu navíc.
- `app/Model/BaseModel` je přímo svázaný s `Nette\Database\Explorer`, takže skutečně izolovaných unit testů
  (bez DB) je v jádru málo — tohle je proto integrační test, ne unit test v užším slova smyslu.
- `UrlManager` byl zvolen jako pilot, protože `validateUrl()` obsahuje čistou byznys logiku (generování
  unikátní webalized URL) testovatelnou jen SELECT dotazy — nepotřebuje `BaseModel::insert()`, který na
  SQLite nefunguje.
- Pokrytí je záměrně malé (jedna metoda) — cílem bylo ověřit proveditelnost a založit vzor/infrastrukturu,
  ne pokrýt `app/` testy plošně.

**Návaznost:**
- Update `docs/Architecture/*.md`? Ano — `overview.md`.
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? Ano — `gotchas.md`.
- DB migrace potřeba? Ne.
