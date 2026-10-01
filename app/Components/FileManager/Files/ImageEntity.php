<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


use App\Components\FileManager\Exceptions\ImageTypeException;
use Nette\Utils\Image;
use Nette\Utils\ImageType;

/**
 * Basic implementation of the image meta information as Doctrine entity.
 */
class ImageEntity implements File
{
	use BaseEntityTrait {
		setMimeType as traitSetMimeType;
	}

	private int $width;

	private int $height;

	/**
	 * The image type -> MIME type map
	 * @var array<int,string>
	 */
	protected array $mimeTypes = array(Image::JPEG => 'image/jpeg', Image::PNG => 'image/png', Image::GIF => 'image/gif');


	/**
	 * @throws ImageTypeException
	 */
	public function setMimeType(string $mimeType): void
	{
		$this->checkMimeType($mimeType);
		$this->traitSetMimeType($mimeType);
	}


	/**
	 * Checks the given image type to be one of the supported types.
	 *
	 * @throws ImageTypeException
	 */
	protected function checkMimeType(string $mimeType): void
	{
		$mimeType = str_replace("image/", "", $mimeType);
		$type = Image::extensionToType($mimeType);
		if(!Image::isTypeSupported($type)){
			throw new ImageTypeException((int) $mimeType);
		}
	}

	/**
	 * @return ImageType::*
	 */
	public function getTranslatedMimeType(): int
	{
		$mimeType = str_replace("image/", "", $this->getMimeType());
		return Image::extensionToType($mimeType);
	}


	public function getHeight(): int
	{
		return $this->height;
	}


	public function setHeight(int $height): void
	{
		$this->height = $height;
	}


	public function getWidth(): int
	{
		return $this->width;
	}


	public function setWidth(int $width): void
	{
		$this->width = $width;
	}
}
