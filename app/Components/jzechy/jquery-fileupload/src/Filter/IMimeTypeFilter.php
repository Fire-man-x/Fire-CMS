<?php declare(strict_types = 1);

namespace Zet\FileUpload\Filter;

use Nette\Http\FileUpload;

/**
 * Interface IMimeTypeFilters
 * Rozhraní pro kontrolu Mime typu souboru.
 *
 * @author  Zechy <email@zechy.cz>
 */
interface IMimeTypeFilter
{

	/**
	 * Ověří mimetype předaného souboru.
	 *
	 */
	public function checkType(FileUpload $file): bool;

	/**
	 * Vrátí seznam povolených typů souborů.
	 *
	 */
	public function getAllowedTypes(): string;

}
