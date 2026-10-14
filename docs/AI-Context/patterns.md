# Vzory kódu

Konkrétní, zkopírovatelné vzory. Pro vysvětlení "proč" viz `Architecture/*`.

## Model

```php
declare(strict_types=1);
namespace App\Model\Database;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

class X extends BaseModel implements IList, IDatagridSource
{
    public function __construct(Explorer $database)
    {
        parent::__construct($database);
        $this->setTableName('firecms_x');       // plugin: firecms_plugin_x
        $this->setForeignKeyColumn('xId');      // PK je výchozí 'id'
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

Registrace v `app/config/config.neon`, sekce `services:` (anonymní, autowired podle typu):

```neon
services:
    - App\Model\Database\X
    - App\AdminModule\Forms\XFormFactory
```

## Form factory

Formuláře administrace leží v `app/AdminModule/Forms/` a dědí z `AdminFormFactory`, který vrací `AdminForm`.
Pole nejsou přímo na formuláři, ale v kontejnerech:

- `$form->data` - hodnoty řádku (`$values->data` je `ArrayHash`),
- `$form->locales->addContainer($language)` - překlad (řádek `*Descriptions`) pro jazyk (`$values->locales[$language]`
  je **pole**, ne `ArrayHash`),
- `$form->buttons` - tlačítka.

Handlery mají typy `(AdminForm $form, AdminFormValues $values): void`. `AdminForm::setDefaults()` rozdělí plochý
řádek (`$row->toArray()`) do `data`, překlady předávejte pod klíčem `locales` (`['locales' => [$language => [...]]]`).
Vzor je `DomainFormFactory`. Všechny formuláře v `app/AdminModule/Forms/` už jsou v tomto formátu.

```php
declare(strict_types=1);
namespace App\AdminModule\Forms;

use App\Model\Database\X;
use Nette\Application\UI\Form;

class XFormFactory extends AdminFormFactory
{
    public function __construct(
        private readonly X $model,
    ) {
        parent::__construct();
    }

    public function create(int|string|null $editId = null): AdminForm
    {
        $form = parent::create($editId);
        $form->data->addText('name', 'Name')->setRequired(VALIDATE_REQUIRED);
        $form->buttons->addSubmit('send', 'Save');
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(AdminForm $form, AdminFormValues $values): void
    {
        if ($this->isEditMode()) {
            $this->model->update((int) $this->getEditId(), (array) $values->data);
        } else {
            $this->model->insert($values->data);
        }
    }

    public function setDefaultValues(Form $form, int $editId): void
    {
        parent::setDefaultValues($form, $editId);
        $defaults = $this->model->getById($editId);
        if (!$defaults) {
            throw new \InvalidArgumentException("Can not edit item with id '" . $editId . "'");
        }
        $form->setDefaults($defaults);
    }
}
```

Ručně vykreslená šablona odkazuje na pole přes kontejner: `{input data-active:}`, `{label data-active: /}`,
`{input buttons-send}`, `$form['data']['status']`, `$form['locales'][$actualLanguage]['title']`.

**Pozor:** pokud `setDefaultValues()` nemá `if ($editId)` guard (rovnou `findById($editId)->fetch()` bez
podmínky), NEVOLEJTE ji z `handleAdd()` — spadla by na neexistujícím id. V tom případě `handleAdd()` jen
`resetEditMode()` + `redrawControl()`, bez `setDefaultValues()`. Oba varianty existují v kódu vedle sebe,
ověřte si konkrétní `setDefaultValues()` implementaci, než ji zavoláte s `editId = 0`.

## Latte — grid + modal formulář

```latte
{block h1}
{_"X"}
{/block}

{block buttons}
	<a n:if="$user->isAllowed('X', 'add')" n:href="add!" class="btn btn-primary ajax" data-bs-toggle="modal" data-bs-target="#modal"><i class="fa fa-plus"></i> {_'Add'}</a>
{/block}

{block content}
	{capture $modalContent}
		{snippet xForm}
			{control xForm}
		{/snippet}
	{/capture}
	{include \APP_DIR.'Presenters/templates/Components/modalForm.latte' id=>'modal', label=>$control->translator->translate('X'), modalContent=>$modalContent}

	{control xGrid}
```

`\APP_DIR` je globální konstanta (definovaná v `bootstrap.php`), ne `%appDir%` DI parametr — v latte
šablonách vždy s vedoucím `\`.

## Komponenta akcí nad řádkem pro výpis i detail (front)

Vhodné pro akce nad položkami výpisu (změna stavu, potvrzení, zrušení), které se zobrazují ve výpisu i v detailu.

- Jedna instance obslouží celý výpis. Řádek se předává do `render(ActiveRow $row)`, šablona volá
  `{control itemStatus, $item}` a signál nese id řádku (`handleChange(int $itemId, ...)`).
  Oproti `Multiplier` nevzniká dotaz na každý řádek výpisu.
- Oprávnění se ověřuje v signálu. Komponenta dostane z presenteru „vlastníka“ (`create(int $ownerId)`)
  a řádek dohledá jen v jeho rozsahu, cizí id vrátí `ForbiddenRequestException`. Pravidla přechodů patří do
  služby, komponenta je jen zobrazí a znovu ověří při odeslání.
- Továrna je rozhraní s `create()`, implementaci generuje Nette DI. V `config.plugin.neon` stačí
  `- ...\ItemStatusControlFactory`. Parametr `create()` se páruje s parametrem konstruktoru podle názvu,
  ostatní závislosti se autowirují.
- Flash zprávy přes `$this->getPresenter()->flashMessage()`: layout vykresluje jen flash zprávy presenteru.
- GET odkaz na `handle*` je chráněný same-origin kontrolou Nette 3.3, pro CSRF netřeba formulář.

## Datagrid toggle sloupec (`addColumnStatus`)

```php
$activeColumn = $grid->addColumnStatus('active', 'A.');
$activeColumn->addOption(0, 'Unactive')->setClass('btn-danger')->setIcon('ban')->setTitle('Set as active');
$activeColumn->addOption(1, 'Active')->setClass('btn-success')->setIcon('check-circle')->setTitle('Set as unactive');
$activeColumn->onChange[] = function ($id, $value) {
    $this->handleActivate((int) $id, (bool) $value);
};
```

```php
public function handleActivate(int $id, bool $status): void
{
    $this->model->update($id, ['active' => $status]);
    $this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
    $this->redirect('this');
}
```

**Pozor na oprávnění:** `onChange` běží v signálu datagridu (`handleChangeStatus` komponenty), ne presenteru.
Atributy `#[Resource]`/`#[Privilege]` na metodě presenteru vyhodnocuje jen
`AdminModule\Presenters\BasePresenter::checkRequirements()`, a to při volání signálu presenteru. Při přímém volání
z `onChange` se nevyhodnotí a platí jen oprávnění z třídy presenteru (typicky `view`). V novém kódu proto oprávnění
ověřte v obsluze ručně (`$this->getUser()->isAllowed('X', 'edit')`). Existující `handleActivate()` v jádru (Pages,
Sections, Languages) to zatím nedělají.

## Registrace pluginu (`config.plugin.neon`)

```neon
services:
    - App\Plugins\X\Model\X
    - App\Plugins\X\Forms\XFormFactory

    -
        factory: App\Plugins\X\Xxx
        tags: [presenter.plugin: x]

    -
        factory: App\DI\PluginMenuItem('Titulek', ':Admin:X:default', 'X')
        tags: [presenter.menu]

    # Vlastní migrace (schéma/seed data) - viz Architecture/plugins.md, "Migrace patří do vlastního
    # stromu balíčku". Adresář je uvnitř balíčku, ne v core data/migrations/. Pro MariaDB i PostgreSQL
    # data/migrations/mysql + data/migrations/pgsql, vybírá parametr migrations.driver.
    -
        factory: Nextras\Migrations\Entities\Group
        setup:
            - $name('x-structures')
            - $enabled(true)
            - $directory(%rootDir%/app/Plugins/X/data/migrations/%migrations.driver%)
            - $dependencies([structures])
        tags: [nextras.migrations.group: {for: [migrations]}]
```

Viz `Architecture/plugins.md` pro `presenter.plugin` vs. `presenter.component` vs. `presenter.menu`.

## Soft delete (kde dává smysl)

```php
public function delete(int $id): ?int
{
    return $this->findById($id)->update(['delete_date' => new \Nette\Database\SqlLiteral('NOW()')]);
}

private function findActive(): Selection
{
    return $this->findAll()->where($this->getTableName() . '.delete_date', null);
}
```
