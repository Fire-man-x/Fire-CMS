<?php declare(strict_types = 1);

namespace Zet\FileUpload\Model;

use Nette\Http\FileUpload;

/**
 * Interface IUploadController
 *
 * @author  Zechy <email@zechy.cz>
 */
interface IUploadModel
{

	/**
	 * Uložení nahraného souboru.
	 *
	 */
	public function save(FileUpload $file, array $params = []): mixed;

	/**
	 * Zpracování přejmenování souboru.
	 *
	 */
	public function rename(mixed $upload, string $newName): mixed;

	/**
	 * Zpracování požadavku o smazání souboru.
	 *
	 */
	public function remove(mixed $uploaded): void;

}
