<?php
declare(strict_types=1);

namespace App\FileStorage\Naming;

use App\FileStorage\Files\File;
use App\FileStorage\Thumbnails\Thumbnail;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;
use Nette\Http\FileUpload;

/**
 * Schéma názvů úložiště: kde v úložišti leží originál souboru a jeho náhledy a jak se pojmenuje nahraný soubor.
 *
 * Úložiště (FlysystemStorage) určuje, kam se soubory ukládají (lokální adresář, S3), schéma jen jak se v něm
 * jmenují. Stejné schéma tak funguje na disku i na S3 a jedno úložiště jde přepnout na jiné jen konfigurací.
 * Nastavuje se pro každé úložiště zvlášť: `fileStorage: <název>: naming:`, výchozí je HashNamingScheme.
 *
 * Náhledy se odvozují z klíče originálu, ne z entity: generátor náhledů (Front:Files:thumbnail) dostane
 * z URL jen název úložiště a klíč originálu.
 */
interface NamingScheme
{
	/**
	 * Klíč originálu v úložišti, např. `a/b/<hash>.jpg`.
	 *
	 * @throws \LogicException entita, se kterou schéma neumí pracovat (chybí údaje, ze kterých se klíč skládá)
	 */
	public function getOriginalPath(File $file): string;


	/**
	 * Je klíč originálem v tomto schématu? Generátor náhledů tak odmítne cestu z URL, která originál není
	 * (náhled, cizí soubor v úložišti).
	 */
	public function isOriginalPath(string $path): bool;


	/**
	 * Klíč náhledu originálu.
	 *
	 * @throws \InvalidArgumentException $originalPath není originál (isOriginalPath())
	 */
	public function getThumbnailPath(string $originalPath, Thumbnail $thumbnail): string;


	/**
	 * Opak getThumbnailPath(): z klíče náhledu originál a klíč náhledu - pro náhled vyžádaný přímo na jeho adrese
	 * (`directThumbnails`, viz FlysystemStorage::thumbnailFromPath()). Kandidátů může být víc, když název
	 * náhledu není jednoznačný; úložiště použije první, jehož originál existuje a náhled je povolený. Každého
	 * kandidáta úložiště ověří zpětně přes getThumbnailPath(), schéma tak nemusí nic dalšího kontrolovat.
	 *
	 * @return list<array{string, string}> [klíč originálu, klíč náhledu], prázdné pole = není to náhled
	 */
	public function parseThumbnailPath(string $path): array;


	/**
	 * Klíče všech uložených náhledů originálu, i náhledů, které už nejsou povolené - pro jejich smazání.
	 *
	 * @return list<string>
	 * @throws \InvalidArgumentException $originalPath není originál (isOriginalPath())
	 * @throws FilesystemException
	 */
	public function listThumbnails(string $originalPath, FilesystemReader $filesystem): array;


	/**
	 * Entita nahrávaného souboru: název, přípona a údaje, ze kterých se skládá klíč (hash, album…). MIME typ,
	 * rozměry obrázku a velikost doplní úložiště. Obrázek, který se má narovnat a zmenšit, vrací schéma jako
	 * ImageEntity.
	 *
	 * Obsazený klíč ($exists) může schéma obejít (např. jiný hash), nebo ho nechat - úložiště pak soubor přepíše
	 * a smaže jeho staré náhledy.
	 *
	 * @param bool $image nahraný soubor je obrázek
	 * @param array<string, mixed> $settings nastavení uploadu (FileManager::upload()), např. album
	 * @param callable(string): bool $exists je klíč v úložišti obsazený?
	 * @throws \InvalidArgumentException soubor do úložiště nepatří (např. jiný než obrázek do galerie)
	 */
	public function createFile(FileUpload $upload, bool $image, array $settings, callable $exists): File;
}
