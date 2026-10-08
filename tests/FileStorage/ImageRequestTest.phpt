<?php

declare(strict_types=1);

namespace Tests\FileStorage;

use App\FileStorage\FileManager;
use App\FileStorage\Files\File;
use App\FileStorage\Files\FileEntity;
use App\FileStorage\Files\ImageEntity;
use App\FileStorage\Macro\ImageMacro;
use App\FileStorage\Request\ImageRequest;
use App\FileStorage\Request\Request;
use App\FileStorage\Storages\IStorage;
use App\FileStorage\Thumbnails\Thumbnail;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Nette\Application\Response;
use Nette\Http\FileUpload;
use Nette\Utils\Image;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * ImageRequest s obrázkem mimo správce souborů (ImageEntity bez id a hashe) - pro pluginy, které čtou
 * obrázky přímo z disku a mají vlastní úložiště (IStorage).
 */
final class ImageRequestTest extends TestCase
{
	public function testAcceptsImageWithoutHash(): void
	{
		$image = self::image('photo');

		$request = ImageRequest::fromMacro($image, ['300x200']);
		Assert::same($image, $request->getFile());
		Assert::same('300x200', Thumbnail::fromRequest($request)->getKey());

		$crop = ImageRequest::crop($image, ['130x130']);
		Assert::same($image, $crop->getFile());
		Assert::same('crop-130x130', Thumbnail::fromRequest($crop)->getKey());

		$other = self::image('other');
		$request->setFile($other);
		Assert::same($other, $request->getFile());
	}


	public function testRejectsFileThatIsNotImage(): void
	{
		$request = ImageRequest::fromMacro(self::image('photo'));

		Assert::exception(fn() => $request->setFile(new FileEntity()), \LogicException::class, 'File is not instance of ImageEntity.');
	}


	public function testMacrosWithCustomStorage(): void
	{
		$latte = new Engine();
		$latte->setLoader(new StringLoader());
		$latte->addExtension(new ImageMacro());

		$html = $latte->renderToString(
			'<img n:src="$image, \'300x200\'"> {image $image} <img n:crop="$image, \'130x130\'">',
			['image' => self::image('photo'), '__imagestore' => new FileManager(self::linkOnlyStorage())],
		);

		Assert::same('<img src="/foto/300x200/photo.jpg"> /foto/original/photo.jpg <img src="/foto/crop-130x130/photo.jpg">', $html);
	}


	private static function image(string $name): ImageEntity
	{
		$image = new ImageEntity();
		$image->setName($name);
		$image->setExtension('jpg');
		$image->setMimeType('image/jpeg');
		$image->setWidth(640);
		$image->setHeight(480);

		return $image;
	}


	/**
	 * Úložiště, které z obrázku potřebuje jen název - jako úložiště pluginu nad soubory na disku.
	 */
	private static function linkOnlyStorage(): IStorage
	{
		return new class implements IStorage {
			public function exist(File $file): bool
			{
				return true;
			}


			public function download(Request $request): Response
			{
				throw new \LogicException('Not implemented.');
			}


			public function link(Request $request): string
			{
				$key = $request instanceof ImageRequest && $request->getDimensions() !== Request::ORIGINAL
					? Thumbnail::fromRequest($request)->getKey()
					: 'original';

				return '/foto/' . $key . '/' . $request->getFile()->getNameWithExtension();
			}


			public function original(File $file): Image
			{
				throw new \LogicException('Not implemented.');
			}


			public function remove(File $file): void
			{
			}


			public function upload(FileUpload $upload, array $settings = []): File
			{
				throw new \LogicException('Not implemented.');
			}
		};
	}
}

(new ImageRequestTest())->run();
