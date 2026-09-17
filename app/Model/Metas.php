<?php
declare(strict_types=1);

namespace App\Model;

use App\Service\LanguageService;
use Nette\Utils\ArrayHash;

/**
 * Metas Model
 */
class Metas extends BaseModel
{

	private LanguageService $languages;


	/**
	 * Constructor
	 * @param \Nette\Database\Explorer $database
	 * @param LanguageService $languages
	 */
	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages)
	{
		parent::__construct($database);
		$this->languages = $languages;

		$this->setTableName('firecms_metas');
		$this->setColumnId('meta_id');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		return parent::insert($data);
	}

}
