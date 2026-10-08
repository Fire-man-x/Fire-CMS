<?php

declare(strict_types=1);

namespace Tests\FileStorage;

use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Files\ImageEntity;
use App\FileStorage\Request\ImageRequest;
use App\FileStorage\Thumbnails\Thumbnail;
use App\FileStorage\Thumbnails\AllowedThumbnails;
use Nette\Utils\Image;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Náhledy obrázků: zápis rozměrů ze šablon, klíč v URL a seznam povolených náhledů z neonu.
 */
final class ThumbnailTest extends TestCase
{
	public function testDimensionsFromTemplates(): void
	{
		Assert::same('300x200', Thumbnail::fromDimensions('300x200')->getKey());
		Assert::same('945x', Thumbnail::fromDimensions('945x')->getKey());
		Assert::same('x50', Thumbnail::fromDimensions('x50')->getKey());
		Assert::same('210x210', Thumbnail::fromDimensions('210')->getKey(), 'jedno číslo = čtverec jako dřív');
		Assert::same('crop-130x130', Thumbnail::fromDimensions('130x130', true)->getKey());
		Assert::same('300x200-f1', Thumbnail::fromDimensions('300x200', false, Image::ShrinkOnly)->getKey());
		Assert::same('130x130', Thumbnail::fromDimensions('130x130', true)->getConfigEntry());
	}


	public function testInvalidDimensions(): void
	{
		foreach (['smallest', 'x', '0x100', '100x100x100', '-5x10', '20000x10'] as $dimensions) {
			Assert::exception(fn() => Thumbnail::fromDimensions($dimensions), InvalidThumbnailException::class);
		}

		Assert::exception(fn() => Thumbnail::fromDimensions('130x', true), InvalidThumbnailException::class, 'Ořez (crop) vyžaduje šířku i výšku.');
		Assert::exception(fn() => Thumbnail::fromDimensions('130x', false, Image::Cover), InvalidThumbnailException::class);
		Assert::exception(fn() => Thumbnail::fromDimensions('130x130', false, 64), InvalidThumbnailException::class);
	}


	public function testKeyFromUrlMustBeCanonical(): void
	{
		Assert::same('crop-130x130', Thumbnail::fromKey('crop-130x130')?->getKey());
		Assert::same('300x200-f1', Thumbnail::fromKey('300x200-f1')?->getKey());
		Assert::null(Thumbnail::fromKey('0300x200'), 'jeden náhled nesmí mít víc adres');
		Assert::null(Thumbnail::fromKey('300x200-f0'));
		Assert::null(Thumbnail::fromKey('300x200.jpg'));
		Assert::null(Thumbnail::fromKey('../../etc'));
	}


	public function testWhitelist(): void
	{
		$allowed = new AllowedThumbnails(['100x100', 945, '300x200-f1', 'x50'], ['130x130']);

		Assert::true($allowed->isAllowed(Thumbnail::fromDimensions('100x100')));
		Assert::true($allowed->isAllowed(Thumbnail::fromDimensions('945')), 'číslo z neonu = čtverec');
		Assert::true($allowed->isAllowed(Thumbnail::fromDimensions('300x200', false, Image::ShrinkOnly)));
		Assert::true($allowed->isAllowed(Thumbnail::fromDimensions('130x130', true)));
		Assert::false($allowed->isAllowed(Thumbnail::fromDimensions('130x130')), 'zmenšení a ořez jsou různé náhledy');
		Assert::false($allowed->isAllowed(Thumbnail::fromDimensions('101x100')));
		Assert::same('crop-130x130', $allowed->get('crop-130x130')?->getKey());
		Assert::null($allowed->get('101x100'));

		Assert::exception(
			fn() => new AllowedThumbnails(['300xx']),
			InvalidThumbnailException::class,
			"thumbnails.resize: Neplatné rozměry náhledu '300xx'%a%",
		);
	}


	public function testAliases(): void
	{
		$allowed = new AllowedThumbnails(['x192', '300x200-f1'], ['130x130'], ['smallest' => 'x192', 'square' => 130, 'small' => '300x200']);
		$image = new ImageEntity();

		Assert::same('x192', $allowed->fromRequest(ImageRequest::fromMacro($image, ['smallest']))->getKey());
		Assert::same('crop-130x130', $allowed->fromRequest(ImageRequest::crop($image, ['square']))->getKey(), 'alias pro ořez');
		Assert::same('300x200-f1', $allowed->fromRequest(ImageRequest::fromMacro($image, ['small', Image::ShrinkOnly]))->getKey(), 'příznaky z makra');
		Assert::same('100x100', $allowed->fromRequest(ImageRequest::fromMacro($image, ['100x100']))->getKey(), 'rozměry bez aliasu');
		Assert::false($allowed->isAllowed($allowed->fromRequest(ImageRequest::fromMacro($image, ['square']))), 'alias neplatí za povolení');

		Assert::exception(
			fn() => new AllowedThumbnails([], [], ['smallest' => 'nejmenší']),
			InvalidThumbnailException::class,
			"thumbnails.aliases.smallest: Neplatné rozměry náhledu 'nejmenší'%a%",
		);
	}


	public function testApply(): void
	{
		$resized = Thumbnail::fromDimensions('20x')->apply(Image::fromBlank(40, 20));
		Assert::same([20, 10], [$resized->getWidth(), $resized->getHeight()]);

		$cropped = Thumbnail::fromDimensions('10x10', true)->apply(Image::fromBlank(40, 20));
		Assert::same([10, 10], [$cropped->getWidth(), $cropped->getHeight()]);
	}
}

(new ThumbnailTest())->run();
