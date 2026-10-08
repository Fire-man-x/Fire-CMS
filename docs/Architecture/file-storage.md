# Úložiště souborů — lokální disk nebo S3, schéma názvů, náhledy obrázků

Soubory se ukládají přes `App\FileStorage\Storages\FlysystemStorage` nad knihovnou
[Flysystem](https://flysystem.thephpleague.com/). Úložišť může být víc, každé je položka
`fileStorage: <název>:` v neonu (úložiště jsou přímo pod `fileStorage:` vedle jeho voleb) a skládá se ze tří
nezávislých částí:

| Část | Určuje | Nastavení |
|---|---|---|
| **kam** | lokální adresář nebo S3 bucket (AWS, Garage, MinIO, Cloudflare R2, …) | `adapter`, `root` / `bucket`, `publicUrl`, … |
| **jak se soubory jmenují** | klíč originálu a náhledů v úložišti, pojmenování nahraného souboru | `naming` (třída implementující `Naming\NamingScheme`, výchozí `HashNamingScheme`) |
| **náhledy obrázků** | které rozměry smí vzniknout | `thumbnails` |

Stejný soubor tak jde uložit na disk do jiného adresáře nebo na S3 jen změnou neonu, kód ani schéma názvů se
nemění. Správce souborů (`firecms_files`, administrace > Správce souborů, obrázky článků/stránek/menu/sliderů)
je úložiště `files` se schématem `HashNamingScheme`. Výchozí je lokální disk `www/files` (URL `/files/`).

Kód je v `app/FileStorage/` (namespace `App\FileStorage`, do 2026-10 `app/Components/FileManager/`). Registruje ho DI
rozšíření `fileStorage:` (`App\FileStorage\DI\Extension`, v `app/config/config.neon`, do 2026-10 `fileManager:`).

## Služby

Pro každé úložiště `<název>`:
- `fileStorage.storage.<název>` — `FlysystemStorage` (upload, odkazy, náhledy, mazání),
- `fileStorage.filesystem.<název>` — samotný `League\Flysystem\Filesystem`,
- `fileStorage.naming.<název>`, `fileStorage.allowedThumbnails.<název>`.

Autowiring má jen úložiště `defaultStorage` (správce souborů), ostatní se předávají jménem
(`@fileStorage.storage.<název>`). Dále `fileStorage.fileManager` (`FileManager` nad `defaultStorage`, proměnná
`$__imagestore` maker v šablonách) a `fileStorage.storages` (`StorageRegistry` — úložiště podle názvu pro
generátor náhledů).

## Struktura klíčů správce souborů (`HashNamingScheme`)

| Co | Klíč v úložišti | Veřejná URL |
|---|---|---|
| originál | `<h0>/<h1>/<hash>.<přípona>` | `publicUrl` + klíč |
| náhled | `cache/<h0>/<h1>/<hash>.<klíč náhledu>.<přípona>` | `publicUrl` + klíč |

`h0` = první znak SHA1 hashe (`firecms_files.diskName`), `h1` druhý, na disku tedy nejvýš 16 × 16 adresářů
(i statisíce souborů jsou pak jen ve stovkách na adresář, na výkonu disku to nepoznáte). Náhledy nemají vlastní
složku pro každý obrázek. Při smazání nebo otočení obrázku se vypíše `cache/<h0>/<h1>/` a smažou se soubory
začínající `<hash>.` (`HashNamingScheme::listThumbnails()`). Na S3 to je jeden výpis (ListObjectsV2, po
stránkách po 1000) a jedno DeleteObject za každý náhled. Mažou se tak i náhledy variant, které už nejsou
povolené, a náhledy ve formátu dřívějšího `HashFileStorage`.

Veřejná URL je `publicUrl` + klíč, části klíče se kódují (`EncodedPublicUrlGenerator`, mezery a diakritika
v názvech souborů jiných schémat). Hashové klíče zůstanou beze změny.

## Konfigurace

Výchozí stav v `app/config/config.neon`:

```neon
fileStorage:
	files:
		adapter: local
		root: %wwwDir%/files
		publicUrl: /files/
```

Projekt na S3 to přepíše v `theme.neon` a přístupové údaje dá do `config.local.neon` (na serveru), nikdy ne do
verzovaného neonu:

```neon
fileStorage:
	files:
		adapter: s3
		bucket: projekt-files
		region: eu-central-1
		prefix: files                                # volitelně, klíče pak jsou files/<h0>/...
		publicUrl: https://cdn.projekt.cz/files/     # URL CDN nebo bucketu VČETNĚ prefixu
		key: AKIA...                                 # bez key/secret: proměnné AWS_* nebo IAM role serveru
		secret: ...
```

| Volba | Výchozí | Význam |
|---|---|---|
| `adapter` | `local` | `local` nebo `s3` |
| `root` | — | lokální: adresář se soubory |
| `publicUrl` | — | začátek veřejné URL souborů; u lokálního typicky `/files/`, u S3 CDN/bucket **včetně `prefix`** |
| `bucket`, `region`, `prefix` | —, `eu-central-1`, `''` | S3 bucket |
| `endpoint`, `pathStyleEndpoint` | `null`, `false` | úložiště kompatibilní s S3 (Garage: `http://localhost:3900`, `true`) |
| `key`, `secret` | `null` | přístupový klíč |
| `acl` | `bucket-owner-full-control` | ACL posílané s každým zápisem, viz níže |
| `checksums` | `when_supported` | `when_required`, když S3 kompatibilní úložiště nezná nové kontrolní součty AWS SDK |
| `naming` | `App\FileStorage\Naming\HashNamingScheme` | schéma názvů: třída nebo služba (`Trida(%parametr%)`) implementující `NamingScheme` |
| `thumbnails` | prázdný | povolené náhledy úložiště (`resize`, `crop`, `aliases`), viz níže |
| `keepMetadata`, `stripGps` | `null` | přepíše stejnojmennou volbu `fileStorage:` pro toto úložiště |
| `directThumbnails` | `false` | odkazy přímo na náhledy, chybějící náhled vytvoří aplikace na jeho adrese; jen `adapter: local` a `publicUrl` jako cesta na tomto webu, viz níže |

Název úložiště smí obsahovat jen písmena bez diakritiky, číslice, `_` a `-` (je v URL generátoru náhledů).

Volby `fileStorage:`, které nejsou úložiště (úložiště se proto nesmí jmenovat stejně):
- `defaultStorage: files` — úložiště, do kterého ukládá správce souborů.
- `macros` — Latte makra (výchozí `App\FileStorage\Macro\ImageMacro`).
- `strictThumbnails: null` — nepovolený náhled vyhodí výjimku; `null` = jen v debug režimu.
- `keepMetadata: true` — originál si ponechá metadata JPEG (EXIF, XMP, IPTC) i po zmenšení a narovnání;
  `false` = zahodit. Barevný profil ICC zůstává vždy, náhledy metadata nemají.
- `stripGps: false` — `true` = odstranit z metadat originálu GPS polohu (EXIF i XMP), kde byla fotka pořízena.
  Ve výchozím stavu poloha zůstává; web originály zveřejňuje, u projektů, kam nahrávají fotky zákazníci
  (poloha fotky z domova je osobní údaj), zvažte `true`.

Zastaralé klíče `storageClass`, `imageEntity`, `basePath`, `storageDir`, `cacheDir` se ignorují a vyhodí
deprecation. Globální `fileStorage: thumbnails:` (do 2026-10) shodí start aplikace s odkazem na
`fileStorage: <název>: thumbnails:` — tiché ignorování by v produkci vedlo na originály místo náhledů.
Stejně tak `fileStorage: storages:` (úložiště byla krátce o úroveň níž).

### Přepnutí na S3 — co zařídit mimo kód

- **Veřejné čtení:** URL z `publicUrl` musí jít otevřít bez přihlášení. U AWS doporučujeme bucket s Block Public
  Access + CloudFront (OAC), případně politiku bucketu s veřejným `s3:GetObject`. Soubory neprocházejí přes PHP
  (kromě prvního vygenerování náhledu a stahování přes `Front:Files:default`).
- **ACL:** nové AWS buckety mají ACL vypnuté (Object Ownership „Bucket owner enforced“) a zápis s ACL jiným
  než `bucket-owner-full-control` odmítnou (`AccessControlListNotSupported`). Proto je to výchozí hodnota.
  Úložiště, která veřejnost řídí přes ACL objektů, si nastaví `acl: public-read`.
- **IAM:** klíč potřebuje `s3:GetObject`, `s3:PutObject`, `s3:DeleteObject` a `s3:ListBucket` (výpis
  náhledů při mazání) na bucketu, ideálně omezené na `prefix`.
- **Cache po otočení obrázku:** originál i náhledy se přepíšou pod stejným klíčem, URL se nemění. Náhledy
  mají v S3 `Cache-Control: public, max-age=86400`, takže návštěvník (a CDN) uvidí otočenou verzi
  nejpozději za den. Administrace ji ukáže hned po překreslení (odkaz vede na generátor, který se necachuje),
  po obnovení stránky případně až po vynuceném obnovení (Ctrl+F5).

## Náhledy obrázků na vyžádání

Náhled vznikne až při prvním zobrazení a jen když je **povolený**. Bez seznamu by si kdokoliv mohl změnou
URL generovat libovolné rozměry. Seznam má každé úložiště vlastní:

```neon
fileStorage:
	files:
		thumbnails:
			resize: [100x100, 945x, x60, '300x200-f1']   # {image} / n:src / n:image; -f<N> = příznaky Image::resize()
			crop: [130x130]                                # {crop} / n:crop - zmenšení a ořez ze středu
			aliases:
				smallest: x60                              # pojmenované rozměry ze šablon: n:src="$file, 'smallest'"
```

Zápis odpovídá šablonám: `n:src="$file, '945x'"` → `945x`, `{crop $file, '130x130'}` → `crop: [130x130]`.
Alias jen pojmenuje rozměry, makro dál určuje zmenšení/ořez a příznaky; výsledný náhled musí být povolený
v `resize`/`crop`. Jádro povoluje náhledy svých šablon v `app/config/config.neon`, projekt v `theme.neon`,
plugin ve svém `config.plugin.neon`. Neon seznamy z více souborů se sloučí. Chybný zápis hlásí už sestavení
kontejneru (s názvem úložiště).

Nepovolený náhled v šabloně:
- **v debug režimu** vyhodí `InvalidThumbnailException` s textem, co přidat do neonu,
- **na produkci** se zaloguje (`log/warning.log`, jednou za požadavek) a místo náhledu se vrátí originál.

Shortcode `[image id rozměr]` v obsahu (rozměr zadává redaktor) spadne na originál vždy, bez výjimky.

### Jak to funguje

Dva režimy podle volby úložiště `directThumbnails`.

**Výchozí (generátor, funguje i na S3):**

1. `link()` (makra `{image}`, `n:src`, …) úložiště nekontroluje, protože na S3 by to byl HTTP požadavek za
   každý obrázek. O vygenerovaných náhledech vede evidenci v Nette Cache podle klíče originálu
   (`FileStorage.thumbnails.<úložiště>.<verze>`, platnost 30 dní; `RegistryVersion` se zvyšuje, když se
   změní, kde náhledy leží).
2. Náhled v evidenci → přímá veřejná URL náhledu.
3. Náhled není v evidenci → URL generátoru `/files/thumbnail/<úložiště>/<klíč originálu>/<klíč náhledu>`
   (`FileRouter`, `Front:Files:thumbnail`). Klíč náhledu je na konci, aby URL nekončila příponou obrázku
   (`.htaccess` by ji jinak nepustil do `index.php`). Generátor najde úložiště podle názvu (`StorageRegistry`,
   jen úložiště z neonu), ověří, že je cesta originálem podle schématu názvů (`NamingScheme::isOriginalPath()`)
   a náhled povolený (jinak 404), náhled vytvoří (originál s EXIF orientací z doby před narovnáváním při
   uploadu narovná), uloží s `Cache-Control: public, max-age=86400`, zapíše do evidence a pošle (odpověď
   generátoru se necachuje). Pokud už v úložišti je, jen přesměruje na jeho veřejnou URL. Databázi
   nepotřebuje, funguje i pro soubory, které v žádné tabulce nejsou.

Náhled smazaný mimo aplikaci (ručně v bucketu) se znovu vytvoří až po vypršení evidence nebo po smazání
`temp/cache`. `FlysystemStorage::removeCache()` smaže náhledy i evidenci.

**`directThumbnails: true` (lokální disk):**

1. `link()` vrací vždy přímou veřejnou URL náhledu, evidence se nepoužívá.
2. Náhled na disku je → pošle ho web server, aplikace o požadavku neví.
3. Náhled na disku není (ještě nevznikl nebo ho někdo smazal) → web server pošle požadavek do `index.php`,
   `FileRouter` má pro každé takové úložiště cestu `<publicUrl>/<path .+>` → `Front:Files:missingThumbnail`
   → `FlysystemStorage::thumbnailFromPath()`. Schéma názvů z klíče náhledu zjistí originál a klíč náhledu
   (`NamingScheme::parseThumbnailPath()`), úložiště ověří, že je náhled povolený, originál existuje a náhled
   leží přesně tam, kam ho schéma ukládá (`getThumbnailPath()`). Pak ho vytvoří, uloží a pošle (cachuje se
   jako statický soubor, 1 den). Cokoliv jiného pod `publicUrl` skončí 404.

Web server musí požadavky na neexistující soubory pod `publicUrl` posílat do `index.php`. `www/.htaccess`
jádra to dělá jen pro přípony mimo svůj seznam statických souborů, a ten rozlišuje velikost písmen:
chybějící `.JPG` do aplikace dojde, `.jpg` skončí 404 v Apache. Kořen úložiště proto potřebuje vlastní
`.htaccess` jako `www/files/.htaccess` (`RewriteCond !-f`, `!-d` → `index.php`). Router cesty těchto úložišť
dostává z konfigurace (`DirectThumbnailRoutes`), ne z úložišť: ta přes `LinkGenerator` závisí na routeru.

Po otočení obrázku (`modifyOriginal()`) zůstává přímá URL náhledu stejná, prohlížeč může den ukazovat starou
verzi i v administraci. Pro úložiště, kde se obrázky upravují, je proto vhodnější výchozí režim.

## Upload

`FlysystemStorage::upload()` (správci souborů ho volá `MultiFileUploadModel` s `dimensions` z nastavení
`imageResolution`). Entitu s názvem a klíčem připraví schéma názvů (`NamingScheme::createFile()`, dostane i ostatní
`$settings`, např. album). Obrázek, který schéma vrátí jako `ImageEntity`, úložiště:
- **narovná podle EXIF orientace:** mobily ukládají snímek na ležato a otočení zapíšou jen do EXIF,
  GD ho ignoruje a zmenšený obrázek by ležel na boku,
- zmenší obrázek větší než `dimensions`,
- JPEG/WebP přeuloží v kvalitě 90.

**Metadata originálu** (`App\FileStorage\Images\JpegMetadata`): GD metadata nezapisuje, proto se
EXIF, XMP, IPTC a barevný profil ICC z nahraného souboru přenesou do přeuloženého obrázku. V EXIF i XMP se
nastaví orientace 1 (obrázek je už narovnaný, prohlížeč by ho jinak otočil podruhé) a nové rozměry, zahodí
se vložený náhled EXIF (zůstal by v původní orientaci). GPS poloha zůstává, s `stripGps: true` se odstraní (v EXIF
se vynuluje i v bajtech souboru). Poškozené EXIF se zahodí celé. Jiné segmenty (MPF, C2PA) se nepřenášejí.

Obrázek, který narovnávat ani zmenšovat nebylo potřeba, se uloží tak, jak byl nahraný. S `stripGps: true` se mu
bezztrátově odstraní GPS z hlavičky souboru, obrazová data se nepřeukládají.

Ve správci souborů (`HashNamingScheme`) dostane stejný obsah nahraný podruhé vlastní hash, aby smazání jednoho
souboru nerozbilo druhý. Schéma, které klíč nezmění (např. název souboru v albu), soubor přepíše a úložiště
smaže jeho staré náhledy.

## Úpravy originálu

- `modifyOriginal($file, fn(Image $image) => ...)`: otočení v administraci.
- `fixOrientation($file)`: narovnání obrázků nahraných dřív.

Obě přepíšou originál (metadata zachovají stejně jako upload) a smažou jeho náhledy. Správce souborů pak uloží
novou velikost souboru přes `Files::updateSize($id, $size)`. Adresa souboru se nemění, viz cache po otočení výše.

## Vlastní úložiště pro plugin

Plugin nepíše vlastní úložiště, jen přidá položku pod `fileStorage:`. Pokud mu nevyhovují hashové
názvy (soubory mimo `firecms_files`, alba jako adresáře, názvy podle data…), dodá i schéma názvů:

```neon
fileStorage:
	gallery:
		adapter: local                  # nebo s3 - schéma ani kód pluginu se nemění
		root: %wwwDir%/foto
		publicUrl: /foto/
		naming: App\Plugins\Gallery\GalleryNamingScheme
		thumbnails:
			resize: [x50, x192]
			aliases:
				smallest: x192

services:
	- App\Plugins\Gallery\Albums(@fileStorage.storage.gallery)
```

Schéma názvů (`implements App\FileStorage\Naming\NamingScheme`):
- `getOriginalPath(File)` — klíč originálu z vlastní entity (`extends ImageEntity` / `FileEntity` s údaji,
  podle kterých se soubor najde, např. album a název). Id ani hash entita mít nemusí.
- `getThumbnailPath($originalPath, Thumbnail)` a `listThumbnails($originalPath, ...)` — náhledy se odvozují
  z klíče originálu, protože generátor dostane z URL jen ten. Náhledy můžou ležet kdekoliv (např.
  `<album>/mini/<název>`), i pod názvy existujících souborů.
- `isOriginalPath($path)` — **musí odmítnout všechno, co originál není** (náhledy, `..`, jiný počet úrovní):
  generátor podle ní pozná, z čeho smí náhled vyrobit.
- `parseThumbnailPath($path)` — opak `getThumbnailPath()` pro `directThumbnails`: kandidáti [klíč originálu,
  klíč náhledu]. Víc kandidátů, když název není jednoznačný (`mini/DSC_x192.JPG` může být náhled `x192` fotky
  `DSC.JPG` i náhled `x50` fotky `DSC_x192.JPG`); úložiště použije prvního s existujícím originálem.
- `createFile($upload, $image, $settings, $exists)` — entita nahrávaného souboru (název, přípona, údaje klíče
  ze `$settings`). Soubor, který do úložiště nepatří, odmítne výjimkou.

Do šablony předá plugin `$__imagestore` jako `new FileManager($storage)`, makra pak fungují stejně jako
u správce souborů. Soubory, které plugin načítá výpisem (alba), čte přes `$storage->getFilesystem()->listContents()`,
ne přes `scandir()` — jen tak poběží i na S3. Vzorové schéma s alby je v `tests/FileStorage/CustomNamingSchemeTest.phpt`.

Samotný `League\Flysystem\Filesystem` úložiště (`@fileStorage.filesystem.<název>`) stačí pluginu, který
s náhledy ani schématem názvů nepracuje. Plugin, který dnes ukládá po svém na lokální disk (`move()`), na S3
nepoběží.

## Převod existujících souborů — `bin/console files:migrate`

Dřívější implementace ukládaly jinak: nejstarší data jako `<h01>/<h23>/<hash>.<ext>` (dva znaky hashe na
úroveň), `HashFileStorage` (do 2026-09) jako `<h0>/<h1>/<původní název>.<ext>`. Příkaz projde `firecms_files`,
najde soubor ve zdrojovém adresáři a zkopíruje ho do úložiště `defaultStorage` v nové struktuře:

```
bin/console files:migrate --dry-run -v                 # jen vypíše, co by udělal
bin/console files:migrate --delete-source              # lokální disk: přesun do nové struktury
bin/console files:migrate --source=/cesta/www/files    # po přepnutí na S3: nahraje soubory do bucketu
```

Příkaz je opakovatelný, co už v cíli je, přeskočí. Staré náhledy v `www/files/cache/` se nepřevádějí, po
ověření smažte celý adresář `cache/` (nové náhledy se ve stejném místě vytvoří znovu).

Pořadí nasazení: smazat `temp/cache` → `files:migrate` → doplnit náhledy šablon projektu do `theme.neon` /
pluginů. Migrace DB není potřeba.

## Lokální vývoj s Garage (S3)

`docker/docker-compose.yml` spouští Garage (S3 API `:3900`, web `:3902`). V `garage.toml` musí být
`s3_api = { api_bind_addr = "[::]:3900", ... }`. S klíčem `bind_addr` se S3 API vůbec nespustí.
Veřejné URL (`publicUrl`) servíruje webový endpoint `:3902` jen bucketu s povoleným webovým přístupem
(`docker exec garage_s3 /garage bucket website --allow muj-bucket`). Bez něj vrací 404, i když objekt v bucketu je.

```neon
# config.local.neon
fileStorage:
	files:
		adapter: s3
		bucket: muj-bucket
		endpoint: http://localhost:3900
		pathStyleEndpoint: true
		key: GK...
		secret: ...
		publicUrl: http://muj-bucket.localhost:3902/   # po `garage bucket website --allow muj-bucket`
```

Test proti S3 (bez proměnných se přeskočí):
`FILEMANAGER_S3_BUCKET=muj-bucket FILEMANAGER_S3_ENDPOINT=http://localhost:3900 FILEMANAGER_S3_KEY=GK… FILEMANAGER_S3_SECRET=… composer test -- tests/FileStorage/S3StorageTest.phpt`

## Omezení

- Stahování (`Front:Files:default`) posílá soubor přes PHP (`StreamResponse`), bez HTTP Range, kvůli počítadlu
  stažení a původnímu názvu souboru.
