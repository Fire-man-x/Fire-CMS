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
obdobně), i když fyzicky leží jinde (`app/Plugins/<Name>/AdminModule/Presenters/<Name>Presenter.php`) — viz [plugins.md](plugins.md) jak je
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
- **Omezení:**
  - presentery z `app/Plugins` se takhle přepsat nedají — `Theme\Plugins\X…` by ležel v `theme/Plugins`,
    který `themePresenters` neskenuje, takže by nebyl DI službou;
  - obecný přepis libovolné třídy tímhle mechanismem řešený není. Služby se dají přepsat v
    `theme/config/theme.neon` (`services:`), třídy vytvářené přes `new` ne;
  - `application: scanDirs: [%rootDir%/theme]` místo `search:` nefunguje: vlastní seznam nahradí výchozí
    `%appDir%` a zmizí všechny presentery z `app/`.

## ACL — `#[Secured('Zdroj', 'právo')]`

Atribut `App\Attributes\Secured`. Dá se dát na třídu presenteru (platí pro celý presenter) i na konkrétní
`action*`/`render*`/`handle*` metodu (platí jen pro ni, obvykle jemnější právo než `view` — `add`, `edit`,
`delete`). Atribut lze opakovat, každá kombinace zdroj + právo se ověřuje zvlášť a musí projít všechny:

```php
#[Secured('Pages', 'view')]
class PagesPresenter extends BasePresenter
{
    #[Secured('Pages', 'delete')]
    public function handleDelete(int $id): void { ... }
}
```

Vynucuje se v `App\AdminModule\Presenters\BasePresenter::checkRequirements()` (`checkSecured()`) — přes reflexi
přečte atributy na třídě NEBO na volané metodě a zavolá `$user->isAllowed($resource, $privilege)`. Při selhání buď
přesměruje na `:Admin:Sign:in` (nenepřihlášený), nebo při `ForbiddenRequestException` ukáže flash a
přesměruje. Zdroj a právo musí odpovídat záznamům v DB tabulce `firecms_modules` (viz
`App\Model\Database\Modules`/`Roles`) — v čerstvém checkoutu bez seed dat tam nic není, takže i správně napsaný
`#[Secured]` bez odpovídajícího řádku v `firecms_modules` fakticky nikoho nepustí (nebo podle nastavení role
naopak nikoho neblokuje — ověřte konkrétní chování v `Acl`/`AuthorizatorFactory`, než na to spoléháte v
testu).

Atributy u handlerů komponent (`Control`, např. `FilesManagerMenu`, `CategoriesMenu`) nikdo nevynucuje,
`checkRequirements()` s nimi má jen presenter administrace.

### Autor záznamu — `recordIdParameter`, `ResourceOwners`

Autor smí se svým záznamem cokoliv, i bez práva `edit`/`delete` v roli. Zajišťuje to pravidlo `deny` s podmínkou
v `AuthorizatorFactory`. Platí jen tehdy, když `isAllowed()` dostane zdroj jako `App\Security\Resource` s ID autora,
např. `isAllowed(new Resource('Articles', $createdBy), 'edit')` (roli s ID uživatele doplní `App\Security\User`).

V atributu to zařídí `recordIdParameter` s názvem parametru požadavku (URL), ve kterém je ID záznamu:

```php
#[Secured('Articles', 'edit', recordIdParameter: 'id', privilegeWithoutRecord: 'add')]
public function actionDetail(?int $id = null): void
```

- ID v požadavku je: ověří se `edit` nad zdrojem s autorem záznamu. Neplatné ID nebo neexistující záznam = 404.
- ID chybí (nový záznam): ověří se právo `privilegeWithoutRecord` (bez něj `privilege`) nad samotným názvem zdroje.

Zatím to používá jen `ArticlesPresenter` (detail, mazání, aktivace, obrázky, kategorie, štítky).

Právo z role platí pro všechny záznamy, pravidlo autora jen přidává. Handler, který mění existující záznam (připojí
obrázek, kategorii), proto chce `edit`, ne `add`: s `add` by ho uživatel s právem přidávat spustil i nad cizím záznamem.
Akci, kterou autor nad svým záznamem dělat nemá (schválení), `recordIdParameter` nedávejte. Kontrola na akci kryje i formulář na stránce (akce běží
před signálem), ne ale handlery, které jdou zavolat i na jiné akci.

Autora podle ID záznamu zjistí služba `App\Security\ResourceOwners` (`resourceOwners` v `config.neon`). Je to
mapa zdroj ACL → model se sloupcem `createdBy`. V jádru je Articles, Categories, Comments, DynamicForms, Menus a
Pages. `getOwnerId($resource, $id)` vrátí ID autora (`null` bez autora), u neexistujícího záznamu vyhodí
`RecordNotFoundException`, u zdroje bez modelu `InvalidArgumentException`. Plugin přidá vlastní zdroj ve svém
`config.plugin.neon`:

```neon
services:
	resourceOwners:
		setup:
			- add('Calendar', @App\Plugins\Calendar\Model\Events)
```

Admin session běží v samostatném auth namespace (`'admin'`), nastaveném v téže `checkRequirements()` —
přihlášení na frontendu a v administraci jsou nezávislá.

## Vzor: grid + modální formulář (CRUD)

Naprostá většina administračních CRUD presenterů (Sliders, Menus, Pages, Sections, ...) používá stejnou
kostru:

```php
#[Secured('X', 'view')]
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

    #[Secured('X', 'add')]
    public function handleAdd(): void
    {
        $this->xFactory->resetEditMode();     // NE setEditId(null) — viz gotchas.md
        $this->redrawControl('xForm');
    }

    #[Secured('X', 'edit')]
    public function handleEdit(int $id): void
    {
        $this->xFactory->setEditId($id);
        $this->xFactory->setDefaultValues($this['xForm'], $id);
        $this->redrawControl('xForm');
    }

    #[Secured('X', 'delete')]
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

Kostra výše je zjednodušená. V presenteru s persistentním `$id` pojmenujte parametr signálů z gridu podle
cizího klíče (`$model->getForeignKeyColumn()`, tedy `handleDelete(int $xId)`), ne `id` — parametr signálu je
parametr presenteru a přepsal by aktuální `$id`. ID z datagridu přichází jako řetězec, typujte ho (viz
`gotchas.md`).

Šablona (`default.latte`) pak jen `{control xGrid}` + modal include (viz `AI-Context/patterns.md` pro
přesný latte vzor a odkaz na `app/Presenters/templates/Components/modalForm.latte`).

## Persistentní parametry

`#[Persistent] public ?int $id = null;` (z `Nette\Application\Attributes\Persistent`) nahrazuje starý
`/** @persistent */` docblock styl. Používá se pro `$id` (aktuálně editovaný záznam, přenáší se mezi
signály na stejné stránce) a podobné per-presenter stavové parametry.
