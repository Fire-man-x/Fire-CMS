# 2026-09-22 — `firecms_options` převedeno na `firecms_settings` + `firecms_settingDescriptions`

**Co:** Dvě nové core migrace nahrazují plochou key/value tabulku `firecms_options` (`key`, `value`)
dvojicí `firecms_settings` (kotva — dnes vždy jeden řádek) + `firecms_settingDescriptions` (jeden
řádek na jazyk, se sloupci `image_resolution`, `main_description`, `main_email`, `main_title`,
`seo_description`, `seo_keywords`, `seo_title`, `themePath`) — stejný vzor jako existující
`firecms_articles`/`firecms_articleDescriptions` a `firecms_tags`/`firecms_tagDescriptions`.

Rozdělené do dvou souborů/skupin záměrně:
- `data/migrations/structures/20260922130000.sql` — jen vytvoří obě nové tabulky a vloží kotevní
  řádek `firecms_settings` (`setting_id = 1`). Nekopíruje data ani neruší `firecms_options`.
- `data/migrations/basic-data/20260922140000.sql` — běží až po `basic-data/20161115000000.sql`
  (pozdější timestamp ve stejné složce/skupině), zkopíruje hodnoty z `firecms_options` do
  `firecms_settingDescriptions` pro každý řádek v `firecms_languages` (přes `INSERT ... ON DUPLICATE
  KEY UPDATE`, viz níže proč) a teprve pak `firecms_options` zahodí.

Důvod rozdělení: skupina `structures` běží PŘED `basic-data` (viz `app/config/config.neon`), takže
na zbrusu nové instalaci by v okamžiku, kdy běží `structures`, byly `firecms_languages` i
`firecms_options` ještě prázdné — kopírovací krok by tam neměl co kopírovat. A `basic-data/
20161115000000.sql` (starý, už jinde spuštěný seed soubor, který se nesmí editovat — viz
"Rozhodnutí a kompromisy") pořád nepodmíněně vkládá řádky do `firecms_options`, takže ta tabulka
musí přežít až do doby, kdy tenhle starý soubor doběhne.

Spolu s migrací přepsán i PHP kód:
- `App\Model\Options` → `App\Model\Settings` (`app/Model/Settings.php`, starý soubor smazán). Nové
  metody `getAllForLanguage(string $language): array` (vrací stejný tvar asociativního pole jako dřív
  `findAll()->fetchPairs('key','value')`) a `getByKey(string $key, ?string $language = null): string`
  (bez jazyka spadne na výchozí jazyk webu), plus `getMainSettingId()`/`saveForLanguage()` pro zápis.
- `App\Forms\SettingFormFactory` přepsán na `$form->addContainer($languageId)` po vzoru
  `TagFormFactory` — jeden kontejner na jazyk místo jediného globálního formuláře.
- Volající místa upravena beze změny chování: `App\FrontModule\Presenters\BasePresenter` (`$options`
  se teď plní přes `getAllForLanguage($this->language)`, `getWwwThemePath()` volá `getByKey('themePath',
  $this->language)`), `App\Model\MultiFileUploadModel`, `App\Plugins\DynamicForms\Forms\ContactFormFactory`.
- `app/config/config.neon` — registrace služby `App\Model\Options` → `App\Model\Settings`.

**Proč:** Na žádost uživatele — nastavení webu (SEO texty, titulek, popis) by měla jít editovat
per-jazyk stejně jako obsah článků/kategorií/štítků, ne jako jedna globální hodnota pro celý web.

**Dotčené soubory/oblasti:**
- `data/migrations/structures/20260922130000.sql` — nová migrace (jen DDL + kotevní řádek).
- `data/migrations/basic-data/20260922140000.sql` — nová migrace (přenos dat + drop staré tabulky).
- `app/Model/Settings.php` (nový), `app/Model/Options.php` (smazán).
- `app/Forms/SettingFormFactory.php`, `app/config/config.neon`.
- `app/FrontModule/Presenters/BasePresenter.php`, `app/Model/MultiFileUploadModel.php`,
  `app/Plugins/DynamicForms/Forms/ContactFormFactory.php`.
- **Beze změny:** žádná `.latte` šablona — `$options` template proměnná si zachovala přesně stejný
  tvar (asociativní pole `main_title`/`themePath`/...), takže `{$options['main_title']}` v
  `app/FrontModule/templates/@layout.latte`, `theme/FrontModule/templates/@layout.latte` a
  front-end layoutech pluginů SDHCalendar/SDHEvents/SDHTests/SDHGallery funguje beze změny.
- **Nedotčeno:** administrace (`app/AdminModule/templates/@layout.latte`) `$options` vůbec nečte,
  žádný zásah nebyl potřeba.

**Rozhodnutí a kompromisy:**
- `image_resolution`, `main_email` a `themePath` teď technicky mají hodnotu per jazyk, i když
  jde o technická/globální nastavení, ne o překládaný text — zvoleno kvůli jednotnosti (jeden
  formulář, jedna tabulka, žádné speciální větvení pro "tahle 3 pole jsou jinak"). Migrace všem
  jazykům nastaví stejnou počáteční hodnotu, takže reálně to nic nemění, dokud editor záměrně
  nezadá jinou hodnotu pro konkrétní jazyk.
- `data/migrations/basic-data/20161115000000.sql` (starý seed soubor) jsem neupravoval, i když teď
  vkládá do tabulky, která se hned po něm zase zahazuje — podle `docs/AI-Context/gotchas.md` se
  jednou spuštěný migrační soubor nesmí editovat (nextras/migrations si ho checksumuje), takže
  úklid musel jít do nového souboru s pozdějším timestampem místo úpravy stávajícího.
- Obě nové migrace jsem otestoval opakovaně proti izolovaným zahazovacím databázím (ne proti živé
  `fire-cms` dev DB) — jednou pro scénář "zbrusu nová instalace" (prázdné `firecms_languages`/
  `firecms_options` v okamžiku běhu `structures`) a jednou pro scénář "už dřív naseedované
  prostředí" (tak jak vypadá naše sdílená dev DB) — v obou proběhlo bez chyby a se správnými daty.
  `DROP TABLE`/`CREATE TABLE` v MySQL dělá implicitní commit, takže "spustit v transakci a
  rollbacknout" na živých datech by nebylo bezpečné; migrace tedy ještě nebyly spuštěné přes
  `bin/console migrations:continue` proti reálné dev databázi.

**Návaznost:**
- Update `docs/Architecture/*.md`? Ne (žádný soubor v `docs/` `firecms_options`/`Model\Options` přímo
  nezmiňoval).
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? Ne.
- DB migrace potřeba? Ano — `data/migrations/structures/20260922130000.sql` +
  `data/migrations/basic-data/20260922140000.sql` (viz výše, ještě nespuštěné proti dev DB).
