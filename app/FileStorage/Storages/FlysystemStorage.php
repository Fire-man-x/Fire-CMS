<?php
declare(strict_types=1);

namespace App\FileStorage\Storages;

use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Exceptions\UploaderException;
use App\FileStorage\Files\File;
use App\FileStorage\Files\ImageEntity;
use App\FileStorage\Images\ExifOrientation;
use App\FileStorage\Images\JpegMetadata;
use App\FileStorage\Naming\NamingScheme;
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
use Nette\Utils\UnknownImageFileException;
use Tracy\ILogger;

/**
 * Úložiště souborů nad Flysystemem, jedno pro každou položku `fileStorage: <název>:` v neonu:
 * - kam: lokální adresář nebo S3 bucket (Flysystem, viz FilesystemFactory),
 * - jak se soubory jmenují: schéma názvů (NamingScheme, výchozí HashNamingScheme správce souborů),
 * - které náhledy obrázků smí vzniknout: AllowedThumbnails úložiště.
 *
 * Náhledy vznikají až na vyžádání a jen povolené, dvěma způsoby:
 * - výchozí (i pro S3): link() úložiště nekontroluje (každá kontrola by byl HTTP požadavek), o vygenerovaných
 *   náhledech vede evidenci v Nette Cache (podle klíče originálu). Dokud náhled v evidenci není, vrací link()
 *   adresu generátoru (Front:Files:thumbnail s názvem úložiště a klíčem originálu), který náhled vytvoří, uloží
 *   a pošle - další vykreslení už odkazuje přímo na veřejnou URL náhledu.
 * - `directThumbnails` (lokální disk): link() vrací vždy přímou URL náhledu. Existující soubor pošle web server,
 *   požadavek na chybějící pošle do aplikace (Front:Files:missingThumbnail) a náhled vytvoří thumbnailFromPath().
 *   Náhled smazaný mimo aplikaci tak vznikne znovu hned při dalším požadavku.
 */
final class FlysystemStorage implements IStorage
{
	/** Jak dlouho platí záznam o vygenerovaném náhledu - náhled smazaný mimo aplikaci se znovu vytvoří nejpozději po této době */
	private const string RegistryExpiration = '30 days';

	/**
	 * Verze evidence náhledů v názvu cache - zvyšte ji, když se změní, kde náhledy leží (schéma názvů), jinak by
	 * evidence odkazovala na náhledy na místech, kde už nejsou (3 = evidence podle klíče originálu).
	 */
	private const int RegistryVersion = 3;

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

	/** Náhled poslaný na jeho vlastní adrese (directThumbnails) se cachuje jako statický soubor (ThumbnailCacheControl) */
	private const string DirectThumbnailExpiration = '1 day';

	/** Kvalita JPEG/WebP při přeuložení originálu (narovnání, zmenšení, otočení) a u náhledů */
	private const int OriginalQuality = 90;
	private const int ThumbnailQuality = 75;

	private Cache $registry;

	/** @var array<string, array<string, true>> klíč originálu => vygenerované náhledy, načtené během tohoto požadavku */
	private array $known = [];

	/** @var array<string, true> nepovolené náhledy už zalogované v tomto požadavku */
	private array $loggedThumbnails = [];


	/**
	 * @param bool $strictThumbnails nepovolený náhled v šabloně vyhodí výjimku (vývoj), jinak se zaloguje a použije se originál
	 * @param bool $keepMetadata ponechat originálu JPEG metadata (EXIF, XMP, IPTC) i po zmenšení a narovnání;
	 *   false = zahodit (barevný profil ICC zůstává vždy). Náhledy metadata nemají.
	 * @param bool $stripGps odstranit z metadat originálu GPS polohu, kde byla fotka pořízena
	 * @param string $name název úložiště z neonu - v URL generátoru náhledů a v názvu evidence náhledů v Nette Cache
	 * @param bool $directThumbnails odkazy přímo na náhledy, chybějící vytvoří thumbnailFromPath() (web server
	 *   posílá požadavky na neexistující soubory pod publicUrl do aplikace)
	 */
	public function __construct(
		private readonly FilesystemOperator $filesystem,
		private readonly NamingScheme $naming,
		private readonly AllowedThumbnails $allowedThumbnails,
		private readonly LinkGenerator $linkGenerator,
		CacheStorage $cacheStorage,
		private readonly bool $strictThumbnails = false,
		private readonly bool $keepMetadata = true,
		private readonly bool $stripGps = false,
		private readonly string $name = 'files',
		private readonly ?ILogger $logger = null,
		private readonly bool $directThumbnails = false,
	)
	{
		$this->registry = new Cache($cacheStorage, 'FileStorage.thumbnails.' . $name . '.' . self::RegistryVersion);
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getFilesystem(): FilesystemOperator
	{
		return $this->filesystem;
	}


	public function getNamingScheme(): NamingScheme
	{
		return $this->naming;
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
		return $this->filesystem->fileExists($this->getOriginalPath($file));
	}


	/**
	 * @throws FilesystemException
	 * @throws UnknownImageFileException soubor není podporovaný obrázek
	 */
	public function original(File $file): Image
	{
		return $this->loadImage($this->getOriginalPath($file))[0];
	}


	/**
	 * Uloží nahraný soubor pod klíčem ze schématu názvů. Obrázek (ImageEntity) se narovná podle EXIF orientace
	 * a zmenší na `dimensions`, metadata JPEG se přenesou do přeuloženého obrázku (viz $keepMetadata, $stripGps).
	 * Soubor se stejným klíčem se přepíše a smažou se jeho staré náhledy.
	 *
	 * @param array<string, mixed> $settings `dimensions` => největší rozměr originálu, např. '1000x1000';
	 *   další nastavení předá schématu názvů (NamingScheme::createFile())
	 * @throws UploaderException
	 * @throws FilesystemException
	 */
	public function upload(FileUpload $upload, array $settings = []): File
	{
		if (!$upload->isOk()) {
			throw new UploaderException($upload->getError());
		}

		$temporaryFile = $upload->getTemporaryFile();
		$mimeType = $upload->getContentType() ?? 'application/octet-stream';
		$maxDimensions = $settings['dimensions'] ?? null;

		$file = $this->naming->createFile($upload, $upload->isImage(), $settings, $this->filesystem->fileExists(...));
		$file->setMimeType($mimeType);
		$contents = $file instanceof ImageEntity && $upload->isImage()
			? $this->prepareImage($temporaryFile, $file, is_string($maxDimensions) ? $maxDimensions : null)
			: null;

		$path = $this->naming->getOriginalPath($file);
		if ($this->filesystem->fileExists($path)) {
			$this->removeCache($file); // schéma nechalo soubor přepsat (např. stejný název v albu) - staré náhledy pryč
		}

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
	 * @throws UnknownImageFileException náhled souboru, který není podporovaný obrázek
	 */
	public function download(Request $request): Response
	{
		$file = $request->getFile();
		$path = $this->getOriginalPath($file);
		if ($request instanceof ImageRequest && $request->getDimensions() !== Request::ORIGINAL) {
			return $this->thumbnail($path, $this->allowedThumbnails->fromRequest($request)->getKey());
		}

		$size = $this->filesystem->fileSize($path); // před readStream() - chybějící soubor tak skončí dřív, než se otevře stream

		return new StreamResponse(
			$this->filesystem->readStream($path),
			$file->getMimeType(),
			$file->getNameWithExtension(),
			$size,
		);
	}


	/**
	 * URL originálu, u ImageRequest s rozměry URL náhledu (bez directThumbnails URL generátoru, dokud náhled neexistuje).
	 *
	 * @throws InvalidThumbnailException nepovolený náhled a $strictThumbnails
	 */
	public function link(Request $request): string
	{
		$path = $this->getOriginalPath($request->getFile());
		if ($request instanceof ImageRequest && $request->getDimensions() !== Request::ORIGINAL) {
			$thumbnail = $this->resolveThumbnail($request);
			if ($thumbnail !== null) {
				return $this->thumbnailLink($path, $thumbnail);
			}
		}

		return $this->filesystem->publicUrl($path);
	}


	/**
	 * Náhled pro generátor náhledů (Front:Files:thumbnail) - vytvoří ho, pokud ještě neexistuje.
	 *
	 * @param string $path klíč originálu (z URL generátoru)
	 * @param string $key klíč náhledu, např. `300x200`
	 * @throws InvalidThumbnailException nepovolený náhled nebo $path není originál v tomto úložišti
	 * @throws FilesystemException originál chybí nebo chyba úložiště
	 * @throws UnknownImageFileException originál není podporovaný obrázek
	 */
	public function thumbnail(string $path, string $key): Response
	{
		if (!$this->naming->isOriginalPath($path)) {
			throw new InvalidThumbnailException(sprintf("'%s' není originál v úložišti '%s'.", $path, $this->name));
		}

		$thumbnail = $this->allowedThumbnails->get($key)
			?? throw new InvalidThumbnailException(sprintf("Náhled '%s' není povolený.", $key));

		$thumbnailPath = $this->naming->getThumbnailPath($path, $thumbnail);
		if ($this->filesystem->fileExists($thumbnailPath)) {
			$this->rememberThumbnail($path, $thumbnail);
			return new RedirectResponse($this->filesystem->publicUrl($thumbnailPath));
		}

		return $this->createThumbnail($path, $thumbnail, $thumbnailPath, self::GeneratorExpiration);
	}


	/**
	 * Náhled vyžádaný přímo na jeho adrese (directThumbnails, Front:Files:missingThumbnail): web server pošle do
	 * aplikace požadavek na náhled, který v úložišti není. Originál a klíč náhledu zjistí ze schématu názvů
	 * (NamingScheme::parseThumbnailPath()), náhled vytvoří, uloží a pošle - další požadavky obslouží web server.
	 *
	 * @param string $thumbnailPath klíč náhledu v úložišti (cesta z URL za `publicUrl`)
	 * @throws InvalidThumbnailException cesta není povolený náhled existujícího originálu v tomto úložišti
	 * @throws FilesystemException chyba úložiště
	 * @throws UnknownImageFileException originál není podporovaný obrázek
	 */
	public function thumbnailFromPath(string $thumbnailPath): Response
	{
		foreach ($this->naming->parseThumbnailPath($thumbnailPath) as [$path, $key]) {
			$thumbnail = $this->allowedThumbnails->get($key);
			// zpětná kontrola: náhled musí ležet přesně tam, kam ho schéma ukládá (jinak by šlo zapsat kamkoliv)
			if (
				$thumbnail === null
				|| !$this->naming->isOriginalPath($path)
				|| $this->naming->getThumbnailPath($path, $thumbnail) !== $thumbnailPath
				|| !$this->filesystem->fileExists($path)
			) {
				continue;
			}

			if ($this->filesystem->fileExists($thumbnailPath)) {
				// existující soubor měl poslat web server - přesměrování na stejnou adresu by se mohlo zacyklit
				return new StreamResponse(
					$this->filesystem->readStream($thumbnailPath),
					$this->filesystem->mimeType($thumbnailPath),
					contentLength: $this->filesystem->fileSize($thumbnailPath),
					expiration: self::DirectThumbnailExpiration,
				);
			}

			return $this->createThumbnail($path, $thumbnail, $thumbnailPath, self::DirectThumbnailExpiration);
		}

		throw new InvalidThumbnailException(sprintf("'%s' není povolený náhled existujícího originálu v úložišti '%s'.", $thumbnailPath, $this->name));
	}


	/**
	 * Smaže originál i všechny jeho náhledy.
	 *
	 * @throws FilesystemException
	 */
	public function remove(File $file): void
	{
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
		$path = $this->getOriginalPath($file);
		foreach ($this->naming->listThumbnails($path, $this->filesystem) as $thumbnailPath) {
			$this->filesystem->delete($thumbnailPath);
		}

		unset($this->known[$path]);
		$this->registry->remove($path);
	}


	/**
	 * Upraví originál obrázku (např. otočení) a smaže jeho náhledy, metadata JPEG zachová. Vrací novou velikost
	 * souboru v bajtech - volající ji má uložit (správce souborů: Files::updateSize()).
	 *
	 * @param callable(Image): void $modifier
	 * @throws FilesystemException
	 * @throws UnknownImageFileException
	 */
	public function modifyOriginal(ImageEntity $file, callable $modifier): int
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
	 * @throws UnknownImageFileException
	 */
	public function fixOrientation(ImageEntity $file): ?int
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


	public function getOriginalPath(File $file): string
	{
		return $this->naming->getOriginalPath($file);
	}


	public function getThumbnailPath(File $file, Thumbnail $thumbnail): string
	{
		return $this->naming->getThumbnailPath($this->getOriginalPath($file), $thumbnail);
	}


	/**
	 * Klíče všech uložených náhledů souboru (i náhledů, které už nejsou povolené).
	 *
	 * @return list<string>
	 * @throws FilesystemException
	 */
	public function listThumbnails(File $file): array
	{
		return $this->naming->listThumbnails($this->getOriginalPath($file), $this->filesystem);
	}


	/**
	 * Náhled požadovaný šablonou, null = nepovolený (místo náhledu se použije originál).
	 *
	 * @throws InvalidThumbnailException nepovolený náhled a $strictThumbnails
	 */
	private function resolveThumbnail(ImageRequest $request): ?Thumbnail
	{
		try {
			$thumbnail = $this->allowedThumbnails->fromRequest($request);
			if ($this->allowedThumbnails->isAllowed($thumbnail)) {
				return $thumbnail;
			}

			$message = sprintf(
				"Náhled '%s' není povolený - přidejte ho do neonu projektu nebo pluginu: fileStorage: %s: thumbnails: %s: [%s]",
				$thumbnail->getKey(),
				$this->name,
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


	/**
	 * Vytvoří náhled z originálu, uloží ho a pošle.
	 *
	 * @throws FilesystemException
	 * @throws UnknownImageFileException
	 */
	private function createThumbnail(string $path, Thumbnail $thumbnail, string $thumbnailPath, string $expiration): StreamResponse
	{
		[$image, $type, $original] = $this->loadImage($path);
		// originály nahrané dřív, než se fotky narovnávaly při uploadu, můžou mít EXIF orientaci - GD ji ignoruje
		ExifOrientation::apply($image, ExifOrientation::read($original));
		$thumbnail->apply($image);
		$contents = $image->toString($type, self::quality($type, self::ThumbnailQuality)); // GD metadata nezapisuje
		$mimeType = Image::typeToMimeType($type);

		$this->filesystem->write($thumbnailPath, $contents, ['mimetype' => $mimeType, 'CacheControl' => self::ThumbnailCacheControl]);
		$this->rememberThumbnail($path, $thumbnail);

		return new StreamResponse($contents, $mimeType, expiration: $expiration);
	}


	private function thumbnailLink(string $path, Thumbnail $thumbnail): string
	{
		if ($this->directThumbnails || $this->isThumbnailKnown($path, $thumbnail)) {
			return $this->filesystem->publicUrl($this->naming->getThumbnailPath($path, $thumbnail));
		}

		return $this->linkGenerator->link('Front:Files:thumbnail', [
			'storage' => $this->name,
			'path' => $path,
			'thumbnail' => $thumbnail->getKey(),
		]);
	}


	private function isThumbnailKnown(string $path, Thumbnail $thumbnail): bool
	{
		return isset($this->loadKnownThumbnails($path)[$thumbnail->getKey()]);
	}


	private function rememberThumbnail(string $path, Thumbnail $thumbnail): void
	{
		$known = $this->loadKnownThumbnails($path);
		$known[$thumbnail->getKey()] = true;
		$this->known[$path] = $known;
		$this->registry->save($path, $known, [Cache::Expire => self::RegistryExpiration]);
	}


	/**
	 * @return array<string, true>
	 */
	private function loadKnownThumbnails(string $path): array
	{
		if (!isset($this->known[$path])) {
			$known = $this->registry->load($path);
			$this->known[$path] = [];
			if (is_array($known)) {
				foreach ($known as $key => $value) {
					if (is_string($key)) {
						$this->known[$path][$key] = true;
					}
				}
			}
		}

		return $this->known[$path];
	}


	/**
	 * Narovná obrázek podle EXIF a zmenší ho na $maxDimensions, metadata JPEG přenese do přeuloženého obrázku.
	 * Vrací nový obsah, nebo null, když se obrázek měnit nemusel a uloží se tak, jak byl nahraný.
	 */
	private function prepareImage(string $temporaryFile, ImageEntity $file, ?string $maxDimensions): ?string
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
	private function saveOriginal(File $file, string $path, Image $image, int $type, string $previous): int
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
	 * @throws UnknownImageFileException
	 */
	private function loadImage(string $path): array
	{
		$contents = $this->filesystem->read($path);
		$type = Image::detectTypeFromString($contents)
			?? throw new UnknownImageFileException("Soubor '$path' v úložišti není podporovaný obrázek.");

		return [Image::fromString($contents), $type, $contents];
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

}
