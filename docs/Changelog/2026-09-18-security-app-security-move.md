# 2026-09-18 — `App\Security\*` přesunuto z `app/Components/Security/` do `app/Security/`

**Co:** Uživatel přesunul `Acl.php`, `AuthorizatorFactory.php`, `FacebookLogin.php`,
`IUserAccessibleEntity.php`, `Resource.php`, `Role.php`, `User.php` z `app/Components/Security/` do
`app/Security/` — fyzická cesta teď konečně odpovídá jejich namespace `App\Security\*` a Composer PSR-4
mapě (`App\ -> app`). V návaznosti jsem upravil testy a dokumentaci, které na starou cestu odkazovaly.

**Proč:** Šlo o dlouhodobě zdokumentovanou historickou anomálii (viz `docs/AI-Context/gotchas.md`
"RobotLoader vs. PSR-4") — v produkci neškodila (RobotLoader indexuje nezávisle na cestě), ale komplikovala
cokoliv, co stojí na Composer autoloaderu bez RobotLoaderu (typicky testy a PHPStan). Konkrétním spouštěčem
bylo dopolední zavedení `tests/Security/RoleTest.phpt` (viz
`docs/Changelog/2026-09-18-nette-tester-more-tests.md`), které si muselo vypomoct přidáním
`app/Components/Security` do `composer.json` → `autoload.classmap`, aby Composer třídu vůbec našel.

**Dotčené soubory/oblasti:**
- `app/Components/Security/*` → `app/Security/*` (přesun, beze změny obsahu souborů).
- `composer.json` — `autoload.classmap` zase prázdný, `classmap` fix z dopoledne už není potřeba.
- `tests/Security/RoleTest.phpt` — upraven docblock (už neodkazuje na starou cestu/classmap).
- `CLAUDE.md` — dvě místa (`Bootstrap a vrstvení konfigurace`, `Autorizace (ACL)`) aktualizována na
  `app/Security/`.
- `docs/Architecture/overview.md`, `docs/Architecture/components.md`, `docs/AI-Context/quick-reference.md`,
  `docs/AI-Context/gotchas.md` — stejná oprava, staré umístění zmíněno jen jako historický kontext.

**Rozhodnutí a kompromisy:**
- Staré changelogové záznamy, které starou cestu zmiňují v kontextu toho, co bylo pravda v danou chvíli
  (`2026-09-18-nette-tester-more-tests.md`, `2026-09-16-dynamicforms-eager-acl-cli-fix.md`), jsem
  neupravoval retroaktivně — jsou to historické záznamy o stavu v době vzniku, ne živá dokumentace. U
  `2026-09-18-nette-tester-more-tests.md` jde navíc přímo o záznam classmap workaroundu, který tenhle
  přesun nahrazuje — ponechán beze změny jako historie rozhodnutí, aktuální stav popisuje tenhle soubor.

**Návaznost:**
- Update `docs/Architecture/*.md`? Ano — `overview.md`, `components.md`.
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? Ano — `gotchas.md`.
- DB migrace potřeba? Ne.
