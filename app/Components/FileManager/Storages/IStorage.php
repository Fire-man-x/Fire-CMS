<?php
declare(strict_types=1);

namespace App\Components\FileManager\Storages;

use App\Components\FileManager\Files\IFile;
use App\Components\FileManager\Macro\IRequest;
use Nette\Http\FileUpload;
use Nette\Http\Response;
use Nette\Utils\Image;

/**
 * Image Storage Interface
 */
interface IStorage
{


	/**
	 * Returns true if the storage contains the given image.
	 *
	 * @param IFile $file
	 * @return boolean
	 */
	public function exist(IFile $file);


	/**
	 * Returns the requested image encapsulated in a HTTP response object.
	 *
	 * @param  IRequest $request
	 * @return Response
	 */
	public function download(IRequest $request);


	/**
	 * Creates the URL of the requested image thumbnail.
	 *
	 * @param  IRequest $request
	 * @return string
	 */
	public function link(IRequest $request);


	/**
	 * Fetches the original stored image.
	 *
	 * @param IFile $file
	 * @return Image
	 */
	public function original(IFile $file);


	/**
	 * Removes the requested image from the storage.
	 *
	 * @param IFile $file
	 * @return void
	 */
	public function remove(IFile $file);


	/**
	 * Uploads an image to the storage and store it's meta information in the given image entity.
	 *
	 * @param FileUpload $upload
	 * @return IFile
	 */
	public function upload(FileUpload $upload, array $settings = array());


}
