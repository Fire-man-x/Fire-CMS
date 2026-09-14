<?php
declare(strict_types=1);

namespace App\Components\FileManager\Storages;


use App\Components\FileManager\Exceptions\UploaderException;
use App\Components\FileManager\Files\Directory;
use App\Components\FileManager\Files\HashFileEntity;
use App\Components\FileManager\Files\HashImageEntity;
use App\Components\FileManager\Files\IFile;
use App\Components\FileManager\Files\IHashFile;
use App\Components\FileManager\Macro\ImageRequest;
use App\Components\FileManager\Macro\IRequest;
use Nette\Http\FileUpload;
use Nette\Http\Request;
use Nette\SmartObject;
use Nette\Utils\Finder;
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
 *
 * @var string $cacheDirectory
 */
class HashFileStorage extends FileStorage
{
	use SmartObject;

	private Request $httpRequest;


	/**
	 * Constructs the image file storage from the given arguments.
	 *
	 * @param string $directory The directory to store the images in
	 * @param string $cacheDirectory
	 * @param string $baseUrl
	 * @param boolean $tryCreateDirectories
	 * @param Request $request
	 */
	public function __construct($directory = "", $cacheDirectory = '', $baseUrl = '/cache/', $tryCreateDirectories = true, Request $request = null){
		parent::__construct($directory, $cacheDirectory, $baseUrl, $tryCreateDirectories);

		$this->httpRequest = $request;
	}


	/**
	 * Returns the URL of the cached version of the image.
	 *
	 * @param IRequest $request The image request
	 * @return string  The URL of the image
	 */
	public function link(IRequest $request){
		$this->createCacheImage($request);
		$hash = $request->getFile()->getHash();
		$fileName = $this->getCacheFileName($request);

		$append = '';
		if($this->httpRequest->isAjax()) {
			$append = '?v=' . date('Gis');
		}

		return $this->getBaseUrl() . "$hash[0]/$hash[1]/" . $fileName . $append;
	}


	/**
	 * Removes the image from the storage by the given image file information.
	 *
	 * @param IFile $file The image information
	 * @return HashFileStorage Fluent interface
	 */
	public function remove(IFile $file){
		$this->removeCache($file);

		@unlink($this->getOriginalFilePathWithFileName($file));

		return $this;
	}


	/**
	 * Removes the image from the storage by the given image file information.
	 *
	 * @param IFile $file The image information
	 * @return HashFileStorage Fluent interface
	 */
	public function removeCache(IFile $file){
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

		return $this;
	}


	/**
	 * Stores the given uploaded file.
	 *
	 * @param FileUpload $upload
	 * @return IFile File
	 * @throws UploaderException
	 */
	public function upload(FileUpload $upload, array $settings = array()){
		if($upload->getError()) {
			throw new UploaderException($upload->getError());
		}
		$source = $upload->getTemporaryFile();

		$file = null;
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
	 *
	 * @param IFile|IHashFile $file The image file information
	 * @return string
	 */
	protected function getOriginalFilePath(IFile $file){
		$hash = $file->getHash();
		$path = $this->directory . '/' . "$hash[0]/$hash[1]";
		new Directory($path);

		return $path;
	}


	/**
	 * Returns the part of the image filename relative to the cache directory.
	 *
	 * @param ImageRequest $imageRequest The image request
	 * @return string
	 */
	protected function getCacheFilePathWithFileName(ImageRequest $imageRequest){
		return $this->getCacheFilePath($imageRequest->getFile()->getHash()) . "/" . $this->getCacheFileName($imageRequest);
	}


	/**
	 * Creates the internal directory path from the given hash.
	 *
	 * Some special images like the "Image Not Available" image are stored
	 * in directories prefixed with an underscore. Those directories are not
	 * fragmented to hash based structure.
	 *
	 * @param string $hash Tha SHA1 hash
	 * @return string
	 */
	protected function getCacheFilePath($hash){
		return "$this->cacheDirectory/$hash[0]/$hash[1]";
	}


	/**
	 * Returns the file name of the cached version of the image.
	 *
	 * @param ImageRequest $imageRequest
	 * @return string The file name of the cached version of the image
	 */
	protected function getCacheFileName(ImageRequest $imageRequest){
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
	private function hash($filename){
		return sha1_file($filename);
	}

}
