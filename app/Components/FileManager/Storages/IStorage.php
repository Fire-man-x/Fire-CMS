<?php
declare(strict_types=1);

namespace App\Components\FileManager\Storages;

use App\Components\FileManager\Files\File;
use App\Components\FileManager\Request\Request;
use Nette\Application\Responses\FileResponse;
use Nette\Http\FileUpload;
use Nette\Utils\Image;

/**
 * Image Storage Interface
 */
interface IStorage
{


	/**
	 * Returns true if the storage contains the given image.
	 */
	public function exist(File $file): bool;


	/**
	 * Returns the requested image encapsulated in a HTTP response object.
	 */
	public function download(Request $request): FileResponse;


	/**
	 * Creates the URL of the requested image thumbnail.
	 */
	public function link(Request $request): string;


	/**
	 * Fetches the original stored image.
	 */
	public function original(File $file): Image;


	/**
	 * Removes the requested image from the storage.
	 */
	public function remove(File $file): void;


	/**
	 * Uploads an image to the storage and store it's meta information in the given image entity.
	 */
	public function upload(FileUpload $upload, array $settings = array()): File;


}
