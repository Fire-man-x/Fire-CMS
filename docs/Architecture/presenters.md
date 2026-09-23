# Presentery

## Hierarchie BasePresenter

```
App\Presenters\BasePresenter                          (app/Presenters/BasePresenter.php)
├── App\AdminModule\Presenters\BasePresenter           (ACL enforcement, admin session namespace)
│   ├── App\AdminModule\Presenters\*Presenter          (ArticlesPresenter, MenusPresenter, ...)
│   └── App\AdminModule\SettingsModule\Presenters\BasePresenter
│       └── App\AdminModule\SettingsModule\Presenters\*Presenter
└── App\FrontModule\Presenters\BasePresenter
    └── App\FrontModule\Presenters\*Presenter
```

Presentery pluginů/modulů dědí od `App\AdminModule\Presenters\BasePresenter` (resp. `FrontModule`
obdobně), i když fyzicky leží jinde (`app/Plugins/Sliders/AdminModule/Presenters/SlidersPresenter.php`,
`app/Modules/CommentsModule/AdminModule/CommentsPresenter.php`) — viz [plugins.md](plugins.md) jak je
`PresenterFactory` najde.

## Přepis presenteru z `theme/`

Presenter jádra se v klientském projektu upraví bez zásahu do `app/` takto: v `theme/` vytvořte třídu se
stejnou relativní cestou a názvem, jen s namespace `Theme\` místo `App\`, a nechte ji dědit z původní třídy:

```php
// theme/FrontModule/Presenters/SignPresenter.php
namespace Theme\FrontModule\Presenters;

class SignPresenter extends \App\FrontModule\Presenters\SignPresenter
{
	public function actionIn(): void { ... }
}
```

- **Výběr třídy:** `App\Application\PresenterFactory::themeOverride()` vezme `Theme\X`, pokud existuje,
  jinak `App\X`.
- **Registrace do DI:** `search: themePresenters` v `app/config/config.neon` skenuje celé `theme/` kromě
  `theme/Plugins`.
- **Šablony:** hledají se nejdřív v `theme/`, pak u původní třídy v `app/`
  (`App\Presenters\BasePresenter::formatTemplateFiles()`), takže stačí přepsat jen ty, které se mění.
- **Po přidání přepisu je potřeba smazat `temp/cache/nette.configurator`**, protože v produkčním módu
  se kontejner sám nepřekompiluje.
- **Omezení:** presentery z `app/Plugins` se takhle přepsat nedají (viz
  `Changelog/2026-09-23-theme-presenter-override.md`).

## ACL — `#[Secured]`/`#[Resource]`/`#[Privilege]`

Definováno v `app/Attributes/`. Dá se dát na třídu presenteru (platí pro celý presenter) i na konkrétní
`action*`/`render*`/`handle*` metodu (platí jen pro ni, obvykle jemnější privilege než `view` — `add`,
`edit`, `delete`):

```php
#[Secured]
#[Resource('Pets')]
#[Privilege('view')]
class PetsPresenter extends BasePresenter
{
    #[Secured]
    #[Resource('Pets')]
    #[Privilege('delete')]
    public function handleDelete(int $id): void { ... }
}
```

Vynucuje se v `App\AdminModule\Presenters\BasePresenter::checkRequirements()` — přes reflexi přečte
atributy na třídě NEBO na volané metodě a zavolá `$user->isAllowed($resource, $privilege)`. Při selhání buď
přesměruje na `:Admin:Sign:in` (nenepřihlášený), nebo při `ForbiddenRequestException` ukáže flash a
přesměruje. `$resource`/`$privilege` řetězce musí odpovídat záznamům v DB tabulce `firecms_modules` (viz
`App\Model\Modules`/`Roles`) — v čerstvém checkoutu bez seed dat tam nic není, takže i správně napsaný
`#[Secured]` bez odpovídajícího řádku v `firecms_modules` fakticky nikoho nepustí (nebo podle nastavení role
naopak nikoho neblokuje — ověřte konkrétní chování v `Acl`/`AuthorizatorFactory`, než na to spoléháte v
testu).

Admin session běží v samostatném auth namespace (`'admin'`), nastaveném v téže `checkRequirements()` —
přihlášení na frontendu a v administraci jsou nezávislá.

## Vzor: grid + modální formulář (CRUD)

Naprostá většina administračních CRUD presenterů (Sliders, Menus, Pets, Owners, ...) používá stejnou
kostru:

```php
#[Secured] #[Resource('X')] #[Privilege('view')]
class XPresenter extends BasePresenter
{
    #[Persistent]
    public ?int $id = null;

    /** @inject */                    // staré presentery — nový kód: constructor injection
    public XFormFactory $xFactory;

    /** @inject */
    public X $xModel;

    public function startup(): void
    {
        parent::startup();
        $this->addBreadCrumbLink('X', $this->link(':Admin:X:default', ['id' => null]));
    }

    protected function createComponentXGrid(string $name): Datagrid
    {
        $grid = new Datagrid($this, $name);
        $grid->setDataSource($this->xModel->getDatagridSource());
        $grid->setPrimaryKey($this->xModel->getColumnId());
        // addColumnText/addColumnStatus/addAction('edit'|'delete', ..., 'edit!'|'delete!', [...])
        return $grid;
    }

    protected function createComponentXForm(): Form
    {
        if ($this->id) { $this->xFactory->setEditId($this->id); }
        $this->xFactory->asModal();
        $form = $this->xFactory->create();
        $form->onSuccess[] = function ($form) {
            $form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
            $form->getPresenter()->redirect('this');
        };
        return $form;
    }

    #[Secured] #[Resource('X')] #[Privilege('add')]
    public function handleAdd(): void
    {
        $this->xFactory->resetEditMode();     // NE setEditId(null) — viz gotchas.md
        $this->redrawControl('xForm');
    }

    #[Secured] #[Resource('X')] #[Privilege('edit')]
    public function handleEdit(int $id): void
    {
        $this->xFactory->setEditId($id);
        $this->xFactory->setDefaultValues($this['xForm'], $id);
        $this->redrawControl('xForm');
    }

    #[Secured] #[Resource('X')] #[Privilege('delete')]
    public function handleDelete(int $id): void
    {
        if ($this->xModel->delete($id)) {
            $this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
        } else {
            $this->flashMessage(FAIL_DELETE, FLASH_FAILED);
        }
        $this->redirect('this');
    }
}
```

Šablona (`default.latte`) pak jen `{control xGrid}` + modal include (viz `AI-Context/patterns.md` pro
přesný latte vzor a odkaz na `app/Presenters/templates/Components/modalForm.latte`).

## Persistentní parametry

`#[Persistent] public ?int $id = null;` (z `Nette\Application\Attributes\Persistent`) nahrazuje starý
`/** @persistent */` docblock styl. Používá se pro `$id` (aktuálně editovaný záznam, přenáší se mezi
signály na stejné stránce) a podobné per-presenter stavové parametry.
