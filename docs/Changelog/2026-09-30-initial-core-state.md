# 2026-09-30 — Výchozí stav jádra (nová historie repozitáře)

**Co:** Changelog začíná znovu tímto záznamem. Dokumentace popisuje jen jádro a pluginy v `app/Plugins/`, klientské balíčky
(`theme/Plugins/`) sem nepatří.

**Proč:** Nový výchozí commit. Záznamy o přechodech mezi verzemi (přejmenování
tabulek, přepsané migrace, ruční kroky v existujících DB) popisovaly stav, který v nové historii neexistuje.

**Co jádro v tomto stavu obsahuje** (podrobnosti v odkazovaných dokumentech):
- Obsah: články, stránky (`Pages`, vnořování, obrázky), sekce (`Sections`, kategorie i články patří do sekce),
  kategorie článků bez typů, štítky, SEO meta, komentáře, hezká URL a přesměrování — `Architecture/orm.md`.
- Menu jako samostatné položky (`linkType` + `target`, obrázky, přeložitelný nadpis) — `Architecture/components.md`.
- Dynamické formuláře a slidery jako součást jádra (dřív pluginy; názvy komponent `contactFormControl` a
  `pluginSlider` zůstaly kvůli šablonám projektů) — `Modules/front.md`.
- Šablony stránek (`firecms_pages.template`, první je „Kontakt“ s mapou Mapy.com), frontend na Bootstrap 5.3,
  úvodní stránka s nejnovějšími články a sekcemi — `Modules/front.md`.
- Vícejazyčnost: LiveTranslator 3.0, překlady jádra, tématu a pluginů (`ChainTranslatorStorage`), jazyky na
  vlastních doménách — `Architecture/plugins.md`, `Architecture/routing.md`.
- Nastavení webu po jazycích (`firecms_settings` + `firecms_settingDescriptions`), včetně kontaktních údajů
  (telefon, adresa, GPS bod mapy) — `Architecture/orm.md`.
- Správce souborů na Flysystemu (lokální disk nebo S3, povolené náhledy, EXIF) — `Architecture/file-storage.md`.
- Úložiště sessions (soubory, MySQL, PostgreSQL, Redis) — `Architecture/sessions.md`.
- Cron endpoint `/cron` — `Architecture/cron.md`.
- Přepis presenterů jádra z `theme/` — `Architecture/presenters.md`.
- Správa pluginů v administraci, migrace pluginů ve vlastním stromu — `Architecture/plugins.md`.
- Testy Nette Tester (`composer test`) — `AI-Context/gotchas.md`, sekce „Testování přes Nette Tester“.

**Konvence DB schématu** (platí pro všechny další migrace):
- tabulky jádra `firecms_<camelCase>`, tabulky pluginů z `app/Plugins/` `firecms_plugin_<camelCase>`;
- sloupce v camelCase, `AUTO_INCREMENT` PK `id`, cizí klíče `<entita>Id`;
- každá tabulka má `createDate` a `updateDate` hned za `id` a sloupci cizích klíčů.

**Migrace jádra** jsou sloučené do jednoho souboru na skupinu (stav k 2026-10-01):
- `data/migrations/structures/20261001000000.sql` — celé schéma jádra;
- `data/migrations/basic-data/20261001000000.sql` — jazyky, role a ACL moduly, administrátor, složky souborů,
  Nastavení (jen název webu a e-mail `info@example.com`, kontaktní údaje prázdné);
- `data/migrations/dummy-data/20261001010000.sql` — ukázkový obsah (jen s `dummyData`): sekce, kategorie, články
  se štítky a komentáři, stránky včetně kontaktní, menu pro `top-menu`/`left-menu`/`text-box`, slider `main_menu`,
  popis webu, SEO a kontaktní údaje v Nastavení (Brno, telefon, GPS).
Stejné soubory pro PostgreSQL jsou v `data/migrations-pgsql/` (volí se parametrem `migrations` v
`config.local.neon`, viz `Architecture/configuration.md`). Další změny schématu už jen jako nové soubory s pozdějším časovým razítkem,
vždy v obou adresářích (viz `AI-Context/gotchas.md`).

**Projekty založené na starší verzi jádra:**
Nová historie nemá s dřívějšími commity nic společného. Merge jádra do projektu forknutého dřív skončí na
„unrelated histories“, projekt je potřeba na nový výchozí commit jednou převést ručně.

Migrace jádra byly před tímto stavem několikrát přepsané přímo, ne doplněné o nové. Databáze založená starší
verzí proto neodpovídá migracím a `migrations:continue` nahlásí změněné kontrolní součty. Nejjednodušší je
`migrations:reset` a nový import dat. Při ručním převodu počítejte hlavně s těmito rozdíly proti starším verzím:
- prefix tabulek `firecms_`/`firecms_plugin_`, camelCase sloupce, PK `id`, `createDate`/`updateDate`;
- `firecms_options` nahradily `firecms_settings` + `firecms_settingDescriptions`;
- položky menu nejsou vazba na kategorie, `firecms_menus.name` nahradil nadpis ve `firecms_menuDescriptions`;
- kategorie nemají sloupec `type`: úvodní stránka je `Front:Homepage`, galerie a obsahové stránky jsou `Pages`;
- kategorie i články potřebují `sectionId`; sekce zakládají jen ukázková data, jinak ji založte v administraci;
- dynamické formuláře a slidery jsou v jádru: tabulky `firecms_plugin_dynamicForm*`/`firecms_plugin_slider*`
  se jmenují `firecms_dynamicForm*`, `firecms_sliders`, `firecms_sliderItems`, skupiny migrací `dynamicForms` a
  `sliders` neexistují a include `app/Plugins/DynamicForms|Sliders/config.plugin.neon` je potřeba z
  `theme/config/plugins.neon` odebrat (soubory už nejsou, kontejner by se nesestavil);
- umístění menu ve výchozím layoutu jsou `top-menu`, `left-menu`, `text-box`;
- zrušený sloupec `gridName` (název je jen v `*Descriptions`);
- soubory na disku v nové struktuře `<h0>/<h1>/<hash>.<ext>`, převod `bin/console files:migrate`.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — všechny dokumenty sjednocené na aktuální stav, bez odkazů na smazané záznamy.
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — bez klientských pluginů a historických poznámek.
- DB migrace potřeba? ano — sloučené migrace jádra vyžadují `migrations:reset` (viz výše).
