# 2026-10-07 — `ImageRequest` přijímá libovolnou `ImageEntity`

**Co:** `ImageRequest` (konstruktor, `fromMacro()`, `crop()`, `getFile()`, `setFile()`) pracuje s `ImageEntity`
místo `HashImageEntity`. Makra `n:src`, `n:image`, `{image}`, `n:crop` a `n:bg` tak umí i obrázky mimo
správce souborů (bez id a hashe), které plugin čte např. přímo z disku a obsluhuje vlastním `IStorage`.

**Proč:** Plugin s vlastním úložištěm musel svou entitu dědit z `HashImageEntity` a `getId()`/`getHash()`
přepisovat na výjimku, jen aby prošla typem v `ImageRequest`. Stejně tak nešla použít `ImageEntity` ze
starého `Storages\FileStorage`.

**Dotčené soubory/oblasti:**
- `app/FileStorage/Request/ImageRequest.php` — typ obrázku `ImageEntity`; `fromMacro()`/`crop()` už nemají
  implicitně nullable parametr (null skončil `TypeError` v konstruktoru i dřív), typ pole `$args`
- `tests/FileStorage/ImageRequestTest.phpt` — `ImageRequest` s `ImageEntity`, makra nad vlastním úložištěm
- `tests/FileStorage/FlysystemStorageTest.phpt` — obrázek bez hashe úložiště správce souborů (`HashNamingScheme`) odmítne
- `docs/Architecture/file-storage.md` — sekce „Vlastní úložiště pro plugin“

**Rozhodnutí a kompromisy:**
Navazuje [2026-10-07-storage-naming-schemes.md](2026-10-07-storage-naming-schemes.md): `FlysystemStorage` už hash
nepotřebuje, cesty skládá schéma názvů úložiště. Obrázek bez hashe odmítne jen `HashNamingScheme` správce souborů
(`LogicException`), plugin s vlastními názvy souborů nepíše vlastní `IStorage`, ale schéma názvů.

**Co musí udělat projekt při mergi jádra:**
- Vlastní třídy dědící z `ImageRequest` nebo volající `$request->getFile()->getHash()`: `getFile()` vrací
  `ImageEntity`, hash ověřte přes `instanceof HashFile`.
- Entity pluginů, které dědily z `HashImageEntity` jen kvůli `ImageRequest`, převeďte na `extends ImageEntity`
  a odstraňte přepsané `getId()`/`getHash()`.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `file-storage.md`
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne
