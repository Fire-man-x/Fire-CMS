# 2026-10-07 — Přímé odkazy na náhledy a jejich vytvoření na vlastní adrese (`directThumbnails`)

**Co:** Úložiště na lokálním disku může mít `directThumbnails: true`: `link()` vrací vždy přímou URL náhledu
a chybějící náhled (ještě nevzniklý nebo smazaný) vytvoří aplikace, když na jeho adresu přijde požadavek.
Existující náhledy posílá web server, aplikace o nich neví.

**Proč:** Ve výchozím režimu vede evidence vygenerovaných náhledů v Nette Cache. Náhled smazaný mimo aplikaci
(ručně z disku) tak evidence dál považovala za existující, odkaz vedl na chybějící soubor a nový náhled
nevznikl až do vypršení evidence (30 dní). U lokálního disku evidence není potřeba: o existenci rozhodne web
server a chybějící soubor pošle do aplikace.

**Dotčené soubory/oblasti:**
- `app/FileStorage/Naming/NamingScheme.php` — nová metoda `parseThumbnailPath()` (opak `getThumbnailPath()`,
  kandidáti [klíč originálu, klíč náhledu]); `HashNamingScheme` ji implementuje
- `app/FileStorage/Storages/FlysystemStorage.php` — parametr `directThumbnails`; `thumbnailFromPath()` (náhled
  podle jeho klíče, se zpětnou kontrolou přes `getThumbnailPath()`, existující náhled pošle místo přesměrování
  na sebe); vytváření náhledu v `createThumbnail()` sdílí s generátorem
- `app/FileStorage/DirectThumbnailRoutes.php` (nové) — cesty `publicUrl` úložišť s `directThumbnails` pro router
- `app/FileStorage/DI/Extension.php` — volba úložiště `directThumbnails` (jen `adapter: local` a `publicUrl`
  jako cesta na tomto webu, jinak chyba při sestavení kontejneru), služba `fileStorage.directThumbnailRoutes`
- `app/Router/FileRouter.php` — cesta `<publicUrl>/<path .+>` pro každé takové úložiště
- `app/FrontModule/Presenters/FilesPresenter.php` — `actionMissingThumbnail()`
- `tests/FileStorage/CustomNamingSchemeTest.phpt`, `FlysystemStorageTest.phpt` — náhled na vlastní adrese,
  nejednoznačný název, smazaný náhled, odmítnuté cesty
- `docs/Architecture/file-storage.md`, `routing.md`, `docs/AI-Context/gotchas.md`

**Rozhodnutí a kompromisy:**
- Volba je opt-in, ne automaticky pro každé lokální úložiště. Bez nastaveného web serveru (chybějící soubory
  do `index.php`) by přímé odkazy na ještě nevzniklé náhledy vedly na 404 a náhledy by nevznikly nikdy.
  Výchozí režim funguje bez nastavení web serveru i na S3.
- Správce souborů (`files`) zůstává ve výchozím režimu: projekty, které ho přepnou na S3, by jinak musely
  volbu vypínat, a po otočení obrázku v administraci by přímá URL náhledu den ukazovala starou verzi
  z cache prohlížeče (generátor se necachuje).
- Router dostává cesty z konfigurace (`DirectThumbnailRoutes`), ne z úložišť: úložiště přes `LinkGenerator`
  závisí na routeru a DI by skončilo na kruhové závislosti.
- Klíč náhledu z URL se nikdy nepoužije přímo: úložiště ho přijme, jen když ho schéma názvů z kandidáta
  vytvoří znovu stejný. Zápis mimo místo náhledu tak není možný.

**Co musí udělat projekt při mergi jádra:**
- Vlastní třídy implementující `NamingScheme` doplní `parseThumbnailPath()` (stačí vracet `[]`, pokud
  úložiště `directThumbnails` nemá).
- Úložiště s `directThumbnails` potřebuje v kořeni `.htaccess` jako `www/files/.htaccess` (jinak chybějící
  náhled s příponou malými písmeny, např. `.jpg`, skončí 404 v Apache).

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `file-storage.md`, `routing.md`
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — `gotchas.md`
- DB migrace potřeba? ne
