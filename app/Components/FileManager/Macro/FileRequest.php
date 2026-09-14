<?php
declare(strict_types=1);

namespace App\Components\FileManager\Macro;


use App\Components\FileManager\Files\IFile;
use Nette\SmartObject;

/**
 * File request encapsulation
 */
class FileRequest implements IRequest
{
	use SmartObject;

	/**
	 * The requested file file information.
	 *
	 */
	private IFile $file;


	/**
	 * Constructs the file request from the given file information, requested dimensions and flags.
	 *
	 */
	public function __construct(IFile $file)
	{
		$this->file = $file;
	}

	/**
	 * Creates the file request from the file macro arguments.
	 *
	 * @param array $args
	 * @return FileRequest
	 */
	public static function fromFile(IFile $file = null)
	{
		return new FileRequest($file);
	}

	public function getFile(): IFile
	{
		return $this->file;
	}


	/**
	 * @return IRequest
	 */
	public function setFile(IFile $file)
	{
		$this->file = $file;

		return $this;
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
