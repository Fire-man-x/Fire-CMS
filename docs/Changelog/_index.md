# Index změn

Chronologický přehled (nejnovější nahoře). Každý řádek odkazuje na detailní záznam v `Changelog/`.

## 2026-10-07

- **Správce souborů v `app/FileStorage`, DI rozšíření `fileStorage:`.** Namespace `App\Components\FileManager`
  → `App\FileStorage`, sekce neonu `fileManager:` → `fileStorage:` (služby `@fileStorage.filesystem.<název>`).
  Projekty musí upravit kód pluginů a šablon a neon včetně `config.local.neon` na serveru, jinak web po deployi
  nenastartuje. Viz [2026-10-07-filestorage-namespace.md](2026-10-07-filestorage-namespace.md).

## 2026-10-02

- **Nasazení projektů přes GitHub Actions.** Vzor `.github/workflows/deploy.yml.dist` (CI → rsync přes SSH →
  migrace), každý projekt si vytvoří vlastní `deploy.yml`. Dokumentace projektu patří do `theme/docs/`. Návody
  `doc/github-deploy.md` a `doc/update-project.md` nahradily GitLab verze. Viz
  [2026-10-02-github-deploy.md](2026-10-02-github-deploy.md).

- **Pluginy Stalker a Statistics na PostgreSQL.** Migrace a `deactivate` skript zvlášť pro MariaDB a PostgreSQL
  (`data/migrations/<driver>/`), migrace přejmenované na `2026100200000x`. Projekty se zapnutým pluginem musí
  přejmenovat záznam v tabulce `migrations`. Viz [2026-10-02-plugins-postgresql.md](2026-10-02-plugins-postgresql.md).

## 2026-09-30

- **Výchozí stav jádra.** Historie repozitáře sloučená do jednoho výchozího commitu, changelog začíná znovu.
  Přehled funkcí jádra, konvence DB schématu a co čeká projekty založené na starší verzi jádra. Viz
  [2026-09-30-initial-core-state.md](2026-09-30-initial-core-state.md).
