<?php
declare(strict_types=1);

namespace App\FileStorage\Storages;

use App\FileStorage\Files\File;
use App\FileStorage\Request\Request;
use Nette\Application\Response;
use Nette\Http\FileUpload;
use Nette\Utils\Image;

/**
 * Image Storage Interface
 *
 * Výchozí implementace je FlysystemStorage (lokální disk i S3). FileStorage/HashFileStorage jsou původní
 * implementace jen pro lokální disk - zůstávají kvůli pluginům, které z nich dědí.
 */
interface IStorage
{


	/**
	 * Returns true if the storage contains the given image.
	 */
	public function exist(File $file): bool;


	/**
	 * Returns the requested image encapsulated in a HTTP response object.
	 * (FileResponse u lokálních úložišť, StreamResponse/RedirectResponse u FlysystemStorage)
	 */
	public function download(Request $request): Response;


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
	 * @param array<string, mixed> $settings
	 */
	public function upload(FileUpload $upload, array $settings = array()): File;


}
