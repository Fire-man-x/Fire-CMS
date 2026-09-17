# 2026-09-17 — Prefix `firecms_`/`firecms_plugin_` (camelCase) u DB tabulek jádra a pluginů

**Co:** Všechny tabulky Fire CMS jádra dostaly prefix `firecms_` (`articles` → `firecms_articles`,
`menu_items` → `firecms_menuItems`, ...) a všechny tabulky pluginů (`app/Plugins/*` i `theme/Plugins/*`)
prefix `firecms_plugin_` (`sliders` → `firecms_plugin_sliders`, `dynamic_form_descriptions` →
`firecms_plugin_dynamicFormDescriptions`, ...) — samotný prefix zůstává s podtržítkem, název ZA prefixem je
camelCase (žádná další podtržítka). Upraveny historické CREATE TABLE migrace (core `data/migrations/`,
`app/Plugins/DynamicForms/data/migrations/`, `theme/Plugins/PetHotel/data/migrations/` +
`deactivate.sql`) i odpovídající `setTableName()`/tabulkové konstanty ve všech `App\Model\*` třídách,
`app/Modules/UrlModule`, `app/Modules/CommentsModule`, `app/Components/Menu/Model/Menus`, bundlovaných
pluginech (`Sliders`, `Statistics`, `Stalker`, `DynamicForms`, včetně jejich `Instalation.php` raw SQL
instalátorů) a `theme/Plugins/PetHotel`. Tři nové SDH pluginy (`SDHAttendance`/`SDHCalendar`/`SDHTowns`)
měly ve svých migracích jen `INSERT INTO modules` (core ACL tabulka) — přejmenováno na `firecms_modules`,
jejich vlastní doménové tabulky beze změny (viz níže).

**Proč:** Zadání od zadavatele — sjednotit název tabulek napříč jádrem a klientskými forky a jasně oddělit
core vs. plugin vlastnictví tabulky už podle jejího názvu.

**Dotčené soubory/oblasti:**
- `data/migrations/structures/*.sql`, `data/migrations/basic-data/20161115000000.sql` — všech 33 core i
  bundlovaných-plugin tabulek přejmenováno přímo v historických CREATE TABLE (bezpečné, migrace zatím
  nikde neběžely — viz "Rozhodnutí" níže).
- `app/Plugins/DynamicForms/data/migrations/20171115000000.sql` — `dynamic_forms` → `firecms_plugin_dynamicForms`,
  `dynamic_form_descriptions` → `firecms_plugin_dynamicFormDescriptions`,
  `dynamic_form_sended_values` → `firecms_plugin_dynamicFormSendedValues`.
- `theme/Plugins/PetHotel/data/migrations/20260916140000.sql` + `data/deactivate.sql` — všech 6 tabulek →
  `firecms_plugin_*`, FK reference na core tabulky (`users`, `files`, `roles`, `modules`) → `firecms_*`.
- `theme/Plugins/{SDHAttendance,SDHCalendar,SDHTowns}/data/{migrations/20260916140000.sql,deactivate.sql}`
  — pouze `modules` → `firecms_modules` (jejich jediná core-tabulková reference).
- `app/Model/*.php`, `app/Modules/UrlModule/*.php`, `app/Modules/CommentsModule/Model/Comments.php`,
  `app/Components/Menu/Model/Menus.php` — `setTableName()` volání + tabulkové konstanty
  (`Articles::TRANSLATION_TABLE_NAME`, `Categories::RELATION_*_TABLE_NAME`, `Tags::TRANSLATION_TABLE_NAME`,
  `Roles::TABLE_NAME_ROLE_MODULE`, `Menus::MENU_ITEM_TABLE_NAME`, `Users::TABLE_NAME`).
- `app/Service/Tag.php`, `app/Service/Meta.php` — literální `` `tags` ``/`"metas"` v raw SQL selectech.
- `app/Plugins/{Sliders,Statistics,Stalker,DynamicForms}/Model/*.php`,
  `app/Plugins/{Statistics,Stalker}/Instalation.php`, `theme/Plugins/PetHotel/Model/*.php` —
  `setTableName()`/tabulkové konstanty.
- `docs/AI-Context/gotchas.md`, `docs/Architecture/presenters.md` — literální zmínky `roles`/`modules` v
  textu opraveny na `firecms_roles`/`firecms_modules`; nová sekce gotchas.md vysvětluje prefix konvenci a
  vynechání SDH.

**Rozhodnutí a kompromisy:**
- Historické CREATE TABLE migrace šlo přepsat přímo (ne přidat `RENAME TABLE` navíc), protože byly
  potvrzené jako nikde nenasazené — `nextras/migrations` hlídá checksum souboru přes tabulku `migrations`
  a při změně obsahu už spuštěné migrace tvrdě spadne. Pro budoucí přejmenování AŽ PO nasazení by tohle
  přestalo platit.
- **SDH pluginy (`SDHAttendance`/`SDHCalendar`/`SDHTowns`/`SDHEvents`/`SDHTests`) jsou vědomě VYNECHANÉ** —
  jejich ~50 tabulek žije v samostatné legacy DB `sdh` (`@database.databaseSdh.context`), bez jediné
  migrace v tomto repu, se stovkami raw SQL JOIN/UPDATE/INSERT výskytů napříč pluginy a bez testů. Zadavatel
  rozhodl odložit jako samostatný navazující úkol s plánem na ověření před nasazením na živý klubový web.
  `SDHGallery` nemá žádnou DB vazbu, netýká se ho to vůbec.
- Interní bookkeeping tabulka `migrations` (knihovna `nextras/migrations`) zůstává BEZ prefixu — je to
  infrastruktura knihovny, ne obsahová data aplikace.

**Návaznost:**
- Update `docs/Architecture/*.md`? Ano — `presenters.md` (literální název tabulky).
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? Ano — nová sekce "DB tabulky mají prefix
  `firecms_`/`firecms_plugin_` a název za prefixem je camelCase" + oprava dvou starších zmínek
  `roles`/`modules`.
- DB migrace potřeba? Ano, viz výše — ale POZOR: jakákoliv existující lokální/dev databáze založená před
  touto změnou (`config.local.neon` → `database.default`, DB `fire-cms`) má tabulky ještě pod starými
  názvy a `nextras/migrations` ji nepřejmenuje zpětně (migrace se přepsaly, ne doplnily o `RENAME TABLE`).
  Než s tímhle checkoutem znovu pracovat, je potřeba lokální DB buď smazat a založit znovu přes
  `bin/console migrations:reset`, nebo tabulky ručně přejmenovat (`RENAME TABLE`) podle mapování v tomto
  commitu.
