# Komponenty

`app/Components/` obsahuje jak vlastní znovupoužitelné UI komponenty (Nette component model —
`createComponentX()` v presenteru, `IComponent`), tak vendorované (upravené) třetí-stranové knihovny,
fyzicky umístěné do stejného stromu. Nejde o jednotný "trait-based" framework — je to spíš sbírka
jednotlivých, na sobě nezávislých mechanismů. Než něco upravíte, ověřte si, do které kategorie komponenta
patří.

## Vlastní komponenty (výběr)

- `Menu`, `LanguageChanger`, `CategoriesMenu`, `FilesManagerMenu`, `Breadcrumb`, `ViewCounter` — běžné
  Nette komponenty vytvářené přes `createComponentX()`.
- `FileManager` — správa souborů/obrázků (upload, storage, cache, thumbnaily). Vlastní Latte makra
  (`n:image`, `n:src`, `n:crop`, `n:bg` — viz `App\Components\FileManager\Macro\*`), registrovaná přes
  `App\Components\FileManager\DI\Extension` (výchozí makro-extension `Macro\ImageMacro`, dá se přidat
  další přes `fileManager: macros: [...]` v `config.neon`).
- `Security` — `App\Security\User`/`AuthorizatorFactory`/`Acl`/`Role`, fyzicky pod
  `app/Components/Security/`, ale v namespace `App\Security\*` (viz "RobotLoader vs. PSR-4" v
  `overview.md`).

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
