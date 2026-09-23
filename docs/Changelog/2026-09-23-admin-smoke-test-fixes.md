# 2026-09-23 — Proklikání administrace po přejmenování sloupců, rozhraní `Translatable`

**Co:** Po resetu DB na nové schéma (camelCase sloupce, PK `id`, bez `gridName`) byla celá administrace proklikána
v Chromiu. Opraveny chyby, které po přejmenování zbyly, a řada starších chyb, které blokovaly základní akce
(ukládání, aktivace, mazání). Modely s překladovou tabulkou `*Descriptions` nově implementují rozhraní
`App\Model\Translatable`.

**Proč:** Přejmenování sloupců ověřené jen přes PHPStan a testy nestačí. Názvy sloupců jsou v řetězcích a řada
akcí (AJAX signály datagridu, modální formuláře) se dá ověřit jen v běžící aplikaci. Rozhraní `Translatable`
jsme zavedli na žádost zadavatele, aby bylo u modelu vidět, že má překladovou tabulku.

**Dotčené soubory/oblasti:**
- **Chyby z přejmenování (moje):**
  - `Languages` neměl `setForeignKeyColumn()`, takže grid jazyků spadl.
  - `TagFormFactory` hledal v překladové tabulce podle `getColumnId()` místo `getForeignKeyColumn()`
    (validace unikátnosti a výchozí hodnoty při editaci).
- **Rozhraní:** `app/Model/Translatable.php` (nové, obsahuje `getTranslationTable()` a `getForeignKeyColumn()`).
  Implementují ho `Articles`, `Categories`, `Tags` a `Settings`. `Categories::getTranslationTable()` dostal
  návratový typ.
- **Starší chyby, které blokovaly administraci:**
  - **Datagrid předává ID jako řetězec**, ale modely mají `getById(int)` a `strict_types`. ID parametry
    handlerů (`handleDelete(int $articleId)`, …) a closure `onChange` gridů jsou teď přetypované. U jazyků
    je ID řetězec (`handleActivate(string $languageId)`).
  - **`BaseFormFactory::setEditId()`** převádí číselné ID ze skrytého pole na `int`. Volání
    `update($this->getEditId(), $values)` dostala `(array)`, protože `ArrayHash` se do `update(int, array)`
    nevejde.
  - **`Service\Article|Category::makeBackup()`**: `ArrayHash::from($activeRow)` → `->toArray()`. Uložit,
    aktivovat ani schválit článek či kategorii s přiřazenou kategorií nebo štítkem do té doby nešlo.
  - **Staré názvy tabulek z přejmenování 2026-09-17:**
    - `category_descriptions` (front menu), `article_tags`/`category_tags` (výpis podle štítku),
      `article_comments`/`category_comments` (komentáře);
    - `slider_items` (detail slideru), `dynamic_forms` (grid formulářů).
    Nahrazeny konstantami a `getTableName()`. `Menus` nemělo `use App\Model\Categories`.
  - **Další drobnosti:**
    - `Tags::insertTranslation()` vracel `ActiveRow` místo `int`;
    - `TagFormFactory` volal `insert(array())`;
    - `MetaFormFactory::formValidate(array)` četl vlastnosti pole;
    - `LanguageFormFactory::setDefaultValues()` měl `int` ID;
    - `Service\Meta` měl `null` malými písmeny (Nette ho obalilo backtickami, takže padal front detail
      s metadaty);
    - `FilesManagerMenu` používal zastaralé `$this->presenter` a netypovaný `$folder_id`;
    - šablona slideru měla `PROMPT_VALUE` bez `\`;
    - `ArticlesPresenter` (front) měl `private int $parent` s `null`.
  - **Neexistující sloupec `default`:** `SlidersPresenter` a `UrlsPresenter::handleDelete()` kontrolovaly
    `->default` na tabulce, která ten sloupec nemá. Kontrola vždy spadla, takže slider ani URL nešly smazat.
    Kontrola je odstraněna.
  - `phpstan-baseline.neon`: odstraněny položky pro opravené chyby.

**Rozhodnutí a kompromisy:**
- `BaseModel::update()` zůstává `update(int, array)`. Rozšířit ho na `ArrayHash`/`iterable` by znamenalo, že
  každý model v navazujících projektech, který `update(int, array)` přepisuje, spadne na nekompatibilní signatuře.
  Přetypování je proto v místě volání.
- `Translatable` obsahuje jen to, co mají všechny čtyři modely společné. `insertTranslation()` a spol. mají
  rozdílné signatury a `Settings` místo nich `saveForLanguage()`.
- Úmyslně neřešeno (mimo rozsah, původní stav):
  - DynamicForms detail (`$id` není inicializované, plugin má víc takových míst);
  - URL formulář je kopie formuláře jazyka;
  - grid kategorií článku a formulář metadat jsou v šablonách zakomentované;
  - frontend chce `theme/FrontModule/templates/Categories/detail.site.latte`, která neexistuje;
  - homepage potřebuje kategorii typu `homepage`, kterou základní data nezakládají.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — gotchas.md (Translatable, ID z datagridu jako řetězec)
- DB migrace potřeba? ne
