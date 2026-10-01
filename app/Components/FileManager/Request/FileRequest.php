<?php
declare(strict_types=1);

namespace App\Components\FileManager\Request;


use App\Components\FileManager\Files\File;
use App\Components\FileManager\Files\FileEntity;
use Nette\SmartObject;

/**
 * File request encapsulation
 */
class FileRequest implements Request
{
	use SmartObject;

	/**
	 * The requested file information.
	 *
	 */
	private FileEntity $file;


	/**
	 * Constructs the file request from the given file information, requested dimensions and flags.
	 *
	 */
	public function __construct(FileEntity $file)
	{
		$this->file = $file;
	}

	/**
	 * Creates the file request from the file macro arguments.
	 */
	public static function fromFile(FileEntity $file = null): self
	{
		return new FileRequest($file);
	}

	public function getFile(): FileEntity
	{
		return $this->file;
	}


	public function setFile(File|FileEntity $file): void
	{
		if(!$file instanceof FileEntity){
			throw new \LogicException('File is not instance of FileEntity.');
		}

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
