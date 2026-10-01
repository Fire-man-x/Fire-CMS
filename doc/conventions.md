*[Zpět](../Readme.md)**

# Konvence

## PHP
- `Interface`
  - Vždy bez prefix nebo postfix *Example.php*
- `Abstract`
  - Bez prefix nebo postfix, pokud je nutné tak prefix `Base` *BaseExample.php*
- `Trait`
  - Obvykle ve vlastních složkách s postfix `Trait` *ExampleTrait.php*
- `Factory`
  - Továrny nette. Vždy s postfix Factory *ExampleFactory.php*

## Balíčky
1. Balíček vždy vychází z `Fire CMS`.
1. Balíček nikdy neupravuje zdrojové soubory `Fire CMS`, například `.../HomepagePresenter.php` nebo `.../Homepage/default.latte`.
1. Migrace balíčku (schéma i vzorová data) jsou uloženy v jeho vlastním stromu, `./app/Plugins/název
   balíčku/data/migrations/`, ne v `./data/migrations/` (ta patří jen jádru — core `structures`/
   `basic-data`/`dummy-data` skupinám). Balíček si svoji skupinu zaregistruje sám ve vlastním
   `config.plugin.neon`, takže přidání/odebrání balíčku nikdy nevyžaduje zásah do `app/config/config.neon`:
    - Příklad `config.plugin.neon`
		```neon
			services:
				-
					factory: Nextras\Migrations\Entities\Group
					setup:
						- $name('název-balíčku-structures')
						- $enabled(true)
						- $directory(%rootDir%/app/Plugins/název balíčku/data/migrations)
						- $dependencies([structures])
					tags: [nextras.migrations.group: {for: [migrations]}]
		```
    - Migrace se spouští přes `bin/console migrations:continue` (`nextras/migrations` +
      `contributte/console`, viz `composer console -- migrations:continue`).
1. Zdroje `[CSS, LESS, images, ...]` jsou uloženy ve složce `./www/frontend/název balíčku`, například `./www/frontned/package/`.
1. Výchozí šablony jsou uloženy v `./App/`, pokud se nejedná o společnou šablonu.
1. Výchozí společné šablony jsou uloženy v `./theme/`. Příklad `./theme/FrontendModule/templates/Homepage/default.latte`