# 2026-09-23 — Výchozí jazyk je vždy právě jeden a aktivní (`Languages::setDefault()`)

**Co:** Uložení formuláře jazyka se zaškrtnutým „Default“ zruší výchozí u ostatních jazyků. Výchozí jazyk
nejde ve formuláři odškrtnout (jen nastavením jiného jako výchozího), při nastavení jako výchozí se aktivuje
a nejde deaktivovat (grid ani formulář).

**Proč:** Nahlášená chyba: po vytvoření jazyka `cs` jako výchozího byly výchozí dva jazyky (`cs` i `en`).
`LanguageFormFactory` ukládal `default` přímo, jen tlačítko v gridu (`handleSetDefault`) rušilo ostatní.

**Dotčené soubory/oblasti:**
- `app/Model/Languages.php` — `setDefault($languageId)`: v transakci zruší `default` u ostatních, nastaví
  `default` + `active` vybranému.
- `app/Forms/LanguageFormFactory.php` — `default` se neukládá přímo, po uložení volá `setDefault()`.
  Validace: výchozí jazyk nejde odškrtnout.
- `app/AdminModule/SettingsModule/Presenters/LanguagesPresenter.php`:
  - `handleSetDefault()` volá `setDefault()`;
  - `handleActivate()` odmítne deaktivovat výchozí jazyk (flash „Výchozí jazyk nelze deaktivovat.“);
  - sloupec „A.“ má `setRenderCondition()`: u výchozího jazyka se místo přepínače vykreslí jen text stavu.
- `app/Forms/LanguageFormFactory.php` — validace: u výchozího jazyka nejde odškrtnout „Active“.
- `data/localization/cs.admin` — překlady nových chybových hlášek.
- `tests/Model/LanguagesSetDefaultTest.phpt`.

**Rozhodnutí a kompromisy:**
- **Výchozí jazyk se automaticky aktivuje.** `LanguageService::getDefaultLanguage()` hledá výchozí jazyk jen mezi
  aktivními, s neaktivním výchozím by frontend nenašel žádný jazyk.
- **Blokace deaktivace je na serveru** (`handleActivate()`, validace formuláře). Skrytý přepínač v gridu je jen
  UX: `column_status.latte` vykreslí bez tlačítka jen text, dropdown s odkazy zůstává v HTML, ale bez
  tlačítka se neotevře.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne
