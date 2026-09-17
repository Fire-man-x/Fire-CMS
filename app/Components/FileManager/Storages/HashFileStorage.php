<?php
declare(strict_types=1);

namespace App\Components\FileManager\Storages;


use App\Components\FileManager\Exceptions\UploaderException;
use App\Components\FileManager\Files\Directory;
use App\Components\FileManager\Files\HashFileEntity;
use App\Components\FileManager\Files\HashImageEntity;
use App\Components\FileManager\Files\File;
use App\Components\FileManager\Files\HashFile;
use App\Components\FileManager\Request\FileRequest;
use App\Components\FileManager\Request\ImageRequest;
use App\Components\FileManager\Request\Request;
use Nette\Http\FileUpload;
use Nette\SmartObject;
use Nette\Utils\Finder;
use Nette\Utils\Image;
use Nette\Utils\ImageException;

/**
 * Image file storage
 *
 * - The images are stored in a regular files in the given `$directory`.
 * - The files are organized in a 2-level directory structure with maximum of 256² directories.
 * - The directory tree is well balanced thanks to the image hashes used for the directory path creation.
 * - The storage stores only one file even if the same image is stored multiple times, thus images should be
 *   deleted only after it is sure it is not referenced from other entities.
 * - The image thumbnails are created on demand and cached in the `$cacheDirectory`.
 *
 * @var string $cacheDirectory
 */
class HashFileStorage extends FileStorage
{
	use SmartObject;


	/**
	 * Constructs the image file storage from the given arguments.
	 */
	public function __construct(string $directory = "", string $cacheDirectory = '', string $baseUrl = '/cache/', bool $tryCreateDirectories = true, private ?\Nette\Http\Request $httpRequest = null){
		parent::__construct($directory, $cacheDirectory, $baseUrl, $tryCreateDirectories);
	}


	/**
	 * Returns the URL of the cached version of the image.
	 * @throws ImageException
	 */
	public function link(Request $request): string
	{
		if($request instanceof ImageRequest) {
			$this->createCacheImage($request);
			$fileName = $this->getCacheFileName($request);
			$hash = $request->getFile()->getHash();

			$append = '';
			if($this->httpRequest->isAjax()) {
				$append = '?v=' . date('Gis');
			}

			return $this->getBaseUrl() . "$hash[0]/$hash[1]/" . $fileName . $append;
		} elseif($request instanceof FileRequest) {
			return parent::link($request);
		} else {
			throw new \InvalidArgumentException("Request is not correct instance of IRequest");
		}
	}


	/**
	 * Removes the image from the storage by the given image file information.
	 */
	public function remove(File|HashFile $file): void
	{
		if(!$file instanceof HashFile){
			throw new \LogicException('File is not instance of IHashFile');
		}

		$this->removeCache($file);

		@unlink($this->getOriginalFilePathWithFileName($file));
	}


	/**
	 * Removes the image from the storage by the given image file information.
	 */
	public function removeCache(File|HashFile $file): void
	{
		if(!$file instanceof HashFile){
			throw new \LogicException('File is not instance of IHashFile');
		}
		$directory = str_replace($file->getHash(), "", $this->getCacheFilePath($file->getHash()));
		$findedFiles = Finder::findFiles($file->getHash() . "*")->in($this->cacheDirectory . "/" . $directory);
		try{
			//cache dirs could not exist
			foreach($findedFiles as $file){
				unlink($file);
			}
		}
		catch(\Exception $e){

		}
	}


	/**
	 * Stores the given uploaded file.
	 */
	public function upload(FileUpload $upload, array $settings = array()): HashImageEntity
	{
		if($upload->getError()) {
			throw new UploaderException($upload->getError());
		}
		$source = $upload->getTemporaryFile();

		if($upload->isImage()) {
			$file = new HashImageEntity();


			$imageSize = $upload->getImageSize();
			$file->setWidth($imageSize[0]);
			$file->setHeight($imageSize[1]);

			if(isset($settings["dimensions"])) {
				list($width, $height) = $this->processDimensions($settings["dimensions"]);
				if($file->getWidth() > $width || $file->getHeight() > $height) {
					$image = Image::fromFile($upload->getTemporaryFile());
					$image->resize($width, $height);
					$file->setWidth($image->getWidth());
					$file->setHeight($image->getHeight());
				}
			}
		} else {
			$file = new HashFileEntity();
		}

		$file->setHash($this->hash($source));
		$file->setName(pathinfo($upload->getUntrustedName(), PATHINFO_FILENAME));
		$file->setMimeType($upload->getContentType());
		$fileExtension = pathinfo($upload->getUntrustedName(), PATHINFO_EXTENSION);
		$file->setExtension($fileExtension == 'jpeg' ? 'jpg' : $fileExtension);
		$file->setSize($upload->getSize());

		//different hash
		while($this->exist($file)){
			$file->setHash(sha1(uniqid()));
		}

		//save
		$newPath = $this->getOriginalFilePathWithFileName($file);
		if(isset($image)) {
			$image->save($newPath);
			$file->setSize(filesize($newPath));
			unlink($upload->getTemporaryFile());
		} else {
			$upload->move($newPath);
		}

		//return
		return $file;
	}


	/**
	 * Creates the internal directory path from the given hash.
	 *
	 * Some special images like the "Image Not Available" image are stored
	 * in directories prefixed with an underscore. Those directories are not
	 * fragmented to hash based structure.
	 */
	protected function getOriginalFilePath(File|HashFile $file): string
	{
		if(!$file instanceof HashFile){
			throw new \LogicException('File is not instance of IHashFile');
		}
		$hash = $file->getHash();
		$path = $this->directory . '/' . "$hash[0]/$hash[1]";
		new Directory($path);

		return $path;
	}


	/**
	 * Returns the part of the image filename relative to the cache directory.
	 */
	protected function getCacheFilePathWithFileName(ImageRequest $imageRequest): string
	{
		return $this->getCacheFilePath($imageRequest) . "/" . $this->getCacheFileName($imageRequest);
	}


	/**
	 * Creates the internal directory path from the given hash.
	 *
	 * Some special images like the "Image Not Available" image are stored
	 * in directories prefixed with an underscore. Those directories are not
	 * fragmented to hash based structure.
	 */
	protected function getCacheFilePath(ImageRequest $imageRequest): string{
		$hash = $imageRequest->getFile()->getHash();
		return "$this->cacheDirectory/$hash[0]/$hash[1]";
	}


	/**
	 * Returns the file name of the cached version of the image.
	 * @return string The file name of the cached version of the image
	 */
	protected function getCacheFileName(ImageRequest $imageRequest): string
	{
		$dimensions = $imageRequest->getDimensions();
		$flags = $imageRequest->getFlags();
		$crop = (int)$imageRequest->getCrop();

		$fileName = $imageRequest->getFile()->getHash();
		$fileExtension = $imageRequest->getFile()->getExtension();

		return "$fileName.$dimensions.$flags.$crop.$fileExtension";
	}


	/**
	 * Computes the SHA1 hash for the given file.
	 *
	 * @param string $filename The file to compute the hash from
	 * @return string The SHA1 hash of a file
	 */
	private function hash(string $filename): string
	{
		return sha1_file($filename);
	}

}
