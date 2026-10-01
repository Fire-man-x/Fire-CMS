<?php

declare(strict_types=1);

namespace Tests\Components\FileManager;

use App\Components\FileManager\Files\HashImageEntity;
use App\Components\FileManager\Flysystem\FilesystemFactory;
use App\Components\FileManager\Request\ImageRequest;
use App\Components\FileManager\Responses\StreamResponse;
use App\Components\FileManager\Storages\FlysystemStorage;
use App\Components\FileManager\Thumbnails\AllowedThumbnails;
use League\Flysystem\Filesystem;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\RouteList;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Http\UrlScript;
use Nette\Utils\Image;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;
use Tests\Helpers\TestImages;

require_once __DIR__ . '/../../bootstrap.php';

/**
 * FlysystemStorage proti skutečnému S3 (AWS nebo kompatibilní úložiště, např. Garage z docker/). Bez
 * proměnných prostředí se přeskočí, `composer test` tak nepotřebuje síť. Spuštění:
 *
 *   FILEMANAGER_S3_BUCKET=muj-bucket FILEMANAGER_S3_ENDPOINT=http://localhost:3900 \
 *   FILEMANAGER_S3_KEY=GK... FILEMANAGER_S3_SECRET=... composer test -- tests/Components/FileManager/S3StorageTest.phpt
 *
 * Volitelně FILEMANAGER_S3_REGION (výchozí eu-central-1) a FILEMANAGER_S3_CHECKSUMS (when_supported / when_required).
 * Test pracuje pod náhodným prefixem `fire-cms-test/...` a na konci ho smaže.
 */
final class S3StorageTest extends TestCase
{
	private Filesystem $filesystem;

	private string $uploadDir;


	protected function setUp(): void
	{
		$bucket = getenv('FILEMANAGER_S3_BUCKET');
		if (!is_string($bucket) || $bucket === '') {
			Environment::skip('Nastavte FILEMANAGER_S3_BUCKET (a další FILEMANAGER_S3_*) pro test proti S3.');
		}

		$endpoint = getenv('FILEMANAGER_S3_ENDPOINT');
		$key = getenv('FILEMANAGER_S3_KEY');
		$secret = getenv('FILEMANAGER_S3_SECRET');
		$region = getenv('FILEMANAGER_S3_REGION');
		$checksums = getenv('FILEMANAGER_S3_CHECKSUMS');

		$this->filesystem = FilesystemFactory::create([
			'adapter' => FilesystemFactory::AdapterS3,
			'root' => null,
			'publicUrl' => 'https://cdn.example.com/',
			'bucket' => $bucket,
			'region' => is_string($region) && $region !== '' ? $region : 'eu-central-1',
			'prefix' => 'fire-cms-test/' . bin2hex(random_bytes(6)),
			'endpoint' => is_string($endpoint) && $endpoint !== '' ? $endpoint : null,
			'pathStyleEndpoint' => is_string($endpoint) && $endpoint !== '',
			'key' => is_string($key) && $key !== '' ? $key : null,
			'secret' => is_string($secret) ? $secret : null,
			'acl' => 'bucket-owner-full-control',
			'checksums' => is_string($checksums) && $checksums !== '' ? $checksums : 'when_supported',
		]);
		$this->uploadDir = TEMP_DIR . '/uploads';
		@mkdir($this->uploadDir);
	}


	protected function tearDown(): void
	{
		if (isset($this->filesystem)) {
			$this->filesystem->deleteDirectory('');
		}
	}


	public function testStorageOnS3(): void
	{
		$router = new RouteList();
		$router->addRoute('files/thumbnail/<hash>/<thumbnail>', 'Front:Files:thumbnail');
		$storage = new FlysystemStorage(
			$this->filesystem,
			new AllowedThumbnails(['10x10'], ['8x8']),
			new LinkGenerator($router, new UrlScript('https://example.com/')),
			new MemoryStorage(),
		);

		$file = $storage->upload(TestImages::upload($this->uploadDir, 'photo.jpg', TestImages::withExifOrientation(TestImages::halves(), 6)));
		Assert::type(HashImageEntity::class, $file);
		assert($file instanceof HashImageEntity);
		Assert::true($storage->exist($file));
		Assert::same([20, 40], [$file->getWidth(), $file->getHeight()], 'narovnání při uploadu');
		Assert::same('image/jpeg', $this->filesystem->mimeType($storage->getOriginalPath($file)), 'Content-Type objektu v S3');

		// náhledy na vyžádání
		Assert::contains('/files/thumbnail/', $storage->link(ImageRequest::fromMacro($file, ['10x10'])));
		Assert::type(StreamResponse::class, $storage->thumbnail($file, '10x10'));
		$storage->thumbnail($file, 'crop-8x8');
		Assert::same(
			'https://cdn.example.com/' . FlysystemStorage::CacheDirectory . '/' . $file->getHash()[0] . '/' . $file->getHash()[1] . '/' . $file->getHash() . '.10x10.jpg',
			$storage->link(ImageRequest::fromMacro($file, ['10x10'])),
		);
		Assert::count(2, $storage->listThumbnails($file));

		// smazání náhledů podle prefixu (DeleteObjects)
		$storage->removeCache($file);
		Assert::same([], $storage->listThumbnails($file));

		// úprava originálu = přepsání objektu
		$storage->modifyOriginal($file, fn(Image $image) => $image->resize(10, null));
		Assert::same(10, $storage->original($file)->getWidth());

		$storage->remove($file);
		Assert::false($storage->exist($file));
	}
}

(new S3StorageTest())->run();
