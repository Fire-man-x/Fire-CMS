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
1. Výchozí/vzorová data databáze jsou uloženy ve složce `./data/migrations/název balíčku`, například `./data/migrations/dummy-data`.
    - Příklad konfiguračního souboru `package.theme.neon.dist`
		```shell
			migrations:
				groups:
					dummy-data:
						directory: %rootDir%/data/migrations/dummy-data
		```
1. Zdroje `[CSS, LESS, images, ...]` jsou uloženy ve složce `./www/frontend/název balíčku`, například `./www/frontned/package/`.
1. Výchozí šablony jsou uloženy v `./App/`, pokud se nejedná o společnou šablonu.
1. Výchozí společné šablony jsou uloženy v `./theme/`. Příklad `./theme/FrontendModule/templates/Homepage/default.latte`