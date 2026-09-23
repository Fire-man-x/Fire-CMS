<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Domains;
use Nette\SmartObject;
use Nette\Utils\Strings;

/**
 * Class DomainService
 *
 * Mapování mezi doménami a jazyky (viz `firecms_domains`), používané routerem k rozhodnutí,
 * jestli má být jazyk určen podle domény (Host hlavičky), nebo (fallback) podle URL prefixu
 * `/xx/` jako doteď - viz App\Router\CustomRouter.
 */
class DomainService
{
	use SmartObject;

	/** @var array<string,string> domain (lowercase) => languageId */
	private array $domainToLanguage;

	/** @var array<string,string> languageId => canonical domain (lowercase) */
	private array $languageToDomain;


	public function __construct(private readonly Domains $model)
	{
	}


	private function load(): void
	{
		if (isset($this->domainToLanguage)) {
			return;
		}

		$this->domainToLanguage = [];
		$this->languageToDomain = [];

		$rows = $this->model->findAll()
			->where('active', true)
			->order('default DESC')
			->order($this->model->getColumnId());

		foreach ($rows as $row) {
			$languageId = $row->languageId;
			$domain = Strings::lower($row->domain);

			$this->domainToLanguage[$domain] = $languageId;

			// první nalezená (díky "default DESC") je kanonická doména daného jazyka
			if (!isset($this->languageToDomain[$languageId])) {
				$this->languageToDomain[$languageId] = $domain;
			}
		}
	}


	/**
	 * Jazyk podle hostname requestu, nebo null, pokud doména není žádnému jazyku přiřazená
	 */
	public function getLanguageByDomain(string $host): ?string
	{
		$this->load();

		return $this->domainToLanguage[Strings::lower($host)] ?? null;
	}


	/**
	 * Kanonická doména daného jazyka, nebo null, pokud jazyk žádnou vlastní doménu nemá
	 * (pak se dál používá URL prefix `/xx/` na sdílené doméně)
	 */
	public function getDomainForLanguage(string $languageId): ?string
	{
		$this->load();

		return $this->languageToDomain[$languageId] ?? null;
	}

}
