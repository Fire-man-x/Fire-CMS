<?php
declare(strict_types=1);

namespace App\Model;

use App\Components\FileManager\FileManager;
use App\Components\FileManager\Files\IFile;
use Nette\Http\FileUpload;
use Nette\SmartObject;
use Zet\FileUpload\Model\IUploadModel;

/**
 * Class MultiFileUploadModel
 * @package Zet\FileUpload\Model
 */
class MultiFileUploadModel implements IUploadModel
{
	use SmartObject;

	private FileManager $fileManager;

	/**
	 * Image max dimeensions
	 */
	private string $dimensions = "1000x1000";


	/**
	 * MultiFileUploadModel constructor
	 */
	public function __construct(FileManager $fileManager, Options $options)
	{
		$this->fileManager = $fileManager;

		$dimensions = $options->getByKey("image_resolution");
		if($dimensions){
			$this->dimensions = $dimensions;
		}
	}


	/**
	 * Save uploaded file - saved by File manager
	 * Save uploaded file - saved by File manager
	 * @return mixed|IFile Vlastní navrátová hodnota.
	 */
	public function save(FileUpload $file, array $params = []): mixed
	{
		$settings = array(
			"dimensions" => $this->dimensions
		);
		//save by File manager
		return $this->fileManager->upload($file, $settings);
	}


	/**
	 * Zpracování požadavku o smazání souboru.
	 */
	public function remove(mixed $uploaded) : void
	{
		# By Pass...
	}


	/**
	 * Zpracování přejmenování souboru.
	 */
	public function rename(mixed $upload, string $newName): mixed
	{
		return $newName;
		//return Strings::webalize($newName);
	}

}
