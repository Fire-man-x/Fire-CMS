# 2026-10-15 — E-mail a telefon webu v globálním nastavení i po jazycích

**Co:** E-mail (`main_email`) i telefon (`contact_phone`) jsou v `firecms_settings` (globální) i ve
`firecms_settingDescriptions` (po jazycích). Prázdná hodnota jazyka = použije se globální.

**Proč:** Telefon byl jen globální, e-mail jen po jazycích. Patička webu má ukazovat oba údaje a jazyk má mít
možnost je přepsat, aniž by se musely vyplňovat v každém jazyce.

**Dotčené soubory/oblasti:**
- `data/migrations/structures/20261015120000.sql` + `data/migrations-pgsql/structures/20261015120000.sql` — sloupce
  `firecms_settings.mainEmail` (naplní se e-mailem výchozího jazyka) a `firecms_settingDescriptions.contactPhone` (prázdný)
- `app/Model/Database/Settings.php` — `main_email` v `GLOBAL_COLUMN_MAP`, `contact_phone` v `DESCRIPTION_COLUMN_MAP`;
  `getAllForLanguage()` doplní prázdnou hodnotu jazyka globální; nové `getGlobalValues()` a `getLanguageValues()`
  (každá tabulka zvlášť); `getByKey()` u klíčů v obou mapách vrací hodnotu s doplněním; `saveForLanguage()` klíče
  z obou map ukládá jen k jazyku
- `app/AdminModule/Forms/SettingFormFactory.php` — globální pole E-mail, pole Telefon u jazyka (s popisem „Pokud je prázdné,
  použije se hodnota z globálního nastavení.“), výchozí hodnoty přes `getGlobalValues()`/`getLanguageValues()`
- `data/localization/cs.admin` — překlad popisu pole
- `tests/Model/SettingsTest.phpt` — doplnění z globální hodnoty, jazyk bez řádku, ukládání

**Rozhodnutí a kompromisy:**
Stejný veřejný klíč v obou mapách místo nových klíčů (`main_email_global` apod.): šablony (`$options['main_email']`)
a odesílání e-mailů (`getByKey('main_email', $language)`) dostanou doplněnou hodnotu bez úprav. Formulář dřív plnil
jazyky přes `getAllForLanguage()`, teď by tím do každého jazyka uložil globální hodnotu a pozdější změna globální by
se neprojevila, proto `getLanguageValues()`. E-maily jazyků migrace nechává, globální e-mail je jen kopie výchozího
jazyka.

**Ověřeno:** `tests/Model/SettingsTest.phpt`, PHPStan level 9 nad `Settings.php` a `SettingFormFactory.php`, PostgreSQL
migrace v transakci s rollbackem proti lokální DB. MariaDB migrace a formulář v prohlížeči neověřené.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano, `orm.md` (Settings)
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ano, `structures/20261015120000.sql` (MariaDB i PostgreSQL)
