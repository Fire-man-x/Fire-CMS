# 2026-09-24 — Názvy položek menu v administraci (výběr nadřazené položky)

**Co:** Výběr „Nadřazená položka“ ve formuláři položky menu ukazuje stejné názvy jako grid položek (např.
„O nás“, „— Tipy a rady“), ne anglický typ a id („Page: 2“, „Section: 1“).

**Proč:** Nahlášeno zadavatelem. Formulář skládal název jen z popisku položky, a ten je u odkazů na kategorii,
článek, stránku a sekci běžně prázdný.

**Dotčené soubory/oblasti (jádro):**
- `app/Components/Menu/Model/MenuItemTitles.php` (nový, registrovaný v `config.neon`) — název položky: popisek
  v jazyce → popisek ve výchozím jazyce → název cíle v jazyce → `target`. Názvy cílů načítá hromadně po typech.
- `app/Forms/MenuItemFormFactory.php` — `getParentOptions()` přes `MenuItemTitles`.
- `app/AdminModule/Presenters/MenusPresenter.php` — grid přes `MenuItemTitles` (smazaný duplicitní
  `getContentTitles()` a nepoužité injekce `Articles`/`Pages`). Nově i v gridu náhradní popisek z výchozího jazyka.
- `app/Service/LanguageService.php` — `existLanguage(?string)`. Po necommitnuté změně zadavatele na `string`
  padaly admin presentery, které předávají nevyplněný parametr `language` (`null` = výchozí jazyk): menu,
  články, kategorie, sekce, štítky, komentáře, DynamicForms. `null` teď vrací `false` jako dřív.

**Ověřeno v Chromiu:**
- úprava položky „Náš tým“: nadřazená „O nás“ předvybraná, nabídka česky, bez položky samotné (nevznikne cyklus);
- administrace menu, článků, kategorií, sekcí, stránek, komentářů, štítků a zákazníků 200.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne
