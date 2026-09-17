<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


use App\Components\FileManager\Exceptions\ImageTypeException;
use Nette\Utils\Image;

/**
 * Basic implementation of the image meta information as Doctrine entity.
 */
class ImageEntity extends FileEntity
{

	private int $width;

	private int $height;

	/**
	 * The image type -> MIME type map
	 *
	 */
	protected array $mimeTypes = array(Image::JPEG => 'image/jpeg', Image::PNG => 'image/png', Image::GIF => 'image/gif');

	/**
	 * The image type -> file extension map
	 *
	 */
	private array $mimeTypesTranslator = array('image/jpeg' => Image::JPEG, 'image/png' => Image::PNG, 'image/gif' => Image::GIF);


	public function setMimeType(string $mimeType): void
	{
		parent::setMimeType($this->checkMimeType($mimeType));
	}


	/**
	 * Checks the given image type to be one of the supported types.
	 *
	 * @throws ImageTypeException
	 */
	protected function checkMimeType(string $mimeType): string
	{
		if (!in_array($mimeType, $this->mimeTypes, true)) {
			throw new ImageTypeException((int) $mimeType);
		}

		return $mimeType;
	}


	public function getTranslatedMimeType(): int
	{
		return $this->mimeTypesTranslator[parent::getMimeType()];
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
