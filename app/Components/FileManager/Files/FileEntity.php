<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;

/**
 * Basic implementation of the file information
 */
class FileEntity implements IFile
{

	/**
	 * File name
	 *
	 */
	private string $name;


	/**
	 * File extension.
	 *
	 */
	private string $extension;


	/**
	 * Mime type
	 *
	 */
	private string $mimeType;


	/**
	 * Original image size
	 *
	 */
	private int $size;


	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * @param  string $name
	 * @return FileEntity
	 */
	public function setName($name)
	{
		$this->name = $name;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getExtension()
	{
		return $this->extension;
	}


	/**
	 * @param  string $extension
	 * @return FileEntity
	 */
	public function setExtension($extension)
	{
		$this->extension = $extension;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getNameWithExtension()
	{
		return $this->getName().'.'.$this->getExtension();
	}


	/**
	 * @return string
	 */
	public function getMimeType()
	{
		return $this->mimeType;
	}


	/**
	 * @param  integer $mimeType
	 * @return FileEntity
	 */
	public function setMimeType($mimeType)
	{
		$this->mimeType = $mimeType;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getSize()
	{
		return $this->size;
	}


	/**
	 * @param  int $size
	 * @return FileEntity
	 */
	public function setSize($size)
	{
		$this->size = $size;

		return $this;
	}

}
