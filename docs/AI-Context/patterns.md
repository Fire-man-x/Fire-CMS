# Vzory kódu

Konkrétní, zkopírovatelné vzory. Pro vysvětlení "proč" viz `Architecture/*`.

## Model

```php
declare(strict_types=1);
namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

class X extends BaseModel implements IList, IDatagridSource
{
    public function __construct(Explorer $database)
    {
        parent::__construct($database);
        $this->setTableName('x');
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

Registrace v `app/config/config.neon`, sekce `services:` (anonymní, autowired podle typu):

```neon
services:
    - App\Model\X
    - App\Forms\XFormFactory
```

## Form factory

```php
declare(strict_types=1);
namespace App\Forms;

use App\Model\X;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

class XFormFactory extends BaseFormFactory
{
    public function __construct(
        FormFactory $factory,
        private readonly X $model,
    ) {
        parent::__construct($factory);
    }

    public function create(int|string|null $editId = null): Form
    {
        $form = parent::create($editId);
        $form->addText('name', 'Name')->setRequired(VALIDATE_REQUIRED);
        $form->addSubmit('send', 'Save');
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, ArrayHash $values): void
    {
        unset($values->editId);
        if ($this->isEditMode()) {
            $this->model->update($this->getEditId(), (array) $values);
        } else {
            $this->model->insert($values);
        }
    }

    public function setDefaultValues(Form $form, int $editId): void
    {
        parent::setDefaultValues($form, $editId);
        $defaults = [];
        if ($editId) {
            $defaults = $this->model->findById($editId)->fetch();
            $this->setEditId($editId);
            if (!$defaults) {
                throw new \InvalidArgumentException("Can not edit item with id '" . $editId . "'");
            }
        }
        $form->setDefaults($defaults);
    }
}
```

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
