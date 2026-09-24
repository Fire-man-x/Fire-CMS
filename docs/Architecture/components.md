# Komponenty

`app/Components/` obsahuje jak vlastní znovupoužitelné UI komponenty (Nette component model —
`createComponentX()` v presenteru, `IComponent`), tak vendorované (upravené) třetí-stranové knihovny,
fyzicky umístěné do stejného stromu. Nejde o jednotný "trait-based" framework — je to spíš sbírka
jednotlivých, na sobě nezávislých mechanismů. Než něco upravíte, ověřte si, do které kategorie komponenta
patří.

## Vlastní komponenty (výběr)

- `Menu`, `LanguageChanger`, `CategoriesMenu`, `FilesManagerMenu`, `Breadcrumb`, `ViewCounter` — běžné
  Nette komponenty vytvářené přes `createComponentX()`.
- `Menu` (`app/Components/Menu/`) — webové menu `{control menu <location>, maxSublevel, menuClass, itemClass}`.
  Položky (`firecms_menuItems`) jsou samostatné entity, **ne vazba na kategorii**: `linkType`
  (`App\Components\Menu\Model\MenuLinkType`: `category` | `article` | `page` | `section` | `url` | `route`) + jeden sloupec
  `target` (id kategorie/článku/stránky, URL/kotva, nebo `:Front:Presenter:action?param=x`), `parentId` pro
  vnořování, popisek po jazycích ve `firecms_menuItemDescriptions` (prázdný = název kategorie/článku).
  Položka typu `category` k sobě dál automaticky připojí podkategorie se `showInMenu`.
  - Obrázky položek jsou ve `firecms_menuItemFiles` (první = hlavní) a v šabloně jako `$category->image` / `files`.
    Spravují se v gridu položek, výchozí `Menu.latte` je nevykresluje.
  - Menu samo má přeložitelný nadpis `title` (`firecms_menuDescriptions`, `Menus` implementuje `Translatable`
    + `TranslatedTitleTrait`), v šabloně jako `$menuTitle`. Výchozí `Menu.latte` ho nevykresluje. Sloupec
    `name` neexistuje: název v administraci je nadpis ve výchozím jazyce, bez něj `location` (klíč pro šablony).
  - Na kategorii/článek nevede cizí klíč (jeden sloupec `target`). Při hard delete kategorie položky uklízí
    `Categories::delete()` → `Menus::deleteItemsByTarget()`, u stránek `Pages::delete()` (i s URL). Články se mažou do koše a menu zobrazuje jen
    publikované.
  - Model vrací typovaný `MenuItem`, ne `ActiveRow`. Administrace (`MenusPresenter::detail`) je plochý seznam
    ve stromovém pořadí. Přetažení mění pořadí jen mezi sourozenci (datagrid neumí řadit strom), rodič se
    mění ve formuláři (`MenuItemFormFactory`).
- `FileManager` — správa souborů/obrázků (upload, storage, cache, thumbnaily). Vlastní Latte makra
  (`n:image`, `n:src`, `n:crop`, `n:bg` — viz `App\Components\FileManager\Macro\*`), registrovaná přes
  `App\Components\FileManager\DI\Extension` (výchozí makro-extension `Macro\ImageMacro`, dá se přidat
  další přes `fileManager: macros: [...]` v `config.neon`).

`App\Security\User`/`AuthorizatorFactory`/`Acl`/`Role` byly do 2026-09-17 fyzicky součástí tohoto stromu
(`app/Components/Security/`), i když v namespace `App\Security\*` — od 2026-09-18 přesunuty do
`app/Security/`, viz "RobotLoader vs. PSR-4" v `overview.md`. Do `app/Components/` už nepatří.

### `TPresenter` trait vzor

`App\Components\FileManager\TPresenter` je trait, který presenteru přidá `/** @inject */ FileManager
$fileManager` a přepíše `createTemplate()` tak, aby do šablony automaticky vložil `$template->__imagestore`
(potřebné pro `n:image`/`n:src` makra):

```php
class PetsPresenter extends BasePresenter
{
    use \App\Components\FileManager\TPresenter;
    // ...
}
```

Starší presentery (Sliders apod.) totéž dělají ručně (vlastní `/** @inject */ FileManager $fileManager;` +
`$this->template->__imagestore = $this->fileManager;` v `startup()`) — pro nový kód preferujte trait, je
kratší a nejde ho zapomenout doplnit.

Tohle je jediný `T*Presenter` trait tohoto typu v repozitáři — není to obecně zavedený "trait-based
component pattern" napříč projektem, jak by se mohlo zdát z názvu.

## Vendorované třetí-stranové komponenty

Fyzicky pod `app/Components/`, ale nejde o vlastní kód a needitujte je jako by šlo o jádro v běžném slova
smyslu (spíš jako vendor lock uvnitř repa): `DateInput` (Vodáček — registruje extension metodu
`addOwnDate()` na `Nette\Forms\Container`, ne `addDate()` — ta je nativní Nette, jiná signatura, viz
gotchas.md), `VisualPaginator-3.0`, `mail-panel-master`, `TwitterBootstrapRenderer`, `TagsInput`, `jzechy`
(jQuery FileUpload).

Podobně `libs/nextras/datagrid` je lokálně vendorovaná kopie (ruční `psr-4` záznam v `composer.json`,
`Nextras\Datagrid\` → `./libs/nextras/datagrid/src`) — není to skutečný Composer balíček, nenajdete ho ve
`vendor/` ani `composer.lock`.

## Napojení pluginů do presenterů jádra

Presenter (přes `App\Presenters\BasePresenter::getPlugin($name)` / `createComponent($name)`) umí natáhnout
službu/komponentu, kterou plugin zaregistroval pod DI tagem `presenter.plugin` / `presenter.component`,
aniž by presenter znal konkrétní třídu pluginu. Podrobně v [plugins.md](plugins.md).
