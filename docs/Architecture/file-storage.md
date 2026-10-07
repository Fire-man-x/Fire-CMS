# Úložiště souborů — lokální disk nebo S3, náhledy obrázků

Správce souborů (`firecms_files`, administrace > Správce souborů, obrázky článků/stránek/menu/sliderů) ukládá přes
`App\FileStorage\Storages\FlysystemStorage` nad knihovnou [Flysystem](https://flysystem.thephpleague.com/).
Kam se ukládá, určuje neon: výchozí je lokální disk `www/files` (URL `/files/`), projekt může přepnout na
AWS S3 nebo úložiště kompatibilní s S3 (Garage, MinIO, Cloudflare R2, …). Kód se tím nemění.

Kód je v `app/FileStorage/` (namespace `App\FileStorage`, do 2026-10 `app/Components/FileManager/`). Registruje ho DI
rozšíření `fileStorage:` (`App\FileStorage\DI\Extension`, v `app/config/config.neon`, do 2026-10 `fileManager:`).

## Struktura klíčů

| Co | Klíč v úložišti | Veřejná URL |
|---|---|---|
| originál | `<h0>/<h1>/<hash>.<přípona>` | `publicUrl` + klíč |
| náhled | `cache/<h0>/<h1>/<hash>.<klíč náhledu>.<přípona>` | `publicUrl` + klíč |

`h0` = první znak SHA1 hashe (`firecms_files.diskName`), `h1` druhý, na disku tedy nejvýš 16 × 16 adresářů
(i statisíce souborů jsou pak jen ve stovkách na adresář, na výkonu disku to nepoznáte). Náhledy nemají vlastní
složku pro každý obrázek. Při smazání nebo otočení obrázku se vypíše `cache/<h0>/<h1>/` a smažou se soubory
začínající `<hash>.` (`FlysystemStorage::listThumbnails()`). Na S3 to je jeden výpis (ListObjectsV2, po
stránkách po 1000) a jedno DeleteObject za každý náhled. Mažou se tak i náhledy variant, které už nejsou
povolené, a náhledy ve formátu dřívějšího `HashFileStorage`.

## Konfigurace

Výchozí stav v `app/config/config.neon`:

```neon
fileStorage:
	storages:
		files:
			adapter: local
			root: %wwwDir%/files
			publicUrl: /files/
```

Projekt na S3 to přepíše v `theme.neon` a přístupové údaje dá do `config.local.neon` (na serveru), nikdy ne do
verzovaného neonu:

```neon
fileStorage:
	storages:
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

Další volby `fileStorage:` mimo `storages:`:
- `defaultStorage: files` — úložiště, do kterého ukládá správce souborů.
- `thumbnails:` — povolené náhledy obrázků (níže).
- `strictThumbnails: null` — nepovolený náhled vyhodí výjimku; `null` = jen v debug režimu.
- `keepMetadata: true` — originál si ponechá metadata JPEG (EXIF, XMP, IPTC) i po zmenšení a narovnání;
  `false` = zahodit. Barevný profil ICC zůstává vždy, náhledy metadata nemají.
- `stripGps: false` — `true` = odstranit z metadat originálu GPS polohu (EXIF i XMP), kde byla fotka pořízena.
  Ve výchozím stavu poloha zůstává; web originály zveřejňuje, u projektů, kam nahrávají fotky zákazníci
  (poloha fotky z domova je osobní údaj), zvažte `true`.

Zastaralé klíče `storageClass`, `imageEntity`, `basePath`, `storageDir`, `cacheDir` se ignorují a vyhodí
deprecation.

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
URL generovat libovolné rozměry.

```neon
fileStorage:
	thumbnails:
		resize: [100x100, 945x, x60, '300x200-f1']   # {image} / n:src / n:image; -f<N> = příznaky Image::resize()
		crop: [130x130]                                # {crop} / n:crop - zmenšení a ořez ze středu
```

Zápis odpovídá šablonám: `n:src="$file, '945x'"` → `945x`, `{crop $file, '130x130'}` → `crop: [130x130]`.
Jádro povoluje náhledy svých šablon v `app/config/config.neon`, projekt v `theme.neon`, plugin ve svém
`config.plugin.neon`. Neon seznamy z více souborů se sloučí.

Nepovolený náhled v šabloně:
- **v debug režimu** vyhodí `InvalidThumbnailException` s textem, co přidat do neonu,
- **na produkci** se zaloguje (`log/warning.log`, jednou za požadavek) a místo náhledu se vrátí originál.

Shortcode `[image id rozměr]` v obsahu (rozměr zadává redaktor) spadne na originál vždy, bez výjimky.

### Jak to funguje

1. `link()` (makra `{image}`, `n:src`, …) úložiště nekontroluje, protože na S3 by to byl HTTP požadavek za
   každý obrázek. O vygenerovaných náhledech vede evidenci v Nette Cache (`FileManager.thumbnails.<úložiště>.<verze>`,
   platnost 30 dní; verze struktury klíčů `ThumbnailLayoutVersion` se zvyšuje při změně cest náhledů).
2. Náhled v evidenci → přímá veřejná URL náhledu.
3. Náhled není v evidenci → URL generátoru `/files/thumbnail/<hash>/<klíč náhledu>` (`FileRouter`,
   `Front:Files:thumbnail`). Generátor ověří, že je náhled povolený (jinak 404), náhled vytvoří (originál s EXIF
   orientací z doby před narovnáváním při uploadu narovná), uloží s `Cache-Control: public, max-age=86400`,
   zapíše do evidence a pošle (odpověď generátoru se necachuje). Pokud už v úložišti je, jen přesměruje na
   jeho veřejnou URL.

Náhled smazaný mimo aplikaci (ručně v bucketu) se znovu vytvoří až po vypršení evidence nebo po smazání
`temp/cache`. `FlysystemStorage::removeCache()` smaže náhledy i evidenci.

## Upload

`FlysystemStorage::upload()` (volá ho `MultiFileUploadModel` s `dimensions` z nastavení `imageResolution`):
- **narovná fotku podle EXIF orientace:** mobily ukládají snímek na ležato a otočení zapíšou jen do EXIF,
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

Stejný obsah nahraný podruhé dostane vlastní hash, aby smazání jednoho souboru nerozbilo druhý.

## Úpravy originálu

- `modifyOriginal($file, fn(Image $image) => ...)`: otočení v administraci.
- `fixOrientation($file)`: narovnání obrázků nahraných dřív.

Obě přepíšou originál (metadata zachovají stejně jako upload) a smažou jeho náhledy. Volající pak uloží novou
velikost souboru přes `Files::updateSize($id, $size)`. Adresa souboru se nemění, viz cache po otočení výše.

## Vlastní úložiště pro plugin

Každé úložiště z `fileStorage: storages:` je služba `fileStorage.filesystem.<název>`
(`League\Flysystem\Filesystem`, bez autowiringu):

```neon
fileStorage:
	storages:
		attachments:
			adapter: local
			root: %wwwDir%/upload/attachments
			publicUrl: /upload/attachments/

services:
	- App\Plugins\X\Service\Attachments(@fileStorage.filesystem.attachments)
```

Plugin, který dnes ukládá po svém na lokální disk (`FileStorage`, `move()`), na S3 nepoběží. Nový kód
používá vlastní úložiště z `fileStorage: storages:`.

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
	storages:
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
- `Storages\FileStorage` / `Storages\HashFileStorage` zůstávají jen kvůli pluginům, které z nich dědí. Jádro je neregistruje.
