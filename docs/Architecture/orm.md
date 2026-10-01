# Vrstva Model

**Žádné ORM.** Modely dědí z `App\Model\Database\BaseModel`, což je tenký obal nad `Nette\Database\Explorer`:
název tabulky + primární klíč + základní CRUD nad `Nette\Database\Table\Selection`/`ActiveRow`. Žádný
Record/Repository/Collection pattern, žádné entity, žádný unit-of-work.

## `BaseModel`

```php
abstract class BaseModel
{
    protected Explorer $database;

    public function __construct(Explorer $database) { ... }

    protected function setTableName(string $tableName): void;
    public function getTableName(): string;
    protected function setColumnId(string $columnId): void;     // vlastní PK, výchozí 'id'
    public function getColumnId(): string;
    protected function setForeignKeyColumn(string $column): void; // jak na tabulku odkazují ostatní ('articleId')
    public function getForeignKeyColumn(): string;

    protected function getTable(): Selection;           // $this->database->table($this->getTableName())
    public function findAll(): Selection;                // return $this->getTable();
    public function findById(int $id): Selection;
    public function findByIds(array $ids): Selection;

    public function insert(ArrayHash $data): int;         // vrací nově vložené id
    public function getById(int $id): ?ActiveRow;
    public function update(int $id, array $data): ?bool;
    public function delete(int $id): ?int;
}
```

Konkrétní model typicky jen zavolá `setTableName()`/`setForeignKeyColumn()` v konstruktoru (PK je výchozí
`id`, `setColumnId()` jen u výjimek) a přidá vlastní query metody. Přepisování `insert()`/`delete()` je běžné, když je potřeba doplnit cizí klíč nebo udělat soft
delete (viz níže) — vždy voláním `parent::` nebo přes `$this->findById($id)->update([...])`, ne
přepisováním celé logiky.

```php
class Brands extends BaseModel implements IList, IDatagridSource
{
    public function __construct(Explorer $database)
    {
        parent::__construct($database);
        $this->setTableName('firecms_plugin_brands');
        $this->setForeignKeyColumn('brandId');
    }

    public function getList(): array|Selection
    {
        return $this->findAll()->order('name')->fetchPairs($this->getColumnId(), 'name');
    }

    public function getDatagridSource(): Selection
    {
        return $this->findAll()->order('name');
    }
}
```

## Rozhraní

- **`App\Model\Database\IList`** — `getList()` pro naplnění `<select>`/checklistů. Rozhraní deklaruje `@return
  array|\Nette\Database\Table\Selection` jen v phpDoc bloku (metoda samotná nemá nativní typ). Implementace
  ale nativní typ mít MŮŽE (`: array|Selection`) — PHPStan pak kontroluje kompatibilitu podle
  fakticky vráceného typu; bez explicitního `@return` v implementaci PHPStan použije jen inferovaný typ z
  `return` statementu a nahlásí neshodu s rozhraním, i když je metoda funkčně v pořádku (viz gotchas.md).
- **`App\Model\Database\IDatagridSource`** — `getDatagridSource(): Selection`, aby šlo `ublaboo`/`contributte`
  datagrid naplnit přímo z `Nette\Database\Table\Selection` (lazy, s filtrováním/řazením/stránkováním na
  úrovni SQL, ne v PHP).

Model nemusí implementovat ani jedno z nich — jsou to jen volitelné "schopnosti", které si presenter
odebere přes `instanceof`/typehint tam, kde je potřebuje (grid, select).

## Soft delete

Není to vlastnost `BaseModel` — je to konvence jen tam, kde to dává smysl. Jádro ani pluginy v `app/Plugins`
ho dnes nepoužívají, vzor je pro pluginy/balíčky:

```php
public function delete(int $id): ?int
{
    return $this->findById($id)->update(['deleteDate' => new \DateTimeImmutable()]);
}

private function findActive(): Selection
{
    return $this->findAll()->where($this->getTableName() . '.deleteDate', null);
}
```

`getList()`/`getDatagridSource()` pak volají `findActive()` místo `findAll()`. Čas zapisujte z PHP, ne
`NOW()` — databáze může běžet v jiné časové zóně (viz `gotchas.md`).

## Konvence DB schématu

- Tabulky jádra `firecms_<camelCase>` (`firecms_menuItems`, `firecms_sliders`), tabulky pluginů z `app/Plugins/`
  `firecms_plugin_<camelCase>` (`firecms_plugin_statistics`). Prefix se řídí tím, kdo tabulku vlastní. Bez prefixu je jen
  `migrations` (evidence knihovny `nextras/migrations`, `PluginMigrator` na ni má napevno raw SQL) a
  `system_sessions` (úložiště sessions, viz `sessions.md`).
- Sloupce v camelCase. `AUTO_INCREMENT` PK se jmenuje `id`, cizí klíč na něj `<entita>Id`. Výjimka:
  `firecms_languages.languageId` (`char(2)`).
- Každá tabulka má hned za `id` a sloupci cizích klíčů `createDate datetime NOT NULL DEFAULT current_timestamp()`
  a `updateDate datetime DEFAULT NULL ON UPDATE current_timestamp()`.
- Překládaný obsah je v tabulce `<entita>Descriptions` (jeden řádek na jazyk). Model s takovou tabulkou
  implementuje `App\Model\Database\Translatable` a pro název v gridu používá `TranslatedTitleTrait`. Hlavní tabulka
  název nemá (žádný denormalizovaný `gridName`).
- Migrace jádra jsou v `data/migrations/` (skupiny `structures`, `basic-data`, `dummy-data`, výchozí stav je
  po jednom souboru na skupinu, viz `Changelog/2026-09-30-initial-core-state.md`), migrace pluginu v jeho
  vlastním stromu. Jádro nezakládá žádnou `firecms_plugin_*` tabulku. Už spuštěnou migraci neupravujte,
  změna schématu = nový soubor (viz `gotchas.md`).

## Content model (jádro)

Články (`Articles`), stránky (`Pages` - `firecms_pages` + `firecms_pageDescriptions` + obrázky `firecms_pageFiles`, vnořování
přes `parentId`/`position`, bez revizí/štítků, URL typu `page` - plochý slug i u podstránek, volitelná šablona
`template` z `Pages::Templates`, např. `contact`), sekce (`Sections` - `firecms_sections` + `firecms_sectionDescriptions`, kategorie i články mají `sectionId`),
kategorie (`Categories`, bez sloupce `type`) se zásuvnými „subtypy" přes `app/Forms/CategorySubtype/*FormPart`
(jen `DefaultFormPart`; kategorie nemají typy - odkazy řeší položky menu, galerie a obsahové stránky jsou
Pages, úvodní stránka je `Front:Homepage`), správce souborů
(`Files`/`FileFolders`), menu (`Menus`), štítky (`Tags`), SEO meta (`Metas`), uživatelé/role
(`Users`/`Roles`), vícejazyčnost (`LiveTranslator` + `Languages`/`LanguageService`), hezká URL a
přesměrování (`UrlModule`), komentáře (`CommentsModule`), nastavení webu (`Settings` - jeden řádek
`firecms_settings` s globálními hodnotami `imageResolution`/`themePath`/`contactPhone`/`mapLatitude`/
`mapLongitude` + texty po jazycích ve `firecms_settingDescriptions`, včetně `contactAddress`).

Pravidla, která nejsou vidět ze schématu:
- **Stránky** se mažou trvale (ne do koše), `delete()` uklidí URL i položky menu a podstránky přesune o úroveň
  výš. `createdBy` je `ON DELETE SET NULL`. URL zůstávají ploché, router pracuje s jedním slugem.
- **Sekce** s kategoriemi nebo články nejde smazat (FK bez CASCADE + kontrola v `Sections::delete()`). Článek
  ani kategorie nejde přesunout do jiné sekce, sekce se nastaví při založení. Oprávnění jsou po typu obsahu
  (`Articles`, `Categories`), ne po sekcích. Vnořené množiny kategorií (`categoryLeft`/`Right`) jsou globální.
- **Výchozí jazyk** je vždy právě jeden a aktivní (`Languages::setDefault()`), nejde deaktivovat.
  `LanguageService::getDefaultLanguage()` hledá jen mezi aktivními.
- **`Settings`** má veřejné snake_case klíče (`main_title`, `seo_title`, `image_resolution`, `themePath`,
  `contact_phone`, `contact_address`, `map_latitude`, `map_longitude`) s převodem na sloupce. Je to API pro
  šablony (`$options['main_title']`), ne zapomenutý převod. Globální klíče má formulář Nastavení v jednom
  kontejneru `global` (`Settings::saveGlobal()`), ne v každém jazyce - jinak by vyhrála hodnota posledního jazyka.
  Vymazané pole se ukládá jako NULL (`property_exists`, ne `isset`).
- **`UrlManager::validateUrl()`** hlídá unikátnost slugu napříč celou `firecms_urls` (všechny typy i jazyky).
