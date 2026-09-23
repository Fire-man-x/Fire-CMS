# 2026-09-23 — Překlady pluginů (`ChainTranslatorStorage`) + přeložený PetHotel

**Co:** Plugin může mít vlastní překladové soubory v `<plugin>/data/localization/` (stejný formát jako
`data/localization/` jádra). PetHotel má `cs.admin` (97 textů) a `cs.front` (202 textů). Všechny texty PetHotel
jsou v kódu anglicky a jdou přes translator, včetně dřív napevno českých šablon, hlášek a e-mailů.

**Proč:** Na žádost zadavatele. PetHotel měl texty napůl česky napevno a napůl anglicky bez překladu.
LiveTranslator uměl jen jeden adresář (`data/localization/`), plugin tedy neměl kam dát své překlady.

**Dotčené soubory/oblasti:**
- **Jádro (dopadá na všechny projekty):**
  - `app/Localization/ChainTranslatorStorage.php` — hlavní úložiště + úložiště pluginů (tag `translator.pluginStorage`).
  - `app/Localization/ReadOnlyFileStorage.php` — `LiveTranslator\Storage\File` jen pro čtení, nezakládá soubory.
  - `app/config/config.neon` — `translatorStorage` je `ChainTranslatorStorage(File(data/localization), tagged(...))`.
    Bez pluginových překladů se chová stejně jako dřív.
  - `tests/Localization/ChainTranslatorStorageTest.phpt`.
- **PetHotel:**
  - `data/localization/cs.admin`, `cs.front` + registrace v `config.plugin.neon`.
  - frontendové šablony (`Login/*`, `Register/*`, `Customer/*`, `Property/*`, `Properties/*`, `HotelList`,
    `HotelSearch`) — české texty nahrazené `{_"English"}`.
  - `LoginPresenter`, `RegisterPresenter`, `HotelSearch`, `PetSize` — anglické zdrojové texty.
  - `HotelList`, `HotelSearch` (+ factory) — translator konstruktorem (komponenty ho nemají samy od sebe).
  - `Service/LoginCodeSender`, `Service/AccountConfirmation` — e-maily přes translator, odstavce jako samostatné klíče.

**Rozhodnutí a kompromisy:**
- **Frontend se zobrazuje anglicky, dokud není čeština výchozí jazyk.** V DB je jen `en` (výchozí) a neaktivní
  `jn`. Zadavatel nastaví `cs` v administraci (Nastavení → Jazyky). Do té doby jsou anglicky i texty, které
  byly napevno česky. Administrace je vždy `cs`, tam se překlady projeví hned.
- **Přednost má jádro.** Klíče, které `data/localization/cs.*` překládá jinak (`Search` → „Vyhledat“,
  `Phone` → „Tel. číslo“, `Sign in`, `Sign up`, `My account`, `Please enter your e-mail.`), v souborech
  pluginu nejsou, protože by se nikdy nepoužily.
- **Plurály** (`Pro 1 psa / 2 psy / 5 psů`) nahrazené tvarem bez skloňování (`Počet psů: %d`), protože frontend
  nenastavuje LiveTranslatoru pravidla plurálu pro češtinu.
- **Víceřádkové texty** (e-maily) jsou rozdělené na odstavce. Soubor je po řádcích, dlouhé víceřádkové klíče
  jsou v něm křehké a špatně se udržují.
- **Mimo PetHotel zůstává** `theme/FrontModule/templates/Homepage/default.latte` a `@layout.latte` (texty
  Pelíšku napevno česky). Jsou to šablony tématu, ne pluginu.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `plugins.md`, sekce „Překlady pluginu“.
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne (pravidla jsou v `plugins.md`)
- DB migrace potřeba? ne
