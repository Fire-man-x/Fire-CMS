<?php declare(strict_types = 1);

namespace Zet\FileUpload\Model;

use Nette\Http\FileUpload;
use Nette\SmartObject;
use Nette\Utils\Strings;

/**
 * Class BaseUploadModel
 *
 * @author  Zechy <email@zechy.cz>
 */
class BaseUploadModel implements IUploadModel
{

	use SmartObject;

	/**
	 * Uložení nahraného souboru.
	 *
	 */
	public function save(FileUpload $file, array $params = []): mixed
	{
		return $file->getSanitizedName();
	}

	/**
	 * Zpracování požadavku o smazání souboru.
	 *
	 */
	public function remove(mixed $uploaded): void
	{
		// By Pass...
	}

	/**
	 * Zpracování přejmenování souboru.
	 *
	 */
	public function rename(mixed $upload, string $newName): mixed
	{
		return Strings::webalize($newName);
	}

}
