# 2026-10-15 — Společný návrh URL z názvu (`UrlValidation`)

**Co:** `handleValidateUrl()` (AJAX návrh URL z názvu pro pole `validate-url`) je jednou v traitu
`UrlValidationTrait` místo čtyř kopií. Presentery článků, kategorií, stránek a sekcí implementují rozhraní
`UrlValidation` a dodávají jen `getUrlType()`.

**Proč:** Čtyři presentery měly stejný handler s drobnými rozdíly. Články a kategorie nevyřazovaly vlastní URL
záznamu, takže u editovaného článku navrhovaly `…-1` místo jeho současné URL.

**Dotčené soubory/oblasti:**
- `app/AdminModule/Presenters/UrlValidation.php` — rozhraní: `handleValidateUrl(string $text)`, `getUrlType()`.
- `app/AdminModule/Presenters/UrlValidationTrait/UrlValidationTrait.php` — implementace: prázdný text = prázdná
  odpověď, jinak `UrlManager::validateUrl($text, getUrlType(), $this->id, $this->id)`. `UrlManager` přes
  `injectUrlValidation()`.
- `ArticlesPresenter`, `CategoriesPresenter`, `PagesPresenter`, `SectionsPresenter` — `implements UrlValidation`,
  `use UrlValidationTrait`, `getUrlType()` (`'article'`, `'category'`, `Pages::URL_TYPE`, `Sections::URL_TYPE`).
  Z `ArticlesPresenter` odstraněn nepoužívaný `$urlManager`.
- `docs/AI-Context/patterns.md` (sekce „Návrh URL z názvu“), `gotchas.md` (`$notInKey` ignoruje typ URL).

**Rozhodnutí a kompromisy:**
Handler v traitu nemá vlastní `#[Secured]`, zdroj se liší podle presenteru. Platí oprávnění třídy (`view`). Stránky
a sekce dřív chtěly `edit`, takže uživatel s právem jen `add` teď návrh URL dostane i u nové stránky. Handler jen
navrhuje volnou URL, nic neukládá.

**Ověřeno** (testovací uživatel s rolí editor, AJAX `?do=validateUrl`, pak smazán): článek 4 „Claude test článek“
→ `claude-test-clanek` (vlastní URL), nový článek → `claude-test-clanek-1`, kategorie 3 „Hlavní“ → `hlavni`,
nová → `hlavni-1`, sekce 2 „Novinky“ → `novinky`, nová sekce i stránka → `novinky-1`, prázdný text → prázdná odpověď.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano, oba
- DB migrace potřeba? ne
