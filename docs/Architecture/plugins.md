# Modules vs. Plugins vs. Theme Product Packages

Tři mechanismy rozšíření jádra:

- **`app/Modules/<Name>Module/`** — funkční oblasti dodávané jako součást jádra samotného. Aktuálně:
  `CommentsModule`, `UrlModule` (obě mají obsah), `CoreModule` (prázdný adresář, zatím bez obsahu — rezerva
  do budoucna, ne fungující modul). Modul obvykle má vlastní `Model/`, `Forms/`, `Components/` a podstrom
  `AdminModule/` (občas i `FrontModule/`) s presentery/šablonami. Modul se nedá "vypnout" — je to napořád
  součást jádra.
- **`app/Plugins/<Name>/`** — SYSTÉMOVÝ plugin: volitelná funkcionalita, ale obecná/znovupoužitelná napříč
  VŠEMI klientskými projekty postavenými na Fire CMS (ne šitá na míru jednomu klientovi). Zapojuje se přes
  vlastní `config.plugin.neon`, který `theme/config/plugins.neon` includuje jen pro AKTIVNÍ pluginy.
  Aktuální pluginy v jádru: `DynamicForms`, `SimpleSignUp`, `Sliders`, `Stalker`, `Statistics` (počet i
  seznam se časem mění, ověřte `ls app/Plugins`, nespoléhejte na tento výčet natvrdo).
- **`theme/Plugins/<Name>/`** — "product package": funkcionalita šitá na míru KONKRÉTNÍMU klientskému
  projektu (např. `PetHotel` pro projekt Pet Hotel, `SDHCalendar`/`SDHEvents`/`SDHAttendance`/`SDHTests`/
  `SDHTowns`/`SDHGallery` pro SDH), ne obecně použitelná napříč všemi klienty — proto patří do `theme/`
  (téma/web-specifická vrstva), ne do `app/Plugins/`. Stejná konvence jako u `app/Plugins/` — vlastní
  `config.plugin.neon`, `Model/`/`Forms/`/`AdminModule/`/`FrontModule/`, aktivace přes `theme/config/
  plugins.neon` (viz `doc/product-packages.md` pro historicky zmiňovanou alternativní cestu přes
  `*.theme.neon.dist` → `theme/config/theme.neon` — v praxi ale i tyhle balíčky používají stejný
  `config.plugin.neon` + `plugins.neon` mechanismus jako `app/Plugins/`). Namespace je `Theme\Plugins\
  <Name>\...` (ne `App\Plugins\...`).

**Důležitý důsledek pro "vypnutý plugin a kompilaci kontejneru" (viz `gotchas.md`):** `Nette\Bridges\
ApplicationDI\ApplicationExtension` (co eagerly validuje `@inject`/autowiring VŠECH nalezených presenterů
při kompilaci DI kontejneru) skenuje jen `%appDir%` — pevně dané v `Nette\Bootstrap\Configurator`'s
výchozí registraci té extension, ne konfigurovatelné z `config.neon`. Presentery pod `theme/Plugins/*`
proto tahle eager validace **nikdy** nenajde a nikdy nevalidiuje, ať je balíček aktivní nebo ne — celá
třída bugů, co potkala `Sliders`/`DynamicForms` (viz `gotchas.md`), se `theme/Plugins/*` balíčků strukturálně
netýká. Z toho plyne praktické doporučení: klientsky/projektově specifickou funkcionalitu, kterou budete
chtít bezpečně zapínat/vypínat bez rizika shození kompilace, umisťujte do `theme/Plugins/`, ne do
`app/Plugins/` — `app/Plugins/*` zůstává vyhrazené pro OBECNÉ, systémové pluginy, u kterých se
"vypnuto = 404" řeší jinak (viz routing gating níže) a případný fix kompilace by musel řešit tenhle
zbylý průnik.

Konvence pro psaní pluginu je v `doc/conventions.md` — nikdy neupravovat soubory jádra přímo (např.
`HomepagePresenter.php`, `default.latte`), chování se má přepsat z pluginu; frontend assety balíčku do
`www/frontend/<název-balíčku>/`.

**Migrace patří do vlastního stromu balíčku**, `app/Plugins/<Name>/data/migrations/*.sql` (ne do
`data/migrations/` — to je jen pro jádro, skupiny `structures`/`basic-data`/`dummy-data`). Balíček si
svoji migrační skupinu zaregistruje sám ve vlastním `config.plugin.neon` jako otagovanou službu
(`Nextras\Migrations\Entities\Group`, tag `nextras.migrations.group: {for: [migrations]}`) — přidání
balíčku tak nikdy nevyžaduje editovat core `app/config/config.neon`. Migrace se pouští přes `bin/console
migrations:continue` (`nextras/migrations` + `contributte/console`, viz `Architecture/configuration.md`).

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

**`theme/Plugins/*` presentery potřebují vlastní `search:` blok, aby dostaly DI službu — `app/Plugins/*`
ji má zadarmo.** `PresenterFactory::resolveFallback()` výše řeší jen "na jakou TŘÍDU se má jméno presenteru
namapovat" — zjištěná třída ale ještě musí existovat jako SLUŽBA v DI kontejneru, jinak `Nette\Bridges\
ApplicationDI\PresenterFactoryCallback` skončí na `InvalidPresenterException: No services of type ... found`
(i když routa/jméno presenteru bylo rozpoznané úplně správně — nemá to nic společného s routerem/routováním,
`theme/Plugins/*` balíček kvůli tomuhle vlastní `App\Router\RouterProvider` implementaci nepotřebuje). `app/
Plugins/*` presentery tuhle službu dostanou ZDARMA, protože je najde `Nette\Bridges\ApplicationDI\
ApplicationExtension` při skenu `%appDir%` (viz sekci o "vypnutý plugin a kompilaci kontejneru" výše — STEJNÝ
mechanismus). `theme/Plugins/*` presentery ale tenhle scan (napevno `%appDir%`) nikdy nenajde.

Řešení NENÍ vypisovat každý presenter ručně do `services:` — Nette má vestavěnou `search:` extension pro
přesně tohle (stejnou, jakou `app/config/config.neon` už používá pro nalezení routerů), stačí ji použít i
v `config.plugin.neon` balíčku, namířenou na jeho vlastní adresář:

```neon
search:
    petHotelPresenters:            # unikátní klíč napříč VŠEMI config.plugin.neon souborů (viz níže)
        in: %rootDir%/theme/Plugins/PetHotel
        implements: Nette\Application\IPresenter
        files:
            - *Presenter.php
        tags:
            - nette.inject          # POVINNÉ, viz další odstavec
```

Nový presenter v balíčku se tak zaregistruje automaticky, bez zásahu do `config.plugin.neon` — přesně
podle toho, jestli je balíček (jeho `config.plugin.neon`) zrovna aktivní v `theme/config/plugins.neon`,
protože jinak by se tenhle `search:` blok vůbec nenačetl.

**Pozor na `tags: [nette.inject]` — bez něj presenter spadne na "must not be accessed before
initialization".** `Nette\Bridges\ApplicationDI\ApplicationExtension` u SVÝCH nalezených presenterů (viz
výše) automaticky přidává tag `Nette\DI\Extensions\InjectExtension::TagInject` (`'nette.inject'`) — bez
něj `InjectExtension` nikdy nezavolá `Presenter::injectPrimary()` (ani žádné jiné `@inject`/`injectXxx`),
takže presenterovy vlastní `$httpRequest`/`$httpResponse`/`$user`/... zůstanou needitializované a PRVNÍ
přístup k nim (uvnitř `Presenter::run()`) spadne na `Error: Typed property ... must not be accessed
before initialization` — ne na chybějící službu, takže je to snadné splést s něčím jiným.
`Nette\DI\Extensions\SearchExtension` (co `search:` implementuje) tenhle tag samo nepřidává — je nutné ho
předat explicitně přes `tags:` v konfiguraci, jak je vidět výše.

Klíč pod `search:` (`petHotelPresenters` výše) musí být napříč VŠEMI `config.plugin.neon` soubory
unikátní — `search:` bloky z různých souborů se mergují podle klíče stejně jako `migrations: groups:`
(viz výše), takže kolize jména by tiše nahradila jeden balíčkův blok druhým.

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
