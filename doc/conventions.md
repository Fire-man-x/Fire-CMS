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
2. Balíček nikdy neupravuje zdrojové soubory `Fire CMS`, například `.../HomepagePresenter.php` nebo `.../Homepage/default.latte`.
3. Migrace balíčku (schéma i vzorová data) jsou uloženy v jeho vlastním stromu, `./app/Plugins/název
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
    - Balíček pro MariaDB i PostgreSQL má migrace zvlášť pro každou databázi
      (`data/migrations/mysql/`, `data/migrations/pgsql/`, stejné názvy souborů) a adresář skupiny vybírá
      parametrem `%migrations.driver%`, např. `$directory(%rootDir%/app/Plugins/název balíčku/data/migrations/%migrations.driver%)`.
      Odinstalační skript je pak `data/deactivate/mysql.sql` a `data/deactivate/pgsql.sql`. Podrobnosti
      v `docs/Architecture/plugins.md`.
4. Zdroje `[CSS, LESS, images, ...]` jsou uloženy ve složce `./www/frontend/název balíčku`, například `./www/frontned/package/`.
5. Výchozí šablony jsou uloženy v `./App/`, pokud se nejedná o společnou šablonu.
6. Výchozí společné šablony jsou uloženy v `./theme/`. Příklad `./theme/FrontendModule/templates/Homepage/default.latte`

## Klientský projekt
1. Nasazení konkrétního projektu je ve vlastním `.github/workflows/deploy.yml`, který si projekt vytvoří
   kopií vzoru `.github/workflows/deploy.yml.dist` (server, větev a secrets nastaví podle
   [GitHub nasazení](github-deploy.md)). Jádro `Fire CMS` `deploy.yml` neobsahuje. Jeho `ci.yml` (testy +
   PHPStan) běží při pushi do `master` i v projektu a `deploy.yml` ho volá před nasazením.
2. Dokumentace projektu, pokud je potřeba, je v `./theme/docs/` s hlavním souborem `./theme/docs/README.md`.
   `./docs/` a `./doc/` patří jádru a další merge jádra by projektové změny v nich přepsal.
