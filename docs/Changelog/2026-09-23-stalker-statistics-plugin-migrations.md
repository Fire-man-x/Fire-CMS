# 2026-09-23 — Tabulky Stalker, Statistics a Sliders přesunuty z core migrace do pluginů

**Co:** `firecms_plugin_stalkers` a `firecms_plugin_statistics` už nezakládá core migrace
`data/migrations/structures/20161115000000.sql`, ale vlastní migrace pluginů
`app/Plugins/Stalker/data/migrations/20260918000001.sql` a
`app/Plugins/Statistics/data/migrations/20260918000002.sql`. Ty jsou nově registrované v `config.plugin.neon`
(skupiny `stalker` a `statistics`, `dependencies: [structures]`, vzor `Sliders`/`DynamicForms`).

**Doplněno (Sliders):** Stejně byly přesunuté `firecms_plugin_sliders` a `firecms_plugin_sliderItems`, a to na
začátek už existující registrované migrace `app/Plugins/Sliders/data/migrations/20260918000000.sql` (před INSERT
záznamu modulu, vzor `DynamicForms`). Core migrace teď nezakládá žádnou `firecms_plugin_*` tabulku.

**Proč:** Podle `app/config/config.neon` vlastní každý plugin své migrace. Plugin migrace navíc duplikovaly core
tabulky, nebyly registrované a po registraci by spadly na „Table already exists“.

**Dotčené soubory/oblasti:**
- **Core migrace:** ze `structures/20161115000000.sql` odstraněny oba bloky `DROP`/`CREATE TABLE`.
  `structures/20200324212900.sql` (jen `ALTER` sloupce `session` ve `firecms_plugin_statistics`) smazán a jeho
  výsledek (`session varchar(32)`) je zapracovaný do plugin migrace.
- **Obsah plugin migrací:** převzatý z core definic. Ty odpovídají kódu:
  - `Stalker::traceUrl()` zapisuje `createdBy`, `ip`, `url`, `data`;
  - `Statistics` zapisuje `session`, `ip`, `agent`.
  Původní obsah plugin souborů (Statistics: `session int`, `hits`, index a FK na neexistující `createdBy`;
  Stalker bez `ip`/`data`) kódu neodpovídal.
- `app/Plugins/Stalker|Statistics/config.plugin.neon`: sekce `migrations`.

**Ověření:** Všechny migrace spuštěné v pořadí jako `nextras/migrations` do dočasné DB bez chyby. Obě tabulky
mají stejné sloupce jako dřív, včetně `createDate`/`updateDate` za `id` a FK. DI kontejner se zkompiluje,
administrace i seznam pluginů se načtou.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano, věta o tom, které plugin tabulky jsou v core migraci
- DB migrace potřeba? ne, historické migrace přepsané. Nutný reset DB a smazání `temp/_Nette.Database*`.
