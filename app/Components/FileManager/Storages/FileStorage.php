<?php
declare(strict_types=1);

namespace App\Components\FileManager\Storages;


use App\Components\FileManager\Exceptions\InvalidCacheDirectoryException;
use App\Components\FileManager\Exceptions\UploaderException;
use App\Components\FileManager\Files\Directory;
use App\Components\FileManager\Files\FileEntity;
use App\Components\FileManager\Files\IFile;
use App\Components\FileManager\Files\ImageEntity;
use App\Components\FileManager\Macro\FileRequest;
use App\Components\FileManager\Macro\ImageRequest;
use App\Components\FileManager\Macro\IRequest;
use Nette\Application\Responses\FileResponse;
use Nette\Http\FileUpload;
use Nette\Http\Request;
use Nette\SmartObject;
use Nette\Utils\Finder;
use Nette\Utils\Image;
use Nette\Utils\Strings;

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
class FileStorage implements IStorage
{
	use SmartObject;

	/**
	 * The directory to store the images in
	 *
	 */
	protected Directory $directory;

	/**
	 * The cache directory
	 *
	 */
	protected Directory $cacheDirectory;

	/**
	 * The public accessible URL of the cache directory
	 *
	 */
	private string $baseUrl;


	public function __construct(string $directory = "", string $cacheDirectory = '', string $baseUrl = '/cache/', bool $tryCreateDirectories = true){
		$this->directory = new Directory($directory, $tryCreateDirectories);
		$this->cacheDirectory = new Directory($cacheDirectory, $tryCreateDirectories);

		if($this->directory->is($this->cacheDirectory)) {
			throw new InvalidCacheDirectoryException($cacheDirectory);
		}

		$this->setBaseUrl($baseUrl);
	}


	/**
	 * Returns the public accessible cache directory URL.
	 *
	 * @return string
	 */
	public function getBaseUrl(){
		return $this->baseUrl;
	}


	/**
	 * Sets the public accessible cache directory URL.
	 *
	 * @param string $baseUrl
	 * @return $this
	 */
	protected function setBaseUrl($baseUrl){
		if(!Strings::endsWith($baseUrl, '/')) {
			$baseUrl .= '/';
		}
		$this->baseUrl = $baseUrl;

		return $this;
	}


	/**
	 * Checks if an image of the given file information is stored in the storage.
	 *
	 * @param IFile $file The image file information
	 * @return boolean true if image is present in the storage or false otherwise
	 */
	public function exist(IFile $file){
		return file_exists($this->getOriginalFilePathWithFileName($file));
	}


	/**
	 * Fetches the original image by the given image file information.
	 *
	 * @param IFile $file The stored image file information
	 * @return Image The stored image
	 */
	public function original(IFile $file){
		return Image::fromFile($this->getOriginalFilePathWithFileName($file));
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
			throw new UploaderException((string) $upload->getError());
		}
		$source = $upload->getTemporaryFile();

		$file = null;
		if($upload->isImage()) {
			$file = new ImageEntity();


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
			$file = new FileEntity();
		}

		$file->setName(pathinfo($upload->getUntrustedName(), PATHINFO_FILENAME));
		$file->setMimeType($upload->getContentType());
		$fileExtension = pathinfo($upload->getUntrustedName(), PATHINFO_EXTENSION);
		$file->setExtension($fileExtension == 'jpeg' ? 'jpg' : $fileExtension);
		$file->setSize($upload->getSize());

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
	 * Creates the file download HTTP response which can be easily sent using the `send()` method.
	 *
	 * @param IRequest $request The image request
	 * @return FileResponse
	 * @throws \Nette\Application\BadRequestException
	 * @throws \Nette\Utils\ImageException
	 */
	public function download(IRequest $request){
		if($request instanceof ImageRequest) {
			if($request->getDimensions() === ImageRequest::ORIGINAL) {
				$filename = $this->getOriginalFilePathWithFileName($request->getFile());
			} else {
				$this->createCacheImage($request);
				$filename = $this->getCacheFilePathWithFileName($request);
			}

			return new FileResponse($filename, basename($filename), $request->getFile()->getMimeType());
		} elseif($request instanceof FileRequest) {
			$filename = $this->getOriginalFilePathWithFileName($request->getFile());

			return new FileResponse($filename, $request->getFile()->getName(), $request->getFile()->getMimeType());
		} else {
			throw new \InvalidArgumentException("Request is not correct instance of IRequest");
		}
	}


	/**
	 * Returns the URL of the cached version of the image.
	 *
	 * @param IRequest $request The image request
	 * @return string  The URL of the image
	 * @throws \Nette\Utils\ImageException
	 */
	public function link(IRequest $request){
		if($request instanceof ImageRequest) {
			$this->createCacheImage($request);
			$filePath = $this->getCacheFilePathWithFileName($request);

			return $this->getBaseUrl() . $filePath;
		} elseif($request instanceof FileRequest) {
			$filePath = $this->getOriginalFilePathWithFileName($request->getFile());

			return $this->getBaseUrl() . $filePath;
		} else {
			throw new \InvalidArgumentException("Request is not correct instance of IRequest");
		}
	}


	/**
	 * Removes the image from the storage by the given image file information.
	 *
	 * @param IFile $file The image information
	 * @return FileStorage Fluent interface
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
	 * @return FileStorage Fluent interface
	 */
	public function removeCache(IFile $file){
		$directory = str_replace($file->getHash(), "", $this->createCacheDirectoryPath($file->getHash()));
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
	 * Checks that the cached version of the image exists and creates it if not.
	 *
	 * @throws \Nette\Utils\ImageException
	 */
	protected function createCacheImage(ImageRequest $request): ImageEntity
	{
		$filePathWithFileName = $this->getCacheFilePathWithFileName($request);

		if(!file_exists($filePathWithFileName)) {
			$file = $request->getFile();

			$original = $this->original($file);

			if($request->getCrop()) {
				$image = $this->crop($original, $request);
			} else {
				$image = $this->resize($original, $request);
			}
			new Directory(dirname($filePathWithFileName));
			$image->save($filePathWithFileName, 75, $file->getTranslatedMimeType());

			return $image;
		}

		return $request->getFile();
	}


	/**
	 * Crops the given image using the given image request options.
	 *
	 * @param Image $image The image to resize
	 * @param IRequest $request The image request
	 * )* @return Image   The image thumbnail
	 */
	protected function crop(Image $image, IRequest $request){
		if($request->getDimensions() === IRequest::ORIGINAL) {

			return $image;
		}

		list($width, $height) = $this->processDimensions($request->getDimensions());

		$resizeWidth = $width;
		$resizeHeight = $height;

		$originalWidth = $request->getFile()->getWidth();
		$originalHeight = $request->getFile()->getHeight();
		$originalLandscape = $originalWidth > $originalHeight;

		$cropLandscape = $width > $height;
		$equals = $width === $height;

		if($originalLandscape) {

			if($cropLandscape) {

				$coefficient = $originalHeight / $height;
				$scaledWidth = round($originalWidth / $coefficient);

				$left = round(($scaledWidth - $width) / 2);
				$top = 0;

				if($scaledWidth < $width) {
					$coefficient = $originalWidth / $width;
					$scaledHeight = round($originalHeight / $coefficient);

					$left = 0;
					$top = round(($scaledHeight - $height) / 2);
				}

			} else {

				$coefficient = $originalHeight / $height;
				$scaledWidth = round($originalWidth / $coefficient);

				$left = round(($scaledWidth - $width) / 2);
				$top = 0;
			}

		} else {

			if($cropLandscape || $equals) {

				$coefficient = $originalWidth / $width;
				$scaledHeight = round($originalHeight / $coefficient);

				$left = 0;
				$top = round(($scaledHeight - $height) / 2);

			} else {

				$coefficient = $originalHeight / $height;
				$scaledWidth = round($originalWidth / $coefficient);

				$left = round(($scaledWidth - $width) / 2);
				$top = 0;

			}
		}

		$image->resize($resizeWidth, $resizeHeight, Image::FILL);
		$image->crop($left, $top, $width, $height);

		return $image;
	}


	/**
	 * Resizes the given image to the given dimensions using given flags.
	 *
	 * @param Image $image The image to resize
	 * @param IRequest $request The image request
	 * @return Image   The image thumbnail
	 */
	protected function resize(Image $image, IRequest $request){
		if($request->getDimensions() === IRequest::ORIGINAL) {
			return $image;
		}

		list($width, $height) = $this->processDimensions($request->getDimensions());

		return $image->resize($width, $height, $request->getFlags());
	}


	/**
	 * Creates the file name from the given file information.
	 *
	 * @param IFile $file The image file information
	 * @return string Absolute path to original file name
	 */
	protected function getOriginalFilePathWithFileName(IFile $file){
		return $this->getOriginalFilePath($file) . '/' . $this->getOriginalFileName($file);
	}


	/**
	 * Creates the internal directory path from the given hash.
	 *
	 * Some special images like the "Image Not Available" image are stored
	 * in directories prefixed with an underscore. Those directories are not
	 * fragmented to hash based structure.
	 *
	 * @param IFile $file The image file information
	 * @return string
	 */
	protected function getOriginalFilePath(IFile $file){
		$name = $file->getName();

		return $this->directory . '/' . "$name[0]/$name[1]";
	}


	/**
	 * Creates the absolute file name from the given file information.
	 *
	 * @param IFile $file The image file information
	 * @return string The absolute file name
	 */
	protected function getOriginalFileName(IFile $file){
		return $file->getNameWithExtension();
	}


	/**
	 * Returns the part of the image filename relative to the cache directory.
	 *
	 * @param ImageRequest $imageRequest The image request
	 * @return string
	 */
	protected function getCacheFilePathWithFileName(ImageRequest $imageRequest){
		return $this->getCacheFilePath($imageRequest) . "/" . $this->getCacheFileName($imageRequest);
	}


	/**
	 * Creates the internal directory path from the given request.
	 *
	 * @param ImageRequest $imageRequest The image request
	 * @return string
	 */
	protected function getCacheFilePath(ImageRequest $imageRequest){
		$name = $imageRequest->getName();
		return "$this->cacheDirectory/$name[0]/$name[1]";
	}


	/**
	 * Returns the file name of the cached version of the image.
	 *
	 * @param ImageRequest $imageRequest The image request
	 * @return string The file name of the cached version of the image
	 */
	protected function getCacheFileName(ImageRequest $imageRequest){
		$dimensions = $imageRequest->getDimensions();

		$fileName = $imageRequest->getFile()->getName();
		$fileExtension = $imageRequest->getfile->getExtension();

		return "$fileName.$dimensions.$fileExtension";
	}


	/**
	 * Parses the given dimensions string for the image width and height.
	 *
	 * @param string $dimensions The dimensions string
	 * @return array  The width and height of the image in pixels
	 */
	protected function processDimensions($dimensions){
		if(strpos($dimensions, 'x') !== false) {
			list($width, $height) = explode('x', $dimensions); // different dimensions, eg. "210x150"
			$width = intval($width);
			$height = intval($height);
		} else {
			$width = intval($dimensions); // same dimensions, eg. "210" => 210x210
			$height = $width;
		}

		return array($width, $height);
	}


}
