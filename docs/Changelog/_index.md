# Index změn

Chronologický přehled (nejnovější nahoře). Každý řádek odkazuje na detailní záznam v `Changelog/`.

## 2026-09-16

- **Znalostní báze `docs/` opravena, aby odpovídala tomuto projektu.** Adresář `docs/` byl omylem zkopírován
  z jiného projektu (jiné ORM — Record/Repository/Collection místo `BaseModel`, jiný počet pluginů, jiná
  moduly). Přepsán tak, aby popisoval skutečnou architekturu Fire CMS. `CLAUDE.md` "Tvrdá pravidla" opravena
  (`bin/phpstan.neon` neexistuje, skutečná brána je `app/config/phpstan.neon` level 5).
- **Port funkcionality Pets/Owners/Reservations/Facilities/ContractTemplates z `pet-hotel`.** Porovnáno
  s `/var/www/pet-hotel` (starší klientský projekt forknutý z tohoto jádra), chybějící třídy/šablony
  přeneseny a převedeny na PSR-4 + aktuální konvence jádra (constructor injection, PHP atributy pro ACL,
  `Contributte\Datagrid`). Vynechána mrtvá/nedokončená vedlejší větev (tisk rezervace do PDF, "Projects"
  multi-tenancy stub, starší nahrazená core infrastruktura typu `RouterFactory`/`ExtensionLoader`). Nová DB
  migrace `data/migrations/20260916120000.sql`. Nic z tohohle nebylo commitnuto (na žádost uživatele —
  čeká na jeho vlastní review a funkční test).
- **Správa pluginů v administraci (`:Admin:Plugins:`) + revertovaný pokus o opravu kompilace kontejneru
  při vypnutém pluginu.** Přidán `App\AdminModule\Presenters\PluginsPresenter` +
  `App\Model\Plugin\PluginRepository` — datagrid nad nalezenými pluginy s zapnout/vypnout přepínačem
  (přepisuje `theme/config/plugins.neon`). Následně objeven hlubší, nezávislý problém: vypnutý plugin
  shazuje kompilaci CELÉHO DI kontejneru (viz `AI-Context/gotchas.md`, "Vypnutý plugin a kompilace
  kontejneru"). Oprava (`App\DI\PluginPresenterGateExtension`) byla funkčně otestovaná a fungovala, ale na
  žádost uživatele byla revertována (`git revert` commitu "Plugins Gate") — momentálně tedy jen routing-time
  gating v `App\Application\PresenterFactory` zůstává, hlubší problém je jen zdokumentovaný, ne opravený.
