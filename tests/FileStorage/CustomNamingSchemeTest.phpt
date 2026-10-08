<?php

declare(strict_types=1);

namespace Tests\FileStorage;

use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Files\File;
use App\FileStorage\Files\ImageEntity;
use App\FileStorage\Flysystem\FilesystemFactory;
use App\FileStorage\Naming\NamingScheme;
use App\FileStorage\Request\ImageRequest;
use App\FileStorage\Responses\StreamResponse;
use App\FileStorage\Storages\FlysystemStorage;
use App\FileStorage\Thumbnails\AllowedThumbnails;
use App\FileStorage\Thumbnails\Thumbnail;
use League\Flysystem\FilesystemReader;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\RouteList;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Http\FileUpload;
use Nette\Http\UrlScript;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\TestImages;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Úložiště s vlastním schématem názvů (alba na lokálním disku, náhledy v `<album>/mini/`) - soubory nejsou
 * ve správci souborů, nemají id ani hash a jmenují se tak, jak je nahrál uživatel.
 */
final class CustomNamingSchemeTest extends TestCase
{
	private string $root;

	private string $uploadDir;


	protected function setUp(): void
	{
		$this->root = TEMP_DIR . '/' . uniqid('gallery', true);
		$this->uploadDir = TEMP_DIR . '/uploads';
		@mkdir($this->uploadDir);
	}


	private function createStorage(bool $directThumbnails = false): FlysystemStorage
	{
		$router = new RouteList();
		$router->addRoute('files/thumbnail/<storage>/<path .+>/<thumbnail [^/]+>', 'Front:Files:thumbnail');

		return new FlysystemStorage(
			FilesystemFactory::create([
				'adapter' => FilesystemFactory::AdapterLocal,
				'root' => $this->root,
				'publicUrl' => '/foto/',
				'bucket' => null,
				'region' => 'eu-central-1',
				'prefix' => '',
				'endpoint' => null,
				'pathStyleEndpoint' => false,
				'key' => null,
				'secret' => null,
				'acl' => 'bucket-owner-full-control',
				'checksums' => 'when_supported',
			]),
			new AlbumNamingScheme(),
			new AllowedThumbnails(['x50', 'x192'], [], ['smallest' => 'x192']),
			new LinkGenerator($router, new UrlScript('https://example.com/')),
			new MemoryStorage(),
			name: 'gallery',
			directThumbnails: $directThumbnails,
		);
	}


	private function upload(FlysystemStorage $storage, string $album, string $name, string $contents): AlbumImage
	{
		$file = $storage->upload(TestImages::upload($this->uploadDir, $name, $contents), ['album' => $album]);
		Assert::type(AlbumImage::class, $file);
		assert($file instanceof AlbumImage);

		return $file;
	}


	public function testUploadKeepsAlbumAndFileName(): void
	{
		$storage = $this->createStorage();
		$file = $this->upload($storage, 'Výlet na Sněžku', 'DSC 01.JPG', TestImages::halves());

		Assert::same('Výlet na Sněžku/DSC 01.JPG', $storage->getOriginalPath($file));
		Assert::true(is_file($this->root . '/Výlet na Sněžku/DSC 01.JPG'), 'soubor na disku pod svým názvem');
		Assert::same([40, 20], [$file->getWidth(), $file->getHeight()]);
		Assert::same('/foto/V%C3%BDlet%20na%20Sn%C4%9B%C5%BEku/DSC%2001.JPG', $storage->link(ImageRequest::fromMacro($file)), 'části cesty se v URL kódují');
	}


	public function testThumbnailsOfImageLoadedFromDisk(): void
	{
		$storage = $this->createStorage();
		// fotka, která už na disku je - plugin ji načte výpisem alba, ne z DB
		mkdir($this->root . '/2025 #1', 0777, true);
		file_put_contents($this->root . '/2025 #1/DSC 02.JPG', TestImages::halves());
		$file = AlbumImage::create('2025 #1', 'DSC 02', 'JPG');
		$request = ImageRequest::fromMacro($file, ['x50']);

		Assert::same('https://example.com/files/thumbnail/gallery/2025%20%231/DSC%2002.JPG/x50', $storage->link($request), 'dokud náhled není, odkaz vede na generátor');
		Assert::type(StreamResponse::class, $storage->thumbnail('2025 #1/DSC 02.JPG', 'x50'));
		Assert::true(is_file($this->root . '/2025 #1/mini/DSC 02.JPG'), 'náhled ve složce mini pod názvem originálu');
		Assert::same('/foto/2025%20%231/mini/DSC%2002.JPG', $storage->link($request));

		$storage->thumbnail('2025 #1/DSC 02.JPG', 'x192');
		Assert::true(is_file($this->root . '/2025 #1/mini/DSC 02_x192.JPG'));
		Assert::same('/foto/2025%20%231/mini/DSC%2002_x192.JPG', $storage->link(ImageRequest::fromMacro($file, ['smallest'])), 'pojmenované rozměry');
		Assert::count(2, $storage->listThumbnails($file));

		Assert::exception(fn() => $storage->thumbnail('2025 #1/mini/DSC 02.JPG', 'x50'), InvalidThumbnailException::class, "%a%není originál v úložišti 'gallery'.");
		Assert::exception(fn() => $storage->thumbnail('../DSC 02.JPG', 'x50'), InvalidThumbnailException::class);
		Assert::exception(fn() => $storage->thumbnail('2025 #1/DSC 02.JPG', '300x300'), InvalidThumbnailException::class, "Náhled '300x300' není povolený.");
	}


	public function testUploadWithSameNameReplacesPhotoAndThumbnails(): void
	{
		$storage = $this->createStorage();
		$file = $this->upload($storage, 'A', 'DSC 01.JPG', TestImages::halves());
		$storage->thumbnail($storage->getOriginalPath($file), 'x50');
		Assert::count(1, $storage->listThumbnails($file));

		$again = $this->upload($storage, 'A', 'DSC 01.JPG', TestImages::halves(80, 40));

		Assert::same($storage->getOriginalPath($file), $storage->getOriginalPath($again));
		Assert::same(80, $storage->original($again)->getWidth(), 'originál se přepsal');
		Assert::same([], $storage->listThumbnails($again), 'staré náhledy se smazaly');
		Assert::contains('/files/thumbnail/gallery/', $storage->link(ImageRequest::fromMacro($again, ['x50'])), 'evidence náhledů se smazala');
	}


	public function testDirectThumbnailsAreCreatedOnTheirOwnUrl(): void
	{
		$storage = $this->createStorage(directThumbnails: true);
		mkdir($this->root . '/A', 0777, true);
		file_put_contents($this->root . '/A/DSC_1.JPG', TestImages::halves());
		file_put_contents($this->root . '/A/DSC_1_x192.JPG', TestImages::halves(20, 10)); // fotka, jejíž název vypadá jako náhled
		$file = AlbumImage::create('A', 'DSC_1', 'JPG');

		Assert::same('/foto/A/mini/DSC_1.JPG', $storage->link(ImageRequest::fromMacro($file, ['x50'])), 'odkaz přímo na náhled, i když ještě není');
		Assert::same('/foto/A/mini/DSC_1_x192.JPG', $storage->link(ImageRequest::fromMacro($file, ['smallest'])));

		// požadavek na adresu chybějícího náhledu (web server ho pošle do aplikace)
		Assert::type(StreamResponse::class, $storage->thumbnailFromPath('A/mini/DSC_1_x192.JPG'));
		Assert::same([384, 192], array_slice(getimagesize($this->root . '/A/mini/DSC_1_x192.JPG') ?: [], 0, 2), 'náhled x192 fotky DSC_1.JPG, ne x50 fotky DSC_1_x192.JPG');
		$storage->thumbnailFromPath('A/mini/DSC_1.JPG');
		Assert::same([100, 50], array_slice(getimagesize($this->root . '/A/mini/DSC_1.JPG') ?: [], 0, 2));

		// smazaný náhled vznikne znovu na stejné adrese
		unlink($this->root . '/A/mini/DSC_1.JPG');
		Assert::same('/foto/A/mini/DSC_1.JPG', $storage->link(ImageRequest::fromMacro($file, ['x50'])));
		$storage->thumbnailFromPath('A/mini/DSC_1.JPG');
		Assert::true(is_file($this->root . '/A/mini/DSC_1.JPG'));

		$message = "%a%není povolený náhled existujícího originálu v úložišti 'gallery'.";
		foreach (['A/DSC_1.JPG', 'A/mini/CHYBI.JPG', 'A/mini/DSC_1_300x300.JPG', '../A/mini/DSC_1.JPG', 'A/mini/x/DSC_1.JPG'] as $path) {
			Assert::exception(fn() => $storage->thumbnailFromPath($path), InvalidThumbnailException::class, $message);
		}
	}


	public function testLayoutDecidesWhatBelongsToStorage(): void
	{
		$storage = $this->createStorage();

		Assert::exception(
			fn() => $storage->upload(TestImages::upload($this->uploadDir, 'notes.txt', 'hello'), ['album' => 'A']),
			\InvalidArgumentException::class,
			'Do alba patří jen obrázky.',
		);
	}
}


/**
 * Fotka v albu: soubor `<album>/<název>.<přípona>`.
 */
final class AlbumImage extends ImageEntity
{
	public string $album;


	public static function create(string $album, string $name, string $extension): self
	{
		$image = new self();
		$image->album = $album;
		$image->setName($name);
		$image->setExtension($extension);
		$image->setMimeType('image/jpeg');

		return $image;
	}
}


/**
 * Alba jako adresáře: originál `<album>/<název>.<přípona>`, náhled výpisu alba `<album>/mini/<název>.<přípona>`,
 * ostatní náhledy `<album>/mini/<název>_<klíč náhledu>.<přípona>`.
 */
final class AlbumNamingScheme implements NamingScheme
{
	public function getOriginalPath(File $file): string
	{
		if (!$file instanceof AlbumImage) {
			throw new \LogicException('Album pracuje jen s AlbumImage.');
		}

		return $file->album . '/' . $file->getNameWithExtension();
	}


	public function isOriginalPath(string $path): bool
	{
		$parts = explode('/', $path);

		return count($parts) === 2 && array_intersect($parts, ['', '.', '..']) === [];
	}


	public function getThumbnailPath(string $originalPath, Thumbnail $thumbnail): string
	{
		[$album, $name] = self::parse($originalPath);
		if ($thumbnail->getKey() !== 'x50') {
			$name = pathinfo($name, PATHINFO_FILENAME) . '_' . $thumbnail->getKey() . '.' . pathinfo($name, PATHINFO_EXTENSION);
		}

		return $album . '/mini/' . $name;
	}


	public function parseThumbnailPath(string $path): array
	{
		$parts = explode('/', $path);
		if (count($parts) !== 3 || $parts[1] !== 'mini') {
			return [];
		}

		[$album, , $name] = $parts;
		$candidates = [];
		if (preg_match('~^(.+)_([^_./]+)\.([^.]+)$~D', $name, $matches)) {
			$candidates[] = ["$album/$matches[1].$matches[3]", $matches[2]];
		}

		$candidates[] = ["$album/$name", 'x50'];

		return $candidates;
	}


	public function listThumbnails(string $originalPath, FilesystemReader $filesystem): array
	{
		[$album, $name] = self::parse($originalPath);
		$pattern = '~^' . preg_quote(pathinfo($name, PATHINFO_FILENAME), '~') . '(_[^/]+)?\.' . preg_quote(pathinfo($name, PATHINFO_EXTENSION), '~') . '$~D';
		$paths = [];
		foreach ($filesystem->listContents($album . '/mini', false) as $item) {
			if ($item->isFile() && preg_match($pattern, basename($item->path()))) {
				$paths[] = $item->path();
			}
		}

		return $paths;
	}


	public function createFile(FileUpload $upload, bool $image, array $settings, callable $exists): File
	{
		$album = $settings['album'] ?? null;
		if (!$image || !is_string($album)) {
			throw new \InvalidArgumentException('Do alba patří jen obrázky.');
		}

		$file = new AlbumImage();
		$file->album = $album;
		$file->setName(pathinfo($upload->getUntrustedName(), PATHINFO_FILENAME));
		$file->setExtension(pathinfo($upload->getUntrustedName(), PATHINFO_EXTENSION));

		return $file;
	}


	/**
	 * @return array{string, string} album a název souboru
	 */
	private static function parse(string $originalPath): array
	{
		$parts = explode('/', $originalPath);
		if (count($parts) !== 2) {
			throw new \InvalidArgumentException("'$originalPath' není originál alba.");
		}

		return [$parts[0], $parts[1]];
	}
}


(new CustomNamingSchemeTest())->run();
