# Index změn

Chronologický přehled (nejnovější nahoře). Každý řádek odkazuje na detailní záznam v `Changelog/`.

## 2026-10-09

- **Šablony komponent z tématu (`TemplateLookupTrait`), zatím u Menu.** Šablona komponenty se hledá v
  `theme/FrontModule/Components/<Komponenta>/` projektu, pak u komponenty. `{control menu:<view> <location>}` vykreslí
  menu vlastní šablonou `<view>.latte`.
  Viz [2026-10-09-menu-custom-template.md](2026-10-09-menu-custom-template.md).

## 2026-10-08

- **Potvrzovací okno v gridech administrace.** `main.js` hledal `data-target` místo `data-bs-target`
  (Bootstrap 5): „Smazat“ v gridu mazalo hned bez potvrzení a okno pro výběr obrázku zůstávalo prázdné.
  Opraveno i pro gridy překreslené AJAXem. Viz [2026-10-08-admin-confirm-modal.md](2026-10-08-admin-confirm-modal.md).

## 2026-10-07

- **Přímé odkazy na náhledy (`directThumbnails`).** Lokální úložiště může odkazovat přímo na náhledy;
  existující pošle web server, chybějící (i smazaný) vytvoří aplikace na jeho adrese
  (`NamingScheme::parseThumbnailPath()`, `FlysystemStorage::thumbnailFromPath()`). Kořen úložiště potřebuje
  `.htaccess` jako `www/files/.htaccess`. Viz [2026-10-07-direct-thumbnails.md](2026-10-07-direct-thumbnails.md).

- **Obecná úložiště: schéma názvů a náhledy pro každé úložiště.** Každá položka pod `fileStorage:` je
  úložiště (`FlysystemStorage`) s vlastním schématem názvů (`NamingScheme`, správce souborů `HashNamingScheme`)
  a vlastním seznamem náhledů (`thumbnails:` u úložiště, aliasy rozměrů). Generátor náhledů obslouží každé
  úložiště (`/files/thumbnail/<úložiště>/<klíč originálu>/<náhled>`, bez DB). Globální `fileStorage: thumbnails:`
  shodí start aplikace, staré `FileStorage`/`HashFileStorage` jsou smazané. Viz
  [2026-10-07-storage-naming-schemes.md](2026-10-07-storage-naming-schemes.md).

- **`ImageRequest` přijímá libovolnou `ImageEntity`.** Makra `n:src`/`n:image`/`n:crop`/`n:bg` umí i obrázky
  mimo správce souborů (bez id a hashe). Viz
  [2026-10-07-imagerequest-image-entity.md](2026-10-07-imagerequest-image-entity.md).

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
