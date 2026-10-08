# 2026-10-07 — Obecná úložiště: schéma názvů a náhledy pro každé úložiště, obecný generátor náhledů

**Co:** Každá položka pod `fileStorage:` (např. `fileStorage: files:`) je plnohodnotné úložiště (`FlysystemStorage`) ze tří částí:
kam (lokální adresář / S3), jak se soubory jmenují (schéma názvů `Naming\NamingScheme`, výchozí `HashNamingScheme`
správce souborů) a které náhledy obrázků smí vzniknout (`thumbnails:` u úložiště, s pojmenovanými rozměry
`aliases`). Generátor náhledů obslouží libovolné úložiště: URL nese název úložiště a klíč originálu, ne hash
z DB.

**Proč:** Soubory mimo správce souborů (alba na disku, soubory pluginů pojmenované po svém) neměly kam
patřit. `FlysystemStorage` měl hashové cesty natvrdo, pluginy proto psaly vlastní úložiště jen pro lokální
disk (nepoběží na S3) a duplikovaly generování náhledů. Teď stačí schéma názvů a přepnutí disk ↔ S3 je jen
změna neonu.

**Dotčené soubory/oblasti:**
- `app/FileStorage/Naming/NamingScheme.php` (nové) — rozhraní schématu názvů: klíč originálu, klíč a výpis
  náhledů (odvozené z klíče originálu), `isOriginalPath()` pro generátor, pojmenování uploadu
- `app/FileStorage/Naming/HashNamingScheme.php` (nové) — dosavadní struktura správce souborů
  (`<h0>/<h1>/<hash>.<ext>`, náhledy `cache/<h0>/<h1>/<hash>.<klíč>.<ext>`), přesunutá z `FlysystemStorage`
- `app/FileStorage/Storages/FlysystemStorage.php` — cesty přes schéma názvů; evidence náhledů podle klíče
  originálu (`FileStorage.thumbnails.<úložiště>.3`); `thumbnail(string $path, string $key)` pro generátor;
  `modifyOriginal()`/`fixOrientation()` berou `ImageEntity`; upload se stejným klíčem smaže staré náhledy;
  soubor, který není obrázek, hlásí `Nette\Utils\UnknownImageFileException`
- `app/FileStorage/Storages/StorageRegistry.php` (nové) — úložiště z neonu podle názvu (pro generátor)
- `app/FileStorage/DI/Extension.php` — úložiště jsou položky přímo pod `fileStorage:` (pevné volby +
  `otherItems` schématu, názvy úložišť proto nesmí být názvy voleb); služby
  `fileStorage.storage|filesystem|naming|allowedThumbnails.<název>` pro každé úložiště, autowiring jen
  `defaultStorage`; volby úložiště `naming`, `thumbnails`, `keepMetadata`, `stripGps`; kontrola názvu
  úložiště a seznamu náhledů při sestavení kontejneru; globální `fileStorage: thumbnails:` i dřívější
  `fileStorage: storages:` shodí start s odkazem na nové místo
- `app/FileStorage/Thumbnails/AllowedThumbnails.php` — `aliases` (pojmenované rozměry) a `fromRequest()`
- `app/FileStorage/Flysystem/EncodedPublicUrlGenerator.php` (nové) — veřejná URL kóduje části klíče (mezery
  a diakritika v názvech souborů); hashové URL beze změny
- `app/FileStorage/Request/FileRequest.php` — přijímá libovolný `File` (dřív jen `FileEntity`, takže stažení
  obrázku správce souborů přes `Front:Files:default` končilo `TypeError`)
- `app/Router/FileRouter.php`, `app/FrontModule/Presenters/FilesPresenter.php` — generátor
  `files/thumbnail/<storage>/<path>/<thumbnail>` přes `StorageRegistry`, bez dotazu do DB; klíč náhledu
  je na konci URL, jinak by ji `.htaccess` (přípona `.jpg`) nepustil do `index.php`
- `app/config/config.neon` — úložiště správce souborů `fileStorage: files:` s náhledy jádra v `thumbnails:`
- smazané `Storages\FileStorage`, `Storages\HashFileStorage`, `Files\Directory`, `DirectoryException`,
  `InvalidCacheDirectoryException` (staré úložiště jen pro lokální disk, jádro ani pluginy je nepoužívaly)
  a jejich záznamy v `phpstan-baseline.neon`
- `tests/FileStorage/CustomNamingSchemeTest.phpt` (nové) — úložiště se schématem alb na lokálním disku (názvy
  s mezerami a diakritikou, náhledy v `mini/`, přepis stejného názvu, generátor jen pro originály);
  `FlysystemStorageTest` (generátor odmítne cokoliv kromě originálu), `ThumbnailTest` (aliasy)

**Rozhodnutí a kompromisy:**
- Náhledy se odvozují z klíče originálu, ne z entity. Generátor tak nepotřebuje DB ani entitu, jen název
  úložiště a klíč z URL. Bezpečnost drží `NamingScheme::isOriginalPath()` a seznam povolených náhledů. Generátor
  vyrobí náhled jen povoleného rozměru z existujícího originálu, který je stejně veřejný přes `publicUrl`.
- Seznam náhledů je jen u úložiště, globální výchozí není. Úložiště mají různé šablony (administrace
  vs. galerie) a společný seznam by povoloval rozměry tam, kde se nepoužívají.
- Alias náhled nepovoluje, jen pojmenuje rozměry. Povolené rozměry jsou tak na jednom místě (`resize`/`crop`).
- Upload se stejným klíčem soubor přepíše (rozhoduje schéma). `HashNamingScheme` kolizi obchází novým hashem jako
  dřív.
- Evidence náhledů začíná znovu (nový název a klíče podle cesty). Uložené náhledy zůstávají, první zobrazení
  každého jde jednou přes generátor, který jen přesměruje.

**Co musí udělat projekt při mergi jádra:**
1. Úložiště přímo pod `fileStorage:` (`fileStorage: storages: files:` → `fileStorage: files:`) a
   `fileStorage: thumbnails:` v `theme.neon` a v `config.plugin.neon` klientských pluginů přesunout pod
   `fileStorage: files: thumbnails:` (správce souborů). Obojí jinak shodí start aplikace s hláškou, kam
   volbu přesunout.
2. Kód volající `FlysystemStorage::thumbnail($file, $key)` → `thumbnail($storage->getOriginalPath($file), $key)`;
   `FlysystemStorage::CacheDirectory` → `HashNamingScheme::CacheDirectory`.
3. Plugin s vlastním `IStorage` nebo dědící ze smazaného `FileStorage`/`HashFileStorage` převést na
   úložiště pod `fileStorage:` se schématem názvů (viz `docs/Architecture/file-storage.md`, sekce
   „Vlastní úložiště pro plugin“).
4. Smazat `temp/cache`.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `file-storage.md`, `routing.md`
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — `gotchas.md` (náhledy u úložiště,
  `isOriginalPath()`)
- DB migrace potřeba? ne
