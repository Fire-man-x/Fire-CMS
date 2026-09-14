<?php
declare(strict_types=1);

namespace App\Components\FileManager;

use App\Components\FileManager\Exceptions\UploaderException;
use App\Components\FileManager\Files\IFile;
use App\Components\FileManager\Macro\IRequest;
use App\Components\FileManager\Storages\IStorage;
use Nette\Application\Responses\FileResponse;
use Nette\Http\FileUpload;
use Nette\Http\Response;
use Nette\SmartObject;
use Nette\Utils\Image;

/**
 * Image file storage
 *
 * - The images are stored in a regular files in the given `$directory`.
 * - The files are organized in a 2-level directory structure with maximum of 256² directories.
 * - The directory tree is well balanced thanks to the image hashes used for the directory path creation.
 * - The storage stores only one file even if the same image is stored multiple times, thus images should be
 *   deleted only after it is sure it is not referenced from other entities.
 * - The image thumbnails are created on demand and cached in the `$cacheDirectory`.
 */
class FileManager
{
	use SmartObject;

	/**
	 * The public accessible URL of the cache directory
	 *
	 */
	private IStorage $storage;


	/**
	 * Constructs the file manager from the given arguments.
	 *
	 */
	public function __construct(IStorage $storage)
	{
		$this->setStorage($storage);
	}


	/**
	 * File storage getter
	 */
	public function getStorage(): IStorage
	{
		return $this->storage;
	}


	/**
	 * File storage setter
	 */
	public function setStorage(IStorage $storage)
	{
		$this->storage = $storage;
	}


	/**
	 * Fetches the original image by the given image meta information.
	 *
	 */
	public function original(IFile $file): Image
	{
		return $this->storage->original($file);
	}


	/**
	 * Removes the image from the storage by the given image meta information.
	 *
	 */
	public function remove(IFile $file): FileManager
	{
		$this->storage->remove($file);

		return $this;
	}


	/**
	 * Checks if an image of the given meta information is stored in the storage.
	 *
	 */
	public function exist(IFile $file): bool
	{
		return $this->storage->exist($file);
	}


	/**
	 * Stores the given uploaded file.
	 *
	 * @throws UploaderException
	 */
	public function upload(FileUpload $upload, array $settings = array()): IFile
	{
		return $this->storage->upload($upload, $settings);
	}


	/**
	 * Returns the URL of the cached version of the image.
	 *
	 */
	public function link(IRequest $request): string
	{
		return $this->storage->link($request);
	}


	/**
	 * Creates the file download HTTP response which can be easily sent using the `send()` method.
	 *
	 */
	public function download(IRequest $request): Response|FileResponse
	{
		return $this->storage->download($request);
	}


	/**
	 * Creates the file download HTTP response which can be easily sent using the `send()` method.
	 *
	 */
	public function fetch(IRequest $request): Image
	{
		return $this->storage->fetch($request);
	}

}
