# Modules vs. Plugins

Dva mechanismy rozšíření jádra:

- **`app/Modules/<Name>Module/`** — funkční oblasti dodávané jako součást jádra samotného. Aktuálně:
  `CommentsModule`, `UrlModule` (obě mají obsah), `CoreModule` (prázdný adresář, zatím bez obsahu — rezerva
  do budoucna, ne fungující modul). Modul obvykle má vlastní `Model/`, `Forms/`, `Components/` a podstrom
  `AdminModule/` (občas i `FrontModule/`) s presentery/šablonami. Modul se nedá "vypnout" — je to napořád
  součást jádra.
- **`app/Plugins/<Name>/`** — volitelná/klientsky specifická funkcionalita, zapojovaná přes vlastní
  `config.plugin.neon`, který `theme/config/plugins.neon` includuje jen pro AKTIVNÍ pluginy. Aktuální
  pluginy v jádru: `DynamicForms`, `SimpleSignUp`, `Sliders`, `Stalker`, `Statistics` (počet i seznam se
  časem mění, ověřte `ls app/Plugins`, nespoléhejte na tento výčet natvrdo).

Konvence pro psaní pluginu je v `doc/conventions.md` — nikdy neupravovat soubory jádra přímo (např.
`HomepagePresenter.php`, `default.latte`), chování se má přepsat z pluginu; ukázková DB data patří do
`data/migrations/<název-balíčku>/`; frontend assety balíčku do `www/frontend/<název-balíčku>/`.

## Jak `PresenterFactory` najde presenter uvnitř Modules/Plugins

Výchozí Nette mapování je `App\*Module\Presenters\*Presenter`. `application.presenterFactory` je v
`config.neon` přepsané na `App\Application\PresenterFactory` — když výchozí mapování třídu nenajde, jako
fallback prohledá `app/Plugins`, `app/Modules` a `theme/Plugins` na soubory `*Presenter.php` (čtením
obsahu souboru, ne přes RobotLoader index). Díky tomu můžou presentery ležet pod vlastním namespace, např.
`App\Plugins\Sliders\AdminModule\Presenters\SlidersPresenter` nebo
`App\Modules\CommentsModule\AdminModule\CommentsPresenter`. Při nejednoznačnosti krátkého jména napříč
více plugin/modulovými stromy se rozlišuje porovnáním prostředního segmentu jména presenteru s názvem
složky pluginu/modulu — detaily viz doc komentář `App\Application\PresenterFactory`.

**Gating podle aktivního pluginu:** `PresenterFactory::resolveFallback()` navíc vyřadí kandidáty, jejichž
plugin není zapnutý v `theme/config/plugins.neon` (přes `App\Model\Plugin\PluginRepository::isActive()`).
Bez toho by šlo na vypnutý plugin routovat i po jeho deaktivaci a spadlo by to na chybějící DI službu
(autowiring error) místo čistého 404. Pozor: tohle řeší jen ROUTOVÁNÍ. Existuje samostatný, hlubší problém
na úrovni kompilace DI kontejneru — viz `gotchas.md`, sekce "Vypnutý plugin a kompilace kontejneru".

## Jak jádro volá do pluginu, aniž by ho znalo

Plugin registruje službu/komponentu v `config.plugin.neon` s DI tagem:

```neon
services:
    -
        factory: App\Plugins\Stalker\Stalker
        tags: [presenter.plugin: stalker]

    -
        factory: App\Plugins\DynamicForms\Components\ContactFormComponentFactory
        tags: [presenter.component: contactFormControl]

    -
        factory: App\DI\PluginMenuItem('Dynamic forms', ':Admin:DynamicForms:default', 'DynamicForms')
        tags: [presenter.menu]
```

Presenter jádra (`App\Presenters\BasePresenter`) pak tahá tyhle služby anonymně, přes jméno z tagu:

```php
protected function getPlugin(string $name): object          // presenter.plugin, dle $name
protected function createComponent(string $name): ?IComponent // presenter.component, fallback na parent
```

vyřešeno přes `App\DI\IPluginServiceLocator` / `IPluginComponentLocator`. `presenter.menu` tagované služby
(`App\DI\PluginMenuItem`) se sbírají v `App\DI\PluginServiceLocator::getList()` a vykreslují v admin
`@layout.latte` dropdownu "Other" — dynamicky, podle toho, co je zrovna aktivní.

## Správa pluginů v administraci

`:Admin:Plugins:default` (`App\AdminModule\Presenters\PluginsPresenter`) — datagrid nad `App\Model\Plugin\
PluginRepository::findAll()` (skenuje `app/Plugins` + `theme/Plugins`, porovná s includes v
`theme/config/plugins.neon`), se sloupcem zapnout/vypnout, který přepíše `theme/config/plugins.neon` na
disku. Viz `Changelog/_index.md` pro historii této funkce a co se u ní řešilo (kompilace kontejneru při
vypnutém pluginu).
