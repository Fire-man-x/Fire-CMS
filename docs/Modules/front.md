# FrontModule

Veřejný frontend. `App\FrontModule\Presenters\BasePresenter` (dědí `App\Presenters\BasePresenter`) — bez
ACL enforcementu (frontend nemá privilegovaný obsah stejným způsobem jako administrace), vlastní auth
namespace odlišný od administrace (viz `Architecture/presenters.md`).

## Struktura

- `app/FrontModule/Presenters/*Presenter.php` — `Homepage`, `Articles`, `Categories`, `Pages`, `Sections`,
  `Tags`, `Search`, `Sign`, `Files` (stahování a generátor náhledů), `Redirect` (301 na doménu jazyka).
- `app/FrontModule/Components/*` — front-specifické komponenty (`Articles`, `Categories`,
  `SearchControl`, `LoginLinkControl`).
- `app/FrontModule/templates/` — latte šablony, `@layout.latte` pro layout webu.

## Routing na frontend

Řeší `CustomRouter`/`FrontRouter`, viz `Architecture/routing.md` — hezká URL z DB (`UrlModule`), fallback
`[<locale>/]<presenter>/<action>[/<id>]`.

## Homepage a obsah

Úvodní stránka je `Front:Homepage` (`HomepagePresenter`), ne kategorie: nejnovější publikované články (první
zvýrazněný), přehled aktivních sekcí s kategoriemi a výzva s odkazem na stránku se šablonou `contact`. Je přes
celou šířku - šablona mimo bloky nastaví `{var $showSidebar = false}` a `{var $showBreadcrumb = false}`
(výchozí `true` v `@layout.latte`, proměnné z podřízené šablony Latte do layoutu předá). Klientský projekt ji
upraví přepisem presenteru nebo šablon v `theme/` (viz `Architecture/presenters.md`).

Stránka (`Pages`) s vyplněnou šablonou (`firecms_pages.template`, výběr v administraci, klíče v
`Pages::Templates`) se vykreslí `Pages/<template>.latte` místo `detail.latte` - `PagesPresenter::renderDetail()`
připraví stejná data a jen přepne view, takže téma šablonu přepíše stejně jako detail. `contact` = kontaktní
stránka: text stránky jako úvod, adresa/telefon/e-mail a GPS bod mapy z Nastavení, mapa jako embed Mapy.com
(`frame.mapy.cz/zakladni?x=<lon>&y=<lat>&z=<zoom>&source=coor&id=<lon>,<lat>`, bez API klíče; `mapy.com` ani
`frame.mapy.com` do iframe vložit nejde), zoom pevně v šabloně.

Boční panel: `{control menu <location>, maxSublevel, menuClass, itemClass, true}` vykreslí nad menu jeho
nadpis z administrace (`$menuTitle`), layout tak odlišuje `left-menu` a `text-box`.

Šablony frontendu jsou pro Bootstrap 5.3 (atributy `data-bs-*`). Výchozí téma `www/theme/default`
(clean-blog) je původně pro Bootstrap 3 - rozdíly (bílé položky dropdownu, výška přichycené navigace, okraje
`<p>`) vyrovnává `www/theme/default/style.css`. Kategorie nemají typy, obsahové stránky
a galerie jsou `Pages`, výpisy článků jsou po sekcích (`Front:Sections:detail`), viz `Architecture/orm.md`.

Výpisy i detail článku zobrazují jen publikované články (`Articles::findPublished()`: publikované, aktivní,
aktuální verze, publikace v minulosti, nevypršené). Koncept článku i stránky vrací 404.

Pluginy mohou do frontendu přidat komponentu registrovanou pod tagem `presenter.component` (viz
`Architecture/plugins.md`) — např. `SimpleSignUp`'s `signInFastForm`. Stejným tagem registruje jádro
`contactFormControl` (dynamické formuláře) a `pluginSlider` (slider) - dřív pluginy, názvy komponent zůstaly
kvůli šablonám projektů.
