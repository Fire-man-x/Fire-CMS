# Index změn

Chronologický přehled (nejnovější nahoře). Každý řádek odkazuje na detailní záznam v `Changelog/`.

## 2026-10-02

- **Pluginy Stalker a Statistics na PostgreSQL.** Migrace a `deactivate` skript zvlášť pro MariaDB a PostgreSQL
  (`data/migrations/<driver>/`), migrace přejmenované na `2026100200000x`. Projekty se zapnutým pluginem musí
  přejmenovat záznam v tabulce `migrations`. Viz [2026-10-02-plugins-postgresql.md](2026-10-02-plugins-postgresql.md).

## 2026-09-30

- **Výchozí stav jádra.** Historie repozitáře sloučená do jednoho výchozího commitu, changelog začíná znovu.
  Přehled funkcí jádra, konvence DB schématu a co čeká projekty založené na starší verzi jádra. Viz
  [2026-09-30-initial-core-state.md](2026-09-30-initial-core-state.md).
