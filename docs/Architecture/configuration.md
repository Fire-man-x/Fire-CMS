# Konfigurace

## Vrstvení NEON souborů

Pořadí načtení v `app/bootstrap.php` (viz `overview.md` pro detail): `app/config/config.neon` →
`app/config/config.local.neon` (needitovaný gitem, ale ne `.gitignore`-ovaný — pozor na credentials) →
`theme/config/plugins.neon` (jen pokud existuje) → `theme/config/theme.neon` (jen pokud existuje).

`%rootDir%` je vestavěný Nette Bootstrap parametr (kořen projektu, tam kde je `composer.json`). `%appDir%`,
`%wwwDir%`, `%tempDir%` — standardní Nette parametry.

## DI extensions registrované v `config.neon`

```neon
extensions:
    router: App\Router\DI\RouterProviderExtension   # viz routing.md
    fileUpload: Zet\FileUpload\FileUploadExtension   # addFileUpload() na Nette\Forms\Container
    fileManager: App\Components\FileManager\DI\Extension
    tagInput: Achse\TagInput\Extension
    dateInput: Vodacek\Forms\Controls\Extension
    visualpaginator: AlesWita\Components\VisualPaginatorExtension
    antispam: Zet\AntiSpam\AntiSpamExtension
    replicator: Kdyby\Replicator\DI\ReplicatorExtension
```

Plus vestavěné Nette extensions (`application`, `search`, ...) a per-plugin `extensions:` uvnitř
jednotlivých `config.plugin.neon` (viz `plugins.md`).

## `search` extension

```neon
search:
    router:
        in: %appDir%/Router
        implements: App\Router\RouterProvider
```

Najde třídy implementující dané rozhraní/dědící danou třídu pod zadaným adresářem a zaregistruje je jako
anonymní služby s daným tagem (`router`) — používá VLASTNÍ, izolovaný `Nette\Loaders\RobotLoader` (ne ten
sdílený z `bootstrap.php`), takže úpravy hlavního RobotLoaderu (viz `gotchas.md`) tuhle extension
neovlivní.

## Aplikace presenteru — vlastní `PresenterFactory`

```neon
services:
    application.presenterFactory:
        factory: App\Application\PresenterFactory(
            Nette\Bridges\ApplicationDI\PresenterFactoryCallback(_, null)
        )
        setup:
            - setScanDirs([%appDir%/Plugins, %appDir%/Modules, %appDir%/../theme/Plugins])
```

Viz [plugins.md](plugins.md) pro co přesně dělá a proč. Přepisuje **stejnojmennou** službu, kterou by jinak
zaregistrovala vestavěná `Nette\Bridges\ApplicationDI\ApplicationExtension` — proto se `factory:` musí
zadat kompletně znovu (přepsáním `factory` se ztratí args, které by extension jinak doplnila).

## Migrace — `nextras/migrations` + `contributte/console`

```neon
extensions:
	console: Contributte\Console\DI\ConsoleExtension(%consoleMode%)
	migrations: Nextras\Migrations\Bridges\NetteDI\MigrationsExtension

migrations:
	dir: %appDir%/../data/migrations
	driver: mysql
	dbal: nette
	groups:
		structures: {directory: %appDir%/../data/migrations/structures}
		basic-data: {directory: %appDir%/../data/migrations/basic-data, dependencies: [structures]}
		dummy-data: {enabled: %debugMode%, directory: %appDir%/../data/migrations/dummy-data, dependencies: [structures, basic-data]}
```

`%consoleMode%` je vestavěný Nette parametr (`PHP_SAPI === 'cli'`) — `ConsoleExtension` proto v běžném
webovém requestu neregistruje vůbec nic (žádné riziko pro produkční web). Spouští se přes `bin/console`
(malý ručně psaný bootstrap skript, stejný vzor jako `www/index.php` — Composer ho negeneruje sám), nebo
`composer console -- <příkaz>`. `bin/console list` ukáže dostupné příkazy, hlavní jsou `migrations:continue`
(pustí čekající migrace), `migrations:create` (založí nový migrační soubor), `migrations:reset`.

**`data/migrations/` je jen pro jádro** (skupiny výše). Migrace balíčku patří do jeho vlastního stromu a
balíček si svoji skupinu registruje sám — viz `plugins.md`, sekce "Migrace patří do vlastního stromu
balíčku" — takže tahle core konfigurace se při přidávání/odebírání balíčků nemění.

## `Nette\Bridges\ApplicationDI\ApplicationExtension` — kompilační kontrola presenterů

Nette při KOMPILACI DI kontejneru (ne za běhu) najde — přes RobotLoader, `%appDir%` jako výchozí scanDirs —
všechny presentery v `app/` a ověří, že jejich `@inject` vlastnosti jdou naautowirovat. Tahle kontrola nic
neví o `theme/config/plugins.neon`: viz `gotchas.md`, "Vypnutý plugin a kompilace kontejneru" — je to
nevyřešený, jen zdokumentovaný problém (pokus o opravu byl revertován, viz `Changelog/_index.md`).
