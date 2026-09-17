<?php
declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Domains Model
 *
 * Domény přiřazené jazykovým mutacím - viz App\Service\DomainService.
 */
class Domains extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_domains');
		$this->setColumnId('domain_id');
	}


	/**
	 * Domains for given language
	 */
	public function findByLanguage(string $languageId): Selection
	{
		return $this->findAll()->where('language_id', $languageId);
	}


	/**
	 * Find by domain hostname
	 */
	public function findByDomain(string $domain): ?ActiveRow
	{
		return $this->findAll()->where('domain', $domain)->fetch();
	}

}
