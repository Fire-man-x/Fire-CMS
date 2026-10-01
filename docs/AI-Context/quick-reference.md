# Quick Reference (pro AI asistenty)

Kompaktní přehled Fire CMS. Podrobnosti a zdůvodnění v `Architecture/*`. Konvence a tvrdá pravidla jsou v
kořenovém `CLAUDE.md` — ten je závazný, tohle je jen shrnutí pro rychlou orientaci.

## Co je co

| Otázka | Odpověď |
|---|---|
| ORM? | Žádné. `App\Model\Database\BaseModel` = tenký obal nad `Nette\Database\Explorer`. Viz `Architecture/orm.md`. |
| Jak se hledají třídy? | Nette RobotLoader (tokenizace, ne PSR-4 sken). Composer psr-4 je jen konvence, ne vynucené — viz `AI-Context/gotchas.md`. |
| Kde je jádro vs. klientský kód? | `app/Modules/**` a vše mimo `app/Plugins/**` = jádro (promítá se do všech klientských projektů). `app/Plugins/<Name>/` = volitelné systémové pluginy. Kód šitý jednomu klientovi patří do `theme/Plugins/` klientského projektu, ne do jádra. |
| Kolik je pluginů? | Proměnlivé, aktuálně 5 (`ls app/Plugins`). Nepředpokládejte fixní číslo. |
| DB konvence? | Tabulky `firecms_<camelCase>` / `firecms_plugin_<camelCase>`, sloupce camelCase, PK `id`, FK `<entita>Id`, `createDate`/`updateDate`. Viz `Architecture/orm.md`. |
| Datagrid knihovna? | `Contributte\Datagrid\Datagrid` (nový kód). Ne `Ublaboo\DataGrid\DataGrid` (staré, viz composer.json historie). |
| ACL? | `#[Secured] #[Resource('X')] #[Privilege('y')]` atributy z `app/Attributes/`. Vynucuje `AdminModule\Presenters\BasePresenter::checkRequirements()`. |
| Routing admin? | `/administrace/<presenter>/<action>[/<id>]`, presenter jméno v URL malými písmeny/pomlčkami (`pages`, `dynamic-forms`), NE PascalCase. |
| Testy? | `composer test` (Nette Tester nad `tests/`) — hlavně integrační testy nad in-memory SQLite (`UrlManager`, `CustomRouter`, menu, stránky, jazyky) + unit testy (`App\Security\Role`, správce souborů, překlady). Zdaleka nepokrývá celé `app/`. Viz `AI-Context/gotchas.md`. |
| PHPStan gate? | Level 5 (`composer stan`) je fakticky vynucovaná brána (s ~900řádkovou baseline dluhu). `composer stan9`/`stan10` pro přísnější kontrolu nového kódu — spouštějte cíleně na měněné soubory, ne na celý strom. |

## Konvence nového kódu (viz CLAUDE.md "Tvrdá pravidla")

- `declare(strict_types=1);`, explicitní typy, žádný `mixed` bez důvodu.
- Constructor injection (promoted properties) — NE `/** @inject */` veřejné vlastnosti (starý kód to
  používá, nepřepisujte ho kvůli tomu zpětně).
- ACL atributy, ne staré `@Secured`/`@Resource`/`@Privilege` docblocky.
- `#[Persistent]` atribut, ne `/** @persistent */` docblock.
- Latte: `{varType Type $var}` pro každou proměnnou, kterou presenter posílá do šablony.
- Komentáře/dokumentace v kódu i zde → česky.

## Typický CRUD presenter

Grid (`Contributte\Datagrid\Datagrid`, zdroj `IDatagridSource::getDatagridSource()`) + modální formulář
(`BaseFormFactory` potomek, `asModal()`, `setEditId()`/`resetEditMode()`). Plný vzor v
`Architecture/presenters.md` a `AI-Context/patterns.md`.

## Než začnete upravovat

1. **Neodvozujte cestu souboru z namespace** — RobotLoader to nevynucuje, vždy dohledejte skutečné umístění.
   Týká se to i `App\Model\Plugin\*` proti `App\DI\*`, jde skrz strom napříč — grep, ne odhad.
2. **Jádro vs. plugin** — pokud editujete něco mimo `app/Plugins/**`, uvědomte si, že to ovlivní všechny
   klientské projekty forknuté z tohoto jádra (viz `Architecture/overview.md`).
3. **PHPStan noise** — repo má rozsáhlou baseline existujícího dluhu. Spouštějte `composer stan --
   <soubor/adresář>`, ne nad celým `app/`, jinak si spletete cizí šum se svou chybou.
4. **Přečtěte si `AI-Context/gotchas.md`** — sbírka konkrétních pastí, na které narazil někdo před vámi
   (case sensitivity v routingu, kompilace kontejneru při vypnutém pluginu, `addDate` vs. `addOwnDate`,
   ...). Šetří to hodiny opakovaného objevování stejné věci.
