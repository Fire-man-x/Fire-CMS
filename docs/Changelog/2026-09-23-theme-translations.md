# 2026-09-23 — Překlady tématu (`theme/data/localization/`) + přeložená homepage a layout

**Co:** Téma má vlastní překladový soubor `theme/data/localization/cs.front` (41 textů). Texty v
`theme/FrontModule/templates/@layout.latte` a `Homepage/default.latte` jsou anglicky přes translator, stejně
jako PetHotel. Překlady tématu mají přednost před překlady pluginů.

**Proč:** Navazuje na [2026-09-23-plugin-translations-pethotel.md](2026-09-23-plugin-translations-pethotel.md).
Homepage a layout zůstaly napevno česky, protože jsou to šablony tématu, ne pluginu.

**Dotčené soubory/oblasti:**
- **Jádro:** `app/Localization/ChainTranslatorStorage.php` má nový druhý argument `$themeStorages`, pořadí
  čtení je projekt → téma → pluginy. `app/config/config.neon` předává `tagged(translator.themeStorage)`.
- `theme/config/theme.neon` — `ReadOnlyFileStorage(theme/data/localization)` s tagem `translator.themeStorage`.
- `theme/data/localization/cs.front`.
- `theme/FrontModule/templates/@layout.latte`, `Homepage/default.latte` — `{_"English"}`, i `aria-label` a
  potvrzovací dialog (`Zrušit` → `Cancel`).
- `theme/Plugins/PetHotel/data/localization/cs.front` — „For hotel owners“ sjednoceno na „Pro majitele hotelů“.
- `tests/Localization/ChainTranslatorStorageTest.phpt` — priorita tématu před pluginem.

**Rozhodnutí a kompromisy:**
- **Samostatný tag pro téma**, ne `translator.pluginStorage`: `tagged()` vrací služby v pořadí načtení configů a
  `theme.neon` se načítá po `plugins.neon`, takže by vyhrál plugin. Téma ale typicky přizpůsobuje texty pluginu.
- **Beze změny zůstává:** názvy měst v „Oblíbené lokality“ (hodnota filtru, musí odpovídat `town` v DB) a
  značka „Pelíšek“.
- **Frontend je dál anglicky**, dokud není `cs` výchozí jazyk (viz předchozí záznam).

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `plugins.md`, sekce „Překlady pluginu a tématu“.
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne
