<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


use Nette\Utils\Image;

/**
 * "Image not found" entity
 */
class EmptyHashImage extends HashImageEntity
{

	public function getHash()
	{
		return '_empty';
	}


	public function getMimeType()
	{
		return Image::PNG;
	}


	public function setHash($hash)
	{
		throw new \LogicException('Unavailable image hash cannot be changed.');
	}


	public function setMimeType($mimeType): void
	{
		throw new \LogicException('Unavailable image hash cannot be changed.');
	}


	public function getHeight()
	{
		return 1080;
	}


	public function getWidth()
	{
		return 1920;
	}

}
