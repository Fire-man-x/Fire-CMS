<?php
declare(strict_types=1);

namespace App\FileStorage\Naming;

use App\FileStorage\Exceptions\HashException;
use App\FileStorage\Files\File;
use App\FileStorage\Files\HashFile;
use App\FileStorage\Files\HashFileEntity;
use App\FileStorage\Files\HashImageEntity;
use App\FileStorage\Thumbnails\Thumbnail;
use League\Flysystem\FilesystemReader;
use Nette\Http\FileUpload;

/**
 * Schéma správce souborů: soubory podle SHA1 hashe (h0 = první znak hashe, h1 druhý - na disku nejvýš
 * 16 × 16 adresářů).
 *
 * - originál: `<h0>/<h1>/<hash>.<přípona>`
 * - náhledy: `cache/<h0>/<h1>/<hash>.<klíč náhledu>.<přípona>` - bez vlastní složky pro každý obrázek;
 *   náhledy souboru se hledají výpisem `cache/<h0>/<h1>/` podle předpony `<hash>.` (listThumbnails())
 *
 * Pracuje se soubory s hashem (HashFileEntity, HashImageEntity), id a další údaje vede model Files v DB.
 */
final class HashNamingScheme implements NamingScheme
{
	public const string CacheDirectory = 'cache';

	/** `<h0>/<h1>/<hash>[.<přípona>]` - adresáře musí odpovídat prvním znakům hashe */
	private const string OriginalPattern = '~^([0-9a-f])/([0-9a-f])/(\1\2[0-9a-f]{38})(?:\.([^/.]+))?$~D';

	/** `cache/<h0>/<h1>/<hash>.<klíč náhledu>[.<přípona>]` - klíč náhledu ani přípona tečku neobsahují */
	private const string ThumbnailPattern = '~^' . self::CacheDirectory . '/([0-9a-f])/([0-9a-f])/(\1\2[0-9a-f]{38})\.([^/.]+)(?:\.([^/.]+))?$~D';


	/**
	 * @throws \LogicException soubor bez hashe
	 */
	public function getOriginalPath(File $file): string
	{
		$hash = self::toHashFile($file)->getHash();

		return self::hashDirectory($hash) . '/' . self::withExtension($hash, $file->getExtension());
	}


	public function isOriginalPath(string $path): bool
	{
		return preg_match(self::OriginalPattern, $path) === 1;
	}


	public function getThumbnailPath(string $originalPath, Thumbnail $thumbnail): string
	{
		[$hash, $extension] = self::parse($originalPath);

		return self::thumbnailDirectory($hash) . '/' . self::withExtension($hash . '.' . $thumbnail->getKey(), $extension);
	}


	public function parseThumbnailPath(string $path): array
	{
		if (!preg_match(self::ThumbnailPattern, $path, $matches)) {
			return [];
		}

		$hash = $matches[3];
		$extension = $matches[5] ?? '';

		return [[self::hashDirectory($hash) . '/' . self::withExtension($hash, $extension), $matches[4]]];
	}


	/**
	 * Vypíše se sdílená složka `cache/<h0>/<h1>/` a vyberou soubory začínající `<hash>.` - klíč náhledu tečku
	 * neobsahuje a hash má pevnou délku, takže se nemůže splést s jiným souborem.
	 */
	public function listThumbnails(string $originalPath, FilesystemReader $filesystem): array
	{
		[$hash] = self::parse($originalPath);
		$directory = self::thumbnailDirectory($hash);
		$prefix = $directory . '/' . $hash . '.';
		$paths = [];
		foreach ($filesystem->listContents($directory, false) as $item) {
			if ($item->isFile() && str_starts_with($item->path(), $prefix)) {
				$paths[] = $item->path();
			}
		}

		return $paths;
	}


	/**
	 * Hash obsahu nahraného souboru. Stejný obsah už může být nahraný - každý upload dostane vlastní soubor
	 * (náhodný hash), aby smazání jednoho nerozbilo druhý.
	 *
	 * @throws HashException
	 */
	public function createFile(FileUpload $upload, bool $image, array $settings, callable $exists): HashFileEntity|HashImageEntity
	{
		$file = $image ? new HashImageEntity() : new HashFileEntity();
		$untrustedName = $upload->getUntrustedName();
		$extension = strtolower(pathinfo($untrustedName, PATHINFO_EXTENSION));
		$file->setName(pathinfo($untrustedName, PATHINFO_FILENAME));
		$file->setExtension($extension === 'jpeg' ? 'jpg' : $extension);

		$temporaryFile = $upload->getTemporaryFile();
		$file->setHash(sha1_file($temporaryFile) ?: throw new \RuntimeException("Nelze přečíst nahraný soubor '$temporaryFile'."));
		while ($exists($this->getOriginalPath($file))) {
			$file->setHash(sha1(random_bytes(20)));
		}

		return $file;
	}


	/**
	 * @return array{string, string} hash a přípona originálu
	 */
	private static function parse(string $originalPath): array
	{
		if (!preg_match(self::OriginalPattern, $originalPath, $matches)) {
			throw new \InvalidArgumentException(sprintf("'%s' není originál správce souborů (<h0>/<h1>/<hash>.<přípona>).", $originalPath));
		}

		return [$matches[3], $matches[4] ?? ''];
	}


	private static function toHashFile(File $file): HashFile
	{
		if (!$file instanceof HashFile) {
			throw new \LogicException(sprintf('HashNamingScheme pracuje jen se soubory s hashem (%s), předán %s.', HashFile::class, $file::class));
		}

		return $file;
	}


	private static function hashDirectory(string $hash): string
	{
		return $hash[0] . '/' . $hash[1];
	}


	private static function thumbnailDirectory(string $hash): string
	{
		return self::CacheDirectory . '/' . self::hashDirectory($hash);
	}


	private static function withExtension(string $name, string $extension): string
	{
		return $extension === '' ? $name : $name . '.' . $extension;
	}
}
