<?php
declare(strict_types=1);

namespace App\FileStorage\Storages;

use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Exceptions\UploaderException;
use App\FileStorage\Files\File;
use App\FileStorage\Files\HashFile;
use App\FileStorage\Files\HashFileEntity;
use App\FileStorage\Files\HashImageEntity;
use App\FileStorage\Images\ExifOrientation;
use App\FileStorage\Images\JpegMetadata;
use App\FileStorage\Request\ImageRequest;
use App\FileStorage\Request\Request;
use App\FileStorage\Responses\StreamResponse;
use App\FileStorage\Thumbnails\AllowedThumbnails;
use App\FileStorage\Thumbnails\Thumbnail;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Nette\Application\LinkGenerator;
use Nette\Application\Response;
use Nette\Application\Responses\RedirectResponse;
use Nette\Caching\Cache;
use Nette\Caching\Storage as CacheStorage;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\ImageType;
use Tracy\ILogger;

/**
 * Úložiště souborů správce souborů nad Flysystemem - lokální disk i S3 podle `fileStorage: storages:` v neonu.
 *
 * Struktura klíčů (h0 = první znak SHA1 hashe, h1 druhý - na disku nejvýš 16 × 16 adresářů):
 * - originál: `<h0>/<h1>/<hash>.<přípona>`
 * - náhledy: `cache/<h0>/<h1>/<hash>.<klíč náhledu>.<přípona>` - bez vlastní složky pro každý obrázek;
 *   náhledy souboru se hledají výpisem `cache/<h0>/<h1>/` podle předpony `<hash>.` (listThumbnails())
 *
 * Náhledy vznikají až na vyžádání a jen povolené (AllowedThumbnails). link() kvůli S3 úložiště
 * nekontroluje (každá kontrola by byl HTTP požadavek), o vygenerovaných náhledech vede evidenci v Nette
 * Cache. Dokud náhled v evidenci není, vrací link() adresu generátoru (Front:Files:thumbnail), který
 * náhled vytvoří, uloží a pošle - další vykreslení už odkazuje přímo na veřejnou URL náhledu.
 */
final class FlysystemStorage implements IStorage
{
	public const string CacheDirectory = 'cache';

	/** Jak dlouho platí záznam o vygenerovaném náhledu - náhled smazaný mimo aplikaci se znovu vytvoří nejpozději po této době */
	private const string RegistryExpiration = '30 days';

	/**
	 * Verze struktury klíčů náhledů v názvu evidence - při změně getThumbnailPath() ji zvyšte, jinak by evidence
	 * odkazovala na náhledy na místech, kde už nejsou (2 = náhledy bez vlastní složky, `<hash>.<klíč>.<přípona>`).
	 */
	private const int ThumbnailLayoutVersion = 2;

	/**
	 * Cache náhledu v prohlížeči a CDN (S3). Po otočení obrázku zůstává adresa náhledu stejná, takže se nová
	 * verze u návštěvníka projeví nejpozději po této době.
	 */
	private const string ThumbnailCacheControl = 'public, max-age=86400';

	/**
	 * Odpověď generátoru náhledů se necachuje - po otočení obrázku vede odkaz znovu na stejnou adresu generátoru
	 * (administrace po překreslení tak hned ukáže otočený náhled).
	 */
	private const string GeneratorExpiration = '0';

	/** Kvalita JPEG/WebP při přeuložení originálu (narovnání, zmenšení, otočení) a u náhledů */
	private const int OriginalQuality = 90;
	private const int ThumbnailQuality = 75;

	private Cache $registry;

	/** @var array<string, array<string, true>> hash => vygenerované náhledy, načtené během tohoto požadavku */
	private array $known = [];

	/** @var array<string, true> nepovolené náhledy už zalogované v tomto požadavku */
	private array $loggedThumbnails = [];


	/**
	 * @param bool $strictThumbnails nepovolený náhled v šabloně vyhodí výjimku (vývoj), jinak se zaloguje a použije se originál
	 * @param bool $keepMetadata ponechat originálu JPEG metadata (EXIF, XMP, IPTC) i po zmenšení a narovnání;
	 *   false = zahodit (barevný profil ICC zůstává vždy). Náhledy metadata nemají.
	 * @param bool $stripGps odstranit z metadat originálu GPS polohu, kde byla fotka pořízena
	 * @param string $name název úložiště - odděluje evidenci náhledů více úložišť v Nette Cache
	 */
	public function __construct(
		private readonly FilesystemOperator $filesystem,
		private readonly AllowedThumbnails $allowedThumbnails,
		private readonly LinkGenerator $linkGenerator,
		CacheStorage $cacheStorage,
		private readonly bool $strictThumbnails = false,
		private readonly bool $keepMetadata = true,
		private readonly bool $stripGps = false,
		string $name = 'files',
		private readonly ?ILogger $logger = null,
	)
	{
		$this->registry = new Cache($cacheStorage, 'FileManager.thumbnails.' . $name . '.' . self::ThumbnailLayoutVersion);
	}


	public function getFilesystem(): FilesystemOperator
	{
		return $this->filesystem;
	}


	public function getAllowedThumbnails(): AllowedThumbnails
	{
		return $this->allowedThumbnails;
	}


	/**
	 * @throws FilesystemException
	 */
	public function exist(File $file): bool
	{
		return $this->filesystem->fileExists($this->getOriginalPath($this->toHashFile($file)));
	}


	/**
	 * @throws FilesystemException
	 */
	public function original(File $file): Image
	{
		return $this->loadImage($this->getOriginalPath($this->toHashFile($file)))[0];
	}


	/**
	 * Uloží nahraný soubor. Obrázek se narovná podle EXIF orientace a zmenší na `dimensions`, metadata JPEG
	 * se přenesou do přeuloženého obrázku (viz $keepMetadata, $stripGps).
	 *
	 * @param array<string, mixed> $settings `dimensions` => největší rozměr originálu, např. '1000x1000'
	 * @throws UploaderException
	 * @throws FilesystemException
	 */
	public function upload(FileUpload $upload, array $settings = []): HashFileEntity|HashImageEntity
	{
		if (!$upload->isOk()) {
			throw new UploaderException($upload->getError());
		}

		$temporaryFile = $upload->getTemporaryFile();
		$untrustedName = $upload->getUntrustedName();
		$extension = strtolower(pathinfo($untrustedName, PATHINFO_EXTENSION));
		$mimeType = $upload->getContentType() ?? 'application/octet-stream';
		$maxDimensions = $settings['dimensions'] ?? null;

		$contents = null;
		if ($upload->isImage()) {
			$file = new HashImageEntity();
			$file->setMimeType($mimeType);
			$contents = $this->prepareImage($temporaryFile, $file, is_string($maxDimensions) ? $maxDimensions : null);
		} else {
			$file = new HashFileEntity();
			$file->setMimeType($mimeType);
		}

		$file->setName(pathinfo($untrustedName, PATHINFO_FILENAME));
		$file->setExtension($extension === 'jpeg' ? 'jpg' : $extension);
		$file->setHash(sha1_file($temporaryFile) ?: throw new \RuntimeException("Nelze přečíst nahraný soubor '$temporaryFile'."));

		// stejný obsah už může být nahraný - každý upload dostane vlastní soubor, aby smazání jednoho nerozbilo druhý
		while ($this->filesystem->fileExists($this->getOriginalPath($file))) {
			$file->setHash(sha1(random_bytes(20)));
		}

		$path = $this->getOriginalPath($file);
		$config = ['mimetype' => $mimeType];
		if ($contents !== null) {
			$this->filesystem->write($path, $contents, $config);
			$file->setSize(strlen($contents));
		} else {
			$stream = fopen($temporaryFile, 'rb') ?: throw new \RuntimeException("Nelze otevřít nahraný soubor '$temporaryFile'.");
			try {
				$this->filesystem->writeStream($path, $stream, $config);
			} finally {
				if (is_resource($stream)) {
					fclose($stream);
				}
			}

			$file->setSize($upload->getSize());
		}

		@unlink($temporaryFile); // jako dřív FileUpload::move() - dočasný soubor už není potřeba, @ - PHP ho může smazat samo

		return $file;
	}


	/**
	 * Stažení souboru (originál s původním názvem), u ImageRequest s rozměry náhled.
	 *
	 * @throws FilesystemException soubor v úložišti chybí
	 * @throws InvalidThumbnailException nepovolený náhled
	 */
	public function download(Request $request): Response
	{
		$file = $this->toHashFile($request->getFile());
		if ($request instanceof ImageRequest && $request->getDimensions() !== Request::ORIGINAL) {
			return $this->thumbnail($request->getFile(), Thumbnail::fromRequest($request)->getKey());
		}

		$path = $this->getOriginalPath($file);
		$size = $this->filesystem->fileSize($path); // před readStream() - chybějící soubor tak skončí dřív, než se otevře stream

		return new StreamResponse(
			$this->filesystem->readStream($path),
			$file->getMimeType(),
			$file->getNameWithExtension(),
			$size,
		);
	}


	/**
	 * URL originálu, u ImageRequest s rozměry URL náhledu (nebo generátoru, dokud náhled neexistuje).
	 *
	 * @throws InvalidThumbnailException nepovolený náhled a $strictThumbnails
	 */
	public function link(Request $request): string
	{
		$file = $this->toHashFile($request->getFile());
		if ($request instanceof ImageRequest && $request->getDimensions() !== Request::ORIGINAL) {
			$thumbnail = $this->resolveThumbnail($request);
			if ($thumbnail !== null) {
				return $this->thumbnailLink($file, $thumbnail);
			}
		}

		return $this->filesystem->publicUrl($this->getOriginalPath($file));
	}


	/**
	 * Náhled pro generátor náhledů (Front:Files:thumbnail) - vytvoří ho, pokud ještě neexistuje.
	 *
	 * @throws InvalidThumbnailException nepovolený náhled
	 * @throws FilesystemException originál chybí nebo chyba úložiště
	 */
	public function thumbnail(HashImageEntity $file, string $key): Response
	{
		$thumbnail = $this->allowedThumbnails->get($key)
			?? throw new InvalidThumbnailException(sprintf("Náhled '%s' není povolený.", $key));

		$path = $this->getThumbnailPath($file, $thumbnail);
		if ($this->filesystem->fileExists($path)) {
			$this->rememberThumbnail($file, $thumbnail);
			return new RedirectResponse($this->filesystem->publicUrl($path));
		}

		[$image, $type, $original] = $this->loadImage($this->getOriginalPath($file));
		// originály nahrané dřív, než se fotky narovnávaly při uploadu, můžou mít EXIF orientaci - GD ji ignoruje
		ExifOrientation::apply($image, ExifOrientation::read($original));
		$thumbnail->apply($image);
		$contents = $image->toString($type, self::quality($type, self::ThumbnailQuality)); // GD metadata nezapisuje
		$mimeType = Image::typeToMimeType($type);

		$this->filesystem->write($path, $contents, ['mimetype' => $mimeType, 'CacheControl' => self::ThumbnailCacheControl]);
		$this->rememberThumbnail($file, $thumbnail);

		return new StreamResponse($contents, $mimeType, expiration: self::GeneratorExpiration);
	}


	/**
	 * Smaže originál i všechny jeho náhledy.
	 *
	 * @throws FilesystemException
	 */
	public function remove(File $file): void
	{
		$file = $this->toHashFile($file);
		$this->filesystem->delete($this->getOriginalPath($file));
		$this->removeCache($file);
	}


	/**
	 * Smaže všechny náhledy souboru (vytvoří se znovu při dalším zobrazení).
	 *
	 * @throws FilesystemException
	 */
	public function removeCache(File $file): void
	{
		$file = $this->toHashFile($file);
		foreach ($this->listThumbnails($file) as $path) {
			$this->filesystem->delete($path);
		}

		unset($this->known[$file->getHash()]);
		$this->registry->remove($file->getHash());
	}


	/**
	 * Upraví originál obrázku (např. otočení) a smaže jeho náhledy, metadata JPEG zachová. Vrací novou velikost
	 * souboru v bajtech - volající ji má uložit do DB (Files::updateSize()).
	 *
	 * @param callable(Image): void $modifier
	 * @throws FilesystemException
	 */
	public function modifyOriginal(HashImageEntity $file, callable $modifier): int
	{
		$path = $this->getOriginalPath($file);
		[$image, $type, $contents] = $this->loadImage($path);
		$modifier($image);

		return $this->saveOriginal($file, $path, $image, $type, $contents);
	}


	/**
	 * Narovná originál podle EXIF orientace - pro obrázky nahrané dřív, než se začaly narovnávat při uploadu.
	 * Vrací novou velikost souboru, null když obrázek narovnávat nebylo potřeba.
	 *
	 * @throws FilesystemException
	 */
	public function fixOrientation(HashImageEntity $file): ?int
	{
		$path = $this->getOriginalPath($file);
		[$image, $type, $contents] = $this->loadImage($path);
		$orientation = ExifOrientation::read($contents);
		if ($orientation === ExifOrientation::Normal) {
			return null;
		}

		ExifOrientation::apply($image, $orientation);

		return $this->saveOriginal($file, $path, $image, $type, $contents);
	}


	public function getOriginalPath(HashFile $file): string
	{
		return self::hashDirectory($file->getHash()) . '/' . self::withExtension($file->getHash(), $file->getExtension());
	}


	public function getThumbnailPath(HashFile $file, Thumbnail $thumbnail): string
	{
		return self::thumbnailDirectory($file) . '/'
			. self::withExtension($file->getHash() . '.' . $thumbnail->getKey(), $file->getExtension());
	}


	/**
	 * Cesty všech uložených náhledů souboru (i náhledů, které už nejsou povolené). Vypíše se sdílená složka
	 * `cache/<h0>/<h1>/` a vyberou soubory začínající `<hash>.` - klíč náhledu tečku neobsahuje a hash má
	 * pevnou délku, takže se nemůže splést s jiným souborem.
	 *
	 * @return list<string>
	 * @throws FilesystemException
	 */
	public function listThumbnails(HashFile $file): array
	{
		$prefix = self::thumbnailDirectory($file) . '/' . $file->getHash() . '.';
		$paths = [];
		foreach ($this->filesystem->listContents(self::thumbnailDirectory($file), false) as $item) {
			if ($item->isFile() && str_starts_with($item->path(), $prefix)) {
				$paths[] = $item->path();
			}
		}

		return $paths;
	}


	/**
	 * Náhled požadovaný šablonou, null = nepovolený (místo náhledu se použije originál).
	 *
	 * @throws InvalidThumbnailException nepovolený náhled a $strictThumbnails
	 */
	private function resolveThumbnail(ImageRequest $request): ?Thumbnail
	{
		try {
			$thumbnail = Thumbnail::fromRequest($request);
			if ($this->allowedThumbnails->isAllowed($thumbnail)) {
				return $thumbnail;
			}

			$message = sprintf(
				"Náhled '%s' není povolený - přidejte ho do neonu projektu nebo pluginu: fileStorage: thumbnails: %s: [%s]",
				$thumbnail->getKey(),
				$thumbnail->crop ? 'crop' : 'resize',
				$thumbnail->getConfigEntry(),
			);
		} catch (InvalidThumbnailException $e) {
			$message = $e->getMessage();
		}

		if ($this->strictThumbnails) {
			throw new InvalidThumbnailException($message);
		}

		if (!isset($this->loggedThumbnails[$message])) {
			$this->loggedThumbnails[$message] = true;
			$this->logger?->log($message, ILogger::WARNING);
		}

		return null;
	}


	private function thumbnailLink(HashFile $file, Thumbnail $thumbnail): string
	{
		if ($this->isThumbnailKnown($file, $thumbnail)) {
			return $this->filesystem->publicUrl($this->getThumbnailPath($file, $thumbnail));
		}

		return $this->linkGenerator->link('Front:Files:thumbnail', ['hash' => $file->getHash(), 'thumbnail' => $thumbnail->getKey()]);
	}


	private function isThumbnailKnown(HashFile $file, Thumbnail $thumbnail): bool
	{
		return isset($this->loadKnownThumbnails($file)[$thumbnail->getKey()]);
	}


	private function rememberThumbnail(HashFile $file, Thumbnail $thumbnail): void
	{
		$known = $this->loadKnownThumbnails($file);
		$known[$thumbnail->getKey()] = true;
		$this->known[$file->getHash()] = $known;
		$this->registry->save($file->getHash(), $known, [Cache::Expire => self::RegistryExpiration]);
	}


	/**
	 * @return array<string, true>
	 */
	private function loadKnownThumbnails(HashFile $file): array
	{
		$hash = $file->getHash();
		if (!isset($this->known[$hash])) {
			$known = $this->registry->load($hash);
			$this->known[$hash] = [];
			if (is_array($known)) {
				foreach ($known as $key => $value) {
					if (is_string($key)) {
						$this->known[$hash][$key] = true;
					}
				}
			}
		}

		return $this->known[$hash];
	}


	/**
	 * Narovná obrázek podle EXIF a zmenší ho na $maxDimensions, metadata JPEG přenese do přeuloženého obrázku.
	 * Vrací nový obsah, nebo null, když se obrázek měnit nemusel a uloží se tak, jak byl nahraný.
	 */
	private function prepareImage(string $temporaryFile, HashImageEntity $file, ?string $maxDimensions): ?string
	{
		$contents = FileSystem::read($temporaryFile);
		$type = Image::detectTypeFromString($contents, $width, $height);
		$width ??= 0;
		$height ??= 0;
		if ($type === null) {
			$file->setWidth($width);
			$file->setHeight($height);
			return null;
		}

		$orientation = $type === ImageType::JPEG ? ExifOrientation::read($contents) : ExifOrientation::Normal;
		if ($orientation >= 5) { // 5-8 = otočení o 90°, strany se prohodí
			[$width, $height] = [$height, $width];
		}

		$max = self::parseDimensions($maxDimensions);
		$resize = $max !== null
			&& (($max->width !== null && $width > $max->width) || ($max->height !== null && $height > $max->height));
		$metadata = $type === ImageType::JPEG ? JpegMetadata::fromJpeg($contents) : null;

		if ($orientation === ExifOrientation::Normal && !$resize) {
			$file->setWidth($width);
			$file->setHeight($height);
			if ($metadata === null) {
				return null;
			}

			// obrazová data beze změny - jen případně bez GPS / bez metadat (bezztrátově, jen hlavička souboru)
			$filtered = $this->filterMetadata($metadata);
			return $filtered->equals($metadata) ? null : $filtered->applyTo($contents);
		}

		$image = Image::fromString($contents);
		ExifOrientation::apply($image, $orientation);
		if ($max !== null && $resize) {
			$image->resize($max->width, $max->height);
		}

		$file->setWidth($image->getWidth());
		$file->setHeight($image->getHeight());

		return $this->encodeOriginal($image, $type, $metadata);
	}


	/**
	 * Uloží originál přeuložený přes GD (JPEG/WebP v kvalitě OriginalQuality) s metadaty původního souboru.
	 *
	 * @param ImageType::* $type
	 */
	private function encodeOriginal(Image $image, int $type, ?JpegMetadata $metadata): string
	{
		$contents = $image->toString($type, self::quality($type, self::OriginalQuality));
		if ($type !== ImageType::JPEG || $metadata === null) {
			return $contents;
		}

		return $this->filterMetadata($metadata)
			->forImage($image->getWidth(), $image->getHeight())
			->applyTo($contents);
	}


	/**
	 * Metadata, která si originál ponechá (viz $keepMetadata, $stripGps).
	 */
	private function filterMetadata(JpegMetadata $metadata): JpegMetadata
	{
		if (!$this->keepMetadata) {
			return $metadata->onlyColorProfile();
		}

		return $this->stripGps ? $metadata->withoutGps() : $metadata;
	}


	/**
	 * @param ImageType::* $type
	 * @param string $previous dosavadní obsah originálu (metadata se přenesou)
	 * @throws FilesystemException
	 */
	private function saveOriginal(HashFile $file, string $path, Image $image, int $type, string $previous): int
	{
		$metadata = $type === ImageType::JPEG ? JpegMetadata::fromJpeg($previous) : null;
		$contents = $this->encodeOriginal($image, $type, $metadata);
		$this->filesystem->write($path, $contents, ['mimetype' => Image::typeToMimeType($type)]);
		$this->removeCache($file);

		return strlen($contents);
	}


	/**
	 * @return array{Image, ImageType::*, string} obrázek, jeho typ a původní obsah souboru
	 * @throws FilesystemException
	 */
	private function loadImage(string $path): array
	{
		$contents = $this->filesystem->read($path);
		$type = Image::detectTypeFromString($contents)
			?? throw new \RuntimeException("Soubor '$path' v úložišti není podporovaný obrázek.");

		return [Image::fromString($contents), $type, $contents];
	}


	private function toHashFile(File $file): HashFile
	{
		if (!$file instanceof HashFile) {
			throw new \LogicException(sprintf('FlysystemStorage pracuje jen se soubory s hashem (%s), předán %s.', HashFile::class, $file::class));
		}

		return $file;
	}


	private static function parseDimensions(?string $dimensions): ?Thumbnail
	{
		if ($dimensions === null || $dimensions === '') {
			return null;
		}

		try {
			return Thumbnail::fromDimensions($dimensions);
		} catch (InvalidThumbnailException) {
			return null;
		}
	}


	private static function quality(int $type, int $quality): ?int
	{
		return $type === ImageType::JPEG || $type === ImageType::WEBP ? $quality : null;
	}


	private static function hashDirectory(string $hash): string
	{
		return $hash[0] . '/' . $hash[1];
	}


	private static function thumbnailDirectory(HashFile $file): string
	{
		return self::CacheDirectory . '/' . self::hashDirectory($file->getHash());
	}


	private static function withExtension(string $name, string $extension): string
	{
		return $extension === '' ? $name : $name . '.' . $extension;
	}
}
