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


	/**
	 * @param  string $mimeType
	 * @return ImageEntity
	 */
	public function setMimeType($mimeType)
	{
		parent::setMimeType($this->checkMimeType($mimeType));

		return $this;
	}


	/**
	 * Checks the given image type to be one of the supported types.
	 *
	 * @param  string $mimeType
	 * @return string
	 * @throws ImageTypeException
	 */
	protected function checkMimeType($mimeType)
	{
		if (!in_array($mimeType, $this->mimeTypes, true)) {
			throw new ImageTypeException($mimeType);
		}

		return $mimeType;
	}


	/**
	 * @return integer
	 */
	public function getTranslatedMimeType()
	{
		return $this->mimeTypesTranslator[parent::getMimeType()];
	}


	/**
	 * @return integer
	 */
	public function getHeight()
	{
		return $this->height;
	}


	/**
	 * @param integer $height
	 */
	public function setHeight($height)
	{
		$this->height = $height;
	}


	/**
	 * @return integer
	 */
	public function getWidth()
	{
		return $this->width;
	}


	/**
	 * @param integer $width
	 */
	public function setWidth($width)
	{
		$this->width = $width;
	}
}
