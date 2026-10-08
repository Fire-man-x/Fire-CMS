<?php

declare(strict_types=1);

namespace Tests\FileStorage;

use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Files\HashFileEntity;
use App\FileStorage\Files\HashImageEntity;
use App\FileStorage\Files\ImageEntity;
use App\FileStorage\Images\ExifOrientation;
use App\FileStorage\Images\JpegMetadata;
use App\FileStorage\Naming\HashNamingScheme;
use App\FileStorage\Request\FileRequest;
use App\FileStorage\Request\ImageRequest;
use App\FileStorage\Responses\StreamResponse;
use App\FileStorage\Storages\FlysystemStorage;
use App\FileStorage\Thumbnails\AllowedThumbnails;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Nette\Application\LinkGenerator;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\Routers\RouteList;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Http\UrlScript;
use Nette\Utils\Image;
use Nette\Utils\ImageType;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\TestImages;
use Tracy\ILogger;

require_once __DIR__ . '/../bootstrap.php';

/**
 * FlysystemStorage nad úložištěm v paměti: upload (narovnání, zmenšení, metadata), struktura klíčů,
 * náhledy na vyžádání s evidencí v cache, seznam povolených náhledů, mazání a úpravy originálu.
 */
final class FlysystemStorageTest extends TestCase
{
	private Filesystem $filesystem;

	private MemoryStorage $cache;

	private string $uploadDir;


	protected function setUp(): void
	{
		$this->filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => '/files/']);
		$this->cache = new MemoryStorage();
		$this->uploadDir = TEMP_DIR . '/uploads';
		@mkdir($this->uploadDir);
	}


	private function createStorage(bool $strict = false, bool $keepMetadata = true, bool $stripGps = false, ?ILogger $logger = null): FlysystemStorage
	{
		$router = new RouteList();
		$router->addRoute('files/thumbnail/<storage>/<path .+>/<thumbnail [^/]+>', 'Front:Files:thumbnail');

		return new FlysystemStorage(
			$this->filesystem,
			new HashNamingScheme(),
			new AllowedThumbnails(['10x10', '20x'], ['8x8']),
			new LinkGenerator($router, new UrlScript('https://example.com/')),
			$this->cache,
			$strict,
			$keepMetadata,
			$stripGps,
			'files',
			$logger,
		);
	}


	/**
	 * @return array<string, mixed> výsledek exif_read_data() po sekcích
	 */
	private static function readExif(string $jpeg): array
	{
		$stream = fopen('php://memory', 'r+b') ?: throw new \RuntimeException();
		fwrite($stream, $jpeg);
		rewind($stream);
		$exif = @exif_read_data($stream, null, true);

		return is_array($exif) ? $exif : [];
	}


	/** Fotka z mobilu: na ležato s EXIF orientací 6, GPS polohou a vloženým náhledem */
	private static function phonePhoto(): string
	{
		return TestImages::withSegments(TestImages::halves(), [[0xE1, TestImages::exif(6)], [0xE2, TestImages::iccProfile()]]);
	}


	/**
	 * @param array<string, mixed> $settings
	 */
	private function uploadImage(FlysystemStorage $storage, string $contents, string $name = 'photo.jpg', array $settings = []): HashImageEntity
	{
		$file = $storage->upload(TestImages::upload($this->uploadDir, $name, $contents), $settings);
		Assert::type(HashImageEntity::class, $file);
		assert($file instanceof HashImageEntity);

		return $file;
	}


	public function testUploadStoresOriginalUnderHash(): void
	{
		$png = TestImages::halves(type: ImageType::PNG);
		$file = $this->uploadImage($this->createStorage(), $png, 'Photo.PNG');

		$hash = sha1($png);
		Assert::same($hash, $file->getHash());
		Assert::same('Photo', $file->getName());
		Assert::same('png', $file->getExtension());
		Assert::same('image/png', $file->getMimeType());
		Assert::same([40, 20], [$file->getWidth(), $file->getHeight()]);
		Assert::same("$hash[0]/$hash[1]/$hash.png", $this->createStorage()->getOriginalPath($file));
		Assert::same($png, $this->filesystem->read($this->createStorage()->getOriginalPath($file)), 'obrázek bez EXIF se uloží beze změny');
		Assert::same(strlen($png), $file->getSize());
	}


	public function testUploadStraightensPhotoFromPhone(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, self::phonePhoto());

		Assert::same([20, 40], [$file->getWidth(), $file->getHeight()]);
		$stored = $this->filesystem->read($storage->getOriginalPath($file));
		$exif = self::readExif($stored);
		Assert::same(1, $exif['IFD0']['Orientation'] ?? null, 'EXIF zůstává, obrázek je narovnaný fyzicky - orientace 1');
		Assert::same([20, 40], [$exif['EXIF']['ExifImageWidth'] ?? null, $exif['EXIF']['ExifImageLength'] ?? null]);
		Assert::same(['50/1', '4/1', '30/1'], $exif['GPS']['GPSLatitude'] ?? null, 'GPS poloha ve výchozím nastavení zůstává');
		Assert::contains(TestImages::iccProfile(), $stored);
		$image = Image::fromString($stored);
		Assert::same([20, 40], [$image->getWidth(), $image->getHeight()]);
		[$r, , $b] = TestImages::rgbAt($image, 10, 5);
		Assert::true($r > 200 && $b < 60);
		Assert::same(strlen($stored), $file->getSize());
	}


	public function testUploadResizesToMaxDimensions(): void
	{
		$storage = $this->createStorage();
		$jpeg = TestImages::withSegments(TestImages::halves(), [[0xE1, TestImages::exif(1)]]);
		$file = $this->uploadImage($storage, $jpeg, settings: ['dimensions' => '10x10']);

		Assert::same([10, 5], [$file->getWidth(), $file->getHeight()]);
		$stored = $this->filesystem->read($storage->getOriginalPath($file));
		$image = Image::fromString($stored);
		Assert::same([10, 5], [$image->getWidth(), $image->getHeight()]);
		$exif = self::readExif($stored);
		Assert::same([10, 5], [$exif['EXIF']['ExifImageWidth'] ?? null, $exif['EXIF']['ExifImageLength'] ?? null], 'zmenšený originál si EXIF ponechá');
		Assert::same(['50/1', '4/1', '30/1'], $exif['GPS']['GPSLatitude'] ?? null, 'poloha zůstává');
	}


	public function testUploadWithoutChangesKeepsFileAsUploaded(): void
	{
		$storage = $this->createStorage();
		$jpeg = TestImages::withSegments(TestImages::halves(), [[0xE1, TestImages::exif(1)]]);
		$file = $this->uploadImage($storage, $jpeg);

		Assert::same($jpeg, $this->filesystem->read($storage->getOriginalPath($file)));
	}


	public function testUploadRemovesGpsWithoutReencoding(): void
	{
		$storage = $this->createStorage(stripGps: true);
		$jpeg = TestImages::withSegments(TestImages::halves(), [[0xE1, TestImages::exif(1)]]);
		$stored = $this->filesystem->read($storage->getOriginalPath($this->uploadImage($storage, $jpeg)));

		Assert::false(isset(self::readExif($stored)['GPS']));
		Assert::same(1, self::readExif($stored)['IFD0']['Orientation'] ?? null);
		Assert::same(substr($jpeg, strrpos($jpeg, "\xFF\xDA") ?: 0), substr($stored, strrpos($stored, "\xFF\xDA") ?: 0), 'obrazová data beze ztráty kvality');
	}


	public function testUploadWithoutMetadata(): void
	{
		$storage = $this->createStorage(keepMetadata: false);
		$stored = $this->filesystem->read($storage->getOriginalPath($this->uploadImage($storage, self::phonePhoto())));

		Assert::same([], array_intersect_key(self::readExif($stored), ['IFD0' => 1, 'EXIF' => 1, 'GPS' => 1]));
		Assert::contains(TestImages::iccProfile(), $stored, 'barevný profil zůstává vždy');
	}


	public function testSameContentUploadedTwiceGetsOwnFile(): void
	{
		$storage = $this->createStorage();
		$png = TestImages::halves(type: ImageType::PNG);
		$first = $this->uploadImage($storage, $png, 'a.png');
		$second = $this->uploadImage($storage, $png, 'b.png');

		Assert::notSame($first->getHash(), $second->getHash());
		Assert::true($storage->exist($first));
		Assert::true($storage->exist($second));
	}


	public function testUploadNonImageFile(): void
	{
		$storage = $this->createStorage();
		$file = $storage->upload(TestImages::upload($this->uploadDir, 'report.pdf.txt', 'hello'));

		Assert::type(HashFileEntity::class, $file);
		Assert::same('report.pdf', $file->getName());
		Assert::same('txt', $file->getExtension());
		Assert::same(5, $file->getSize());
		Assert::same('hello', $this->filesystem->read($storage->getOriginalPath($file)));
		Assert::type(StreamResponse::class, $storage->download(FileRequest::fromFile($file)));
		Assert::same('/files/' . $storage->getOriginalPath($file), $storage->link(FileRequest::fromFile($file)));
	}


	public function testThumbnailIsGeneratedOnDemand(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::halves(type: ImageType::PNG), 'photo.png');
		$hash = $file->getHash();
		$request = ImageRequest::fromMacro($file, ['10x10']);

		Assert::same("https://example.com/files/thumbnail/files/$hash[0]/$hash[1]/$hash.png/10x10", $storage->link($request), 'dokud náhled není, odkaz vede na generátor');
		Assert::false($this->filesystem->fileExists($storage->getThumbnailPath($file, $storage->getAllowedThumbnails()->get('10x10') ?? throw new \LogicException())));

		Assert::type(StreamResponse::class, $storage->thumbnail($storage->getOriginalPath($file), '10x10'));
		$thumbnailPath = HashNamingScheme::CacheDirectory . "/$hash[0]/$hash[1]/$hash.10x10.png";
		Assert::true($this->filesystem->fileExists($thumbnailPath));
		$thumbnailImage = Image::fromString($this->filesystem->read($thumbnailPath));
		Assert::same([10, 5], [$thumbnailImage->getWidth(), $thumbnailImage->getHeight()]);
		Assert::true(JpegMetadata::fromJpeg($this->filesystem->read($thumbnailPath))->isEmpty(), 'náhled je bez metadat');

		Assert::same("/files/$thumbnailPath", $storage->link($request), 'vygenerovaný náhled - přímý odkaz');
		Assert::same("/files/$thumbnailPath", $this->createStorage()->link($request), 'evidence náhledů přežije požadavek (Nette Cache)');

		$redirect = $storage->thumbnail($storage->getOriginalPath($file), '10x10');
		Assert::type(RedirectResponse::class, $redirect);
		assert($redirect instanceof RedirectResponse);
		Assert::same("/files/$thumbnailPath", $redirect->getUrl(), 'existující náhled - přesměrování, žádné nové generování');
	}


	public function testCropThumbnail(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::halves());

		Assert::contains('/crop-8x8', $storage->link(ImageRequest::crop($file, ['8x8'])));
		$storage->thumbnail($storage->getOriginalPath($file), 'crop-8x8');
		$thumbnail = $storage->getAllowedThumbnails()->get('crop-8x8') ?? throw new \LogicException();
		$thumbnailImage = Image::fromString($this->filesystem->read($storage->getThumbnailPath($file, $thumbnail)));
		Assert::same([8, 8], [$thumbnailImage->getWidth(), $thumbnailImage->getHeight()]);
	}


	public function testDisallowedThumbnail(): void
	{
		$logger = new class implements ILogger {
			/** @var list<string> */
			public array $messages = [];

			public function log(mixed $value, string $level = self::INFO): void
			{
				$this->messages[] = $level . ': ' . (is_string($value) ? $value : '');
			}
		};
		$storage = $this->createStorage(logger: $logger);
		$file = $this->uploadImage($storage, TestImages::halves());
		$originalUrl = '/files/' . $storage->getOriginalPath($file);

		Assert::same($originalUrl, $storage->link(ImageRequest::fromMacro($file, ['11x11'])), 'v produkci originál místo náhledu');
		Assert::same($originalUrl, $storage->link(ImageRequest::fromMacro($file, ['11x11'])));
		Assert::same($originalUrl, $storage->link(ImageRequest::fromMacro($file, ['smallest'])));
		Assert::count(2, $logger->messages, 'každý nepovolený náhled se v požadavku zaloguje jen jednou');
		Assert::contains('fileStorage: files: thumbnails: resize: [11x11]', $logger->messages[0]);

		Assert::exception(
			fn() => $this->createStorage(strict: true)->link(ImageRequest::fromMacro($file, ['11x11'])),
			InvalidThumbnailException::class,
			"%a%'11x11' není povolený%a%",
		);
		Assert::exception(fn() => $storage->thumbnail($storage->getOriginalPath($file), '11x11'), InvalidThumbnailException::class);
		Assert::exception(fn() => $storage->thumbnail($storage->getOriginalPath($file), '../10x10'), InvalidThumbnailException::class);
	}


	public function testThumbnailFromItsOwnPath(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::halves(type: ImageType::PNG), 'photo.png');
		$hash = $file->getHash();
		$thumbnailPath = HashNamingScheme::CacheDirectory . "/$hash[0]/$hash[1]/$hash.10x10.png";

		Assert::type(StreamResponse::class, $storage->thumbnailFromPath($thumbnailPath));
		Assert::true($this->filesystem->fileExists($thumbnailPath));
		Assert::type(StreamResponse::class, $storage->thumbnailFromPath($thumbnailPath), 'existující náhled se pošle, ne přesměruje na sebe');

		$message = "%a%není povolený náhled existujícího originálu v úložišti 'files'.";
		foreach ([$storage->getOriginalPath($file), HashNamingScheme::CacheDirectory . "/$hash[0]/$hash[1]/$hash.11x11.png", HashNamingScheme::CacheDirectory . "/$hash[0]/$hash[1]/$hash.10x10.jpg", HashNamingScheme::CacheDirectory . '/a/a/' . str_repeat('a', 40) . '.10x10.png'] as $path) {
			Assert::exception(fn() => $storage->thumbnailFromPath($path), InvalidThumbnailException::class, $message);
		}
	}


	public function testGeneratorAcceptsOnlyOriginals(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::halves());
		$original = $storage->getOriginalPath($file);
		$thumbnail = $storage->getAllowedThumbnails()->get('10x10') ?? throw new \LogicException();
		$storage->thumbnail($original, '10x10');

		$message = "%a%není originál v úložišti 'files'.";
		foreach ([$storage->getThumbnailPath($file, $thumbnail), '../' . $original, 'x/' . $original, strtoupper($original), 'a/b/' . $file->getHash() . '.jpg'] as $path) {
			Assert::exception(fn() => $storage->thumbnail($path, '10x10'), InvalidThumbnailException::class, $message);
		}

		// originál, který v úložišti není
		$missing = str_repeat('a', 40);
		Assert::exception(fn() => $storage->thumbnail("a/a/$missing.jpg", '10x10'), FilesystemException::class);
	}


	public function testRemoveCacheDeletesAllThumbnails(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::halves());
		$storage->thumbnail($storage->getOriginalPath($file), '10x10');
		$storage->thumbnail($storage->getOriginalPath($file), 'crop-8x8');

		Assert::count(2, $storage->listThumbnails($file));

		// jiný soubor se stejnými prvními znaky hashe - jeho náhledy leží ve stejné složce cache/<h0>/<h1>/
		$neighbour = new HashImageEntity();
		$neighbour->setHash(substr($file->getHash(), 0, 2) . str_repeat('0', 38));
		$neighbour->setExtension('jpg');
		$neighbour->setMimeType('image/jpeg');
		$this->filesystem->write($storage->getOriginalPath($neighbour), TestImages::halves());
		$storage->thumbnail($storage->getOriginalPath($neighbour), '10x10');
		// náhled ve formátu HashFileStorage (<hash>.<rozměry>.<příznaky>.<ořez>.<přípona>) ve stejné složce
		$legacy = HashNamingScheme::CacheDirectory . '/' . $file->getHash()[0] . '/' . $file->getHash()[1] . '/' . $file->getHash() . '.130x130.0.0.jpg';
		$this->filesystem->write($legacy, 'x');

		$storage->removeCache($file);

		Assert::same([], $storage->listThumbnails($file));
		Assert::false($this->filesystem->fileExists($legacy), 'smažou se i náhledy ve starém formátu');
		Assert::count(1, $storage->listThumbnails($neighbour), 'náhledy jiného souboru ve stejné složce zůstanou');
		Assert::contains('/files/thumbnail/', $storage->link(ImageRequest::fromMacro($file, ['10x10'])), 'evidence se smazala s náhledy');
		Assert::true($storage->exist($file), 'originál zůstává');

		$withoutCache = new HashImageEntity();
		$withoutCache->setHash(str_repeat('f', 40));
		$withoutCache->setExtension('jpg');
		Assert::same([], $storage->listThumbnails($withoutCache), 'složka cache/f/f/ neexistuje');
		$storage->removeCache($withoutCache);
	}


	public function testModifyOriginalRotatesAndClearsThumbnails(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::withSegments(TestImages::halves(), [[0xE1, TestImages::exif(1)]]));
		$storage->thumbnail($storage->getOriginalPath($file), '10x10');

		$size = $storage->modifyOriginal($file, fn(Image $image) => ExifOrientation::rotate($image, 270));

		$stored = $this->filesystem->read($storage->getOriginalPath($file));
		Assert::same(strlen($stored), $size);
		$image = Image::fromString($stored);
		Assert::same([20, 40], [$image->getWidth(), $image->getHeight()]);
		$exif = self::readExif($stored);
		Assert::same([20, 40], [$exif['EXIF']['ExifImageWidth'] ?? null, $exif['EXIF']['ExifImageLength'] ?? null], 'EXIF přežije i otočení');
		Assert::same([], $storage->listThumbnails($file));
	}


	public function testFixOrientationOfStoredPhoto(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::halves());
		// originál uložený dřív, než se fotky narovnávaly při uploadu
		$this->filesystem->write($storage->getOriginalPath($file), TestImages::withExifOrientation(TestImages::halves(), 6));

		Assert::type('int', $storage->fixOrientation($file));
		$image = $storage->original($file);
		Assert::same([20, 40], [$image->getWidth(), $image->getHeight()]);
		Assert::same(1, self::readExif($this->filesystem->read($storage->getOriginalPath($file)))['IFD0']['Orientation'] ?? null);
		Assert::null($storage->fixOrientation($file), 'už narovnaný');
	}


	public function testThumbnailOfOldUnstraightenedPhoto(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::halves());
		$this->filesystem->write($storage->getOriginalPath($file), self::phonePhoto()); // originál s EXIF orientací 6

		$storage->thumbnail($storage->getOriginalPath($file), '20x');
		$thumbnail = $storage->getAllowedThumbnails()->get('20x') ?? throw new \LogicException();
		$thumbnailImage = Image::fromString($this->filesystem->read($storage->getThumbnailPath($file, $thumbnail)));
		Assert::same([20, 40], [$thumbnailImage->getWidth(), $thumbnailImage->getHeight()], 'náhled se narovná podle EXIF originálu');
		[$r, , ] = TestImages::rgbAt($thumbnailImage, 10, 5);
		Assert::true($r > 200);
	}


	public function testRemove(): void
	{
		$storage = $this->createStorage();
		$file = $this->uploadImage($storage, TestImages::halves());
		$storage->thumbnail($storage->getOriginalPath($file), '10x10');

		$storage->remove($file);

		Assert::false($storage->exist($file));
		Assert::same([], $this->filesystem->listContents('', true)->toArray());
		Assert::exception(fn() => $storage->thumbnail($storage->getOriginalPath($file), '10x10'), FilesystemException::class);
	}


	public function testImageWithoutHashIsRejected(): void
	{
		$storage = $this->createStorage();
		$image = new ImageEntity();
		$image->setName('photo');
		$image->setExtension('jpg');
		$image->setMimeType('image/jpeg');

		$message = 'HashNamingScheme pracuje jen se soubory s hashem %a%';
		Assert::exception(fn() => $storage->link(ImageRequest::fromMacro($image)), \LogicException::class, $message);
		Assert::exception(fn() => $storage->link(ImageRequest::fromMacro($image, ['10x10'])), \LogicException::class, $message);
		Assert::exception(fn() => $storage->download(ImageRequest::fromMacro($image, ['10x10'])), \LogicException::class, $message);
	}
}

(new FlysystemStorageTest())->run();
