# 2026-10-15 — `#[Secured('Zdroj', 'právo')]` místo trojice atributů, autor záznamu v atributu

**Co:** Oprávnění v administraci se zapisuje jedním opakovatelným atributem `#[Secured('Articles', 'view')]` místo
`#[Secured]` + `#[Resource]` + `#[Privilege]`. Nový parametr `recordIdParameter` (název parametru požadavku s ID záznamu) pustí
autora záznamu ke všemu, `privilegeWithoutRecord` je právo pro požadavek bez ID (nový záznam). Zatím jen na
`ArticlesPresenter::actionDetail()`.

**Proč:** `checkRequirements()` kontroloval jen tehdy, když našel `#[Resource]` i `#[Privilege]`. Samotné `#[Secured]`
nebo zapomenutý `#[Privilege]` znamenalo tichou díru. Povinné parametry konstruktoru to vyloučí. Zároveň krok
k hromadné kontrole „autor smí se svým článkem cokoliv“ (navazuje na
[2026-10-14-resource-owners.md](2026-10-14-resource-owners.md)).

**Dotčené soubory/oblasti:**
- `app/Attributes/Secured.php` — `resource`, `privilege`, `recordIdParameter`, `privilegeWithoutRecord`, `IS_REPEATABLE`.
  `app/Attributes/Resource.php` a `Privilege.php` smazané, bez zpětné kompatibility.
- `app/AdminModule/Presenters/BasePresenter.php` — `checkRequirements()` ověří každý `#[Secured]` zvlášť
  (`checkSecured()`). S `recordIdParameter` zjistí autora přes `ResourceOwners` (`injectResourceOwners()`) a ptá se na
  `App\Security\Resource` s ID autora. Neplatné ID nebo neexistující záznam = 404.
- 80 výskytů v presenterech administrace a v `FilesManagerMenu`/`CategoriesMenu` převedeno 1:1, staré docblock anotace
  (`@SecuredInside`, `@Resource (…)`, `@Privilege (…)`) zůstaly.
- `app/AdminModule/Presenters/ArticlesPresenter.php` — `actionDetail()`:
  `#[Secured('Articles', 'edit', recordIdParameter: 'id', privilegeWithoutRecord: 'add')]` místo ruční kontroly autora (ta brala neexistující
  článek jako vlastní). `handleDelete()`: `#[Secured('Articles', 'delete', recordIdParameter: 'articleId')]`, autor smaže
  (do koše) vlastní článek i bez práva `delete`. Tlačítko Smazat v gridu se řídí právem `delete` (dřív `edit`).
  Ostatní handlery: `recordIdParameter: 'id'` u obrázků, kategorií a štítků (`handleLoadTags` navíc
  `privilegeWithoutRecord: 'add'` pro nový článek), `recordIdParameter: 'articleId'` u `handleActivate` (ruční kontrola
  zůstala kvůli přímému volání z gridu). `handleAddImage` a `handleAddCategory` chtěly `add`, takže autor s `add` připojil
  obrázek nebo kategorii i k cizímu článku (`articles/default/<id>?do=addImage`), teď `edit`. `handleApprove` záměrně
  bez `recordIdParameter`, autor by si jinak schválil vlastní článek.
- Dokumentace: `CLAUDE.md`, `docs/Architecture/presenters.md` (sekce ACL a „Autor záznamu“), `overview.md`,
  `routing.md`, `docs/AI-Context/quick-reference.md`, `patterns.md`, `gotchas.md`.

**Rozhodnutí a kompromisy:**
Jeden zdroj a jedno právo v atributu místo polí: všech 80 výskytů mělo jedno a jedno, víc oprávnění = víc atributů.
Kontrola autora na akci kryje i formulář na stránce (akce běží před signálem). Handlery ostatních presenterů zatím ověřují jen právo role, jdou zavolat i na jiné akci a potřebují vlastní `recordIdParameter`.
Atributy u handlerů komponent (`FilesManagerMenu`, `CategoriesMenu`) se nevynucují ani teď, `checkRequirements()`
má jen presenter.

**Ověřeno** (testovací uživatel s rolí author = Articles `add`, přes curl s vlastními cookies, pak smazán i s daty):
cizí článek → přesměrování s „Nemáte přístup do sekce 'Articles' s přístupovými právy 'edit'.“, uložení formuláře
cizího článku neprojde, nový článek se otevře (`add`), vlastní článek se otevře i uloží, neexistující článek → 404,
Pages/Users/Menus odmítnuté (`view`), Tags povolené.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano, `presenters.md`, `overview.md`, `routing.md`
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano, oba (zápis atributu)
- DB migrace potřeba? ne
- Klientské projekty: pluginy s `#[Secured]` + `#[Resource]` + `#[Privilege]` v `theme/Plugins/` je potřeba převést.
  Starý `#[Secured]` bez parametrů po mergi skončí chybou (`ArgumentCountError`), ochrana nezmizí potichu.
