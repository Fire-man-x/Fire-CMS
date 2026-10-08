<?php
declare(strict_types=1);

namespace App\FileStorage\Request;


use App\FileStorage\Files\File;
use Nette\SmartObject;

/**
 * File request encapsulation - libovolný soubor (File), i obrázek správce souborů (HashImageEntity)
 */
class FileRequest implements Request
{
	use SmartObject;

	/**
	 * The requested file information.
	 *
	 */
	private File $file;


	/**
	 * Constructs the file request from the given file information, requested dimensions and flags.
	 *
	 */
	public function __construct(File $file)
	{
		$this->file = $file;
	}

	/**
	 * Creates the file request from the file macro arguments.
	 */
	public static function fromFile(File $file): self
	{
		return new FileRequest($file);
	}

	public function getFile(): File
	{
		return $this->file;
	}


	public function setFile(File $file): void
	{
		$this->file = $file;
	}

	public function getCrop(): bool
	{
		throw new \BadFunctionCallException("Function 'crop' is not implemented in FileRequest");
	}


	public function getDimensions(): string
	{
		throw new \BadFunctionCallException("Function 'dimensions' is not implemented in FileRequest");
	}


	public function getFlags(): int
	{
		throw new \BadFunctionCallException("Function 'flags' is not implemented in FileRequest");
	}

}
