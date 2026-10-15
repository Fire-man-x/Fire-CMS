# 2026-10-14 — Mapa autorů záznamů pro ACL (`ResourceOwners`)

**Co:** Nová služba `App\Security\ResourceOwners` (`resourceOwners` v `config.neon`) mapuje zdroj ACL na model se
sloupcem `createdBy` a podle ID záznamu vrátí jeho autora (`getOwnerId()`). Zatím ji nic nevolá.

**Proč:** První krok k hromadné kontrole „autor smí se svým záznamem cokoliv“ v
`AdminModule\Presenters\BasePresenter::checkRequirements()` (atribut s názvem parametru, ve kterém je ID záznamu).
Dnes ji presentery řeší ručně a u většiny akcí (mazání, obrázky, kategorie článku) chybí.

**Dotčené soubory/oblasti:**
- `app/Security/ResourceOwners.php` — mapa zdroj → `BaseModel`, `add()`, `has()`, `getModel()`, `getOwnerId()`
  (`null` bez autora, `RecordNotFoundException` u neexistujícího záznamu, `InvalidArgumentException` u zdroje bez modelu).
- `app/config/config.neon` — služba `resourceOwners`: Articles, Categories, Comments, DynamicForms, Menus, Pages
  (tabulky se sloupcem `createdBy`). Plugin přidá vlastní zdroj přes `resourceOwners: setup: - add('Zdroj', @Model)`.
- `tests/Security/ResourceOwnersTest.phpt` — autor, záznam bez autora, neexistující záznam, zdroj bez modelu, `add()`.
- `docs/Architecture/presenters.md` — sekce „Autor záznamu — `ResourceOwners`“.

**Rozhodnutí a kompromisy:**
Mapa je podle názvu zdroje ACL, ne podle presenteru: atribut v presenteru pak ponese jen název parametru s ID,
zdroj už je v `#[Resource]`. Neexistující záznam je výjimka, ne `null`, aby se nezaměnil se záznamem bez autora
(dnes `ArticlesPresenter::actionDetail()` neexistující článek bere jako vlastní).

**Návaznost:**
- Update `docs/Architecture/*.md`? ano, `presenters.md`
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne
