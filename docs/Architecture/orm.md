# Vrstva Model

**Žádné ORM.** Modely dědí z `App\Model\BaseModel`, což je tenký obal nad `Nette\Database\Explorer`:
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
    protected function setColumnId(string $columnId): void;
    public function getColumnId(): string;

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

Konkrétní model typicky jen zavolá `setTableName()`/`setColumnId()` v konstruktoru a přidá vlastní query
metody. Přepisování `insert()`/`delete()` je běžné, když je potřeba doplnit cizí klíč nebo udělat soft
delete (viz níže) — vždy voláním `parent::` nebo přes `$this->findById($id)->update([...])`, ne
přepisováním celé logiky.

```php
class Facilities extends BaseModel implements IList, IDatagridSource
{
    public function __construct(Explorer $database)
    {
        parent::__construct($database);
        $this->setTableName('facilities');
        $this->setColumnId('id');
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

- **`App\Model\IList`** — `getList()` pro naplnění `<select>`/checklistů. Rozhraní deklaruje `@return
  array|\Nette\Database\Table\Selection` jen v phpDoc bloku (metoda samotná nemá nativní typ). Implementace
  ale nativní typ mít MŮŽE (`: array|Selection`) — PHPStan pak kontroluje kompatibilitu podle
  fakticky vráceného typu; bez explicitního `@return` v implementaci PHPStan použije jen inferovaný typ z
  `return` statementu a nahlásí neshodu s rozhraním, i když je metoda funkčně v pořádku (viz gotchas.md).
- **`App\Model\IDatagridSource`** — `getDatagridSource(): Selection`, aby šlo `ublaboo`/`contributte`
  datagrid naplnit přímo z `Nette\Database\Table\Selection` (lazy, s filtrováním/řazením/stránkováním na
  úrovni SQL, ne v PHP).

Model nemusí implementovat ani jedno z nich — jsou to jen volitelné "schopnosti", které si presenter
odebere přes `instanceof`/typehint tam, kde je potřebuje (grid, select).

## Soft delete

Není to vlastnost `BaseModel` — je to konvence jen tam, kde to dává smysl (např. `Owners`, `Pets`,
`Reservations` v Pet Hotel funkcionalitě). Vzor:

```php
public function delete(int $id): ?int
{
    return $this->findById($id)->update(['delete_date' => new SqlLiteral('NOW()')]);
}

private function findActive(): Selection
{
    return $this->findAll()->where($this->getTableName() . '.delete_date', null);
}
```

`getList()`/`getDatagridSource()` pak volají `findActive()` místo `findAll()`.

## Content model (jádro)

Články (`Articles`), kategorie (`Categories`) se zásuvnými „subtypy" přes `app/Forms/CategorySubtype/*FormPart`
(Default/Homepage/CategoryLink/Url/Gallery, implementují `ICategoryFormType`), správce souborů
(`Files`/`FileFolders`), menu (`Menus`), štítky (`Tags`), SEO meta (`Metas`), uživatelé/role
(`Users`/`Roles`), vícejazyčnost (`LiveTranslator` + `Languages`/`LanguageService`), hezká URL a
přesměrování (`UrlModule`), komentáře (`CommentsModule`).
