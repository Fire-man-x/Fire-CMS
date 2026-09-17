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


	public function getName(): string
	{
		return $this->name;
	}


	public function setName(string $name): void
	{
		$this->name = $name;
	}


	public function getExtension(): string
	{
		return $this->extension;
	}


	public function setExtension(string $extension): void
	{
		$this->extension = $extension;
	}


	public function getNameWithExtension(): string
	{
		return $this->getName().'.'.$this->getExtension();
	}


	public function getMimeType(): string
	{
		return $this->mimeType;
	}


	public function setMimeType(string $mimeType): void
	{
		$this->mimeType = $mimeType;
	}


	public function getSize(): int
	{
		return $this->size;
	}


	public function setSize(int $size): void
	{
		$this->size = $size;
	}

}
