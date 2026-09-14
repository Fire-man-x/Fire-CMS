<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Model;
use Nette\Application\Attributes\Persistent;

class SearchPresenter extends BasePresenter
{

	/**
	 * Search query
	 */
	#[Persistent]
	public string $query;

	/** @inject */
	public Model\Articles $articlesModel;

	public function renderDefault(): void
	{
		//breadcrumb
		$this->addBreadCrumbLink("Search", $this->link(":Front:Search:default", array("query" => null)), 'fa fa-search');

		if($this->query){
			$this->addBreadCrumbLink($this->query, $this->link(":Front:Search:default", array("query" => $this->query)), null, false);
		}

		//override main
		$this->template->options["main_description"] = $this->translator->translate("Search results: %s", $this->query);

		//set query to control
		$this->search->setQuery($this->query);

		//set query to control
		$this->articles->setQuery($this->query);
		$this->categories->setQuery($this->query);

		//set links to languageChanger
		foreach ($this->languages->getActiveLanguages() as $lanuageItem => $languageName) {
			$this->languageChanger->setLinkForLanguage($lanuageItem, $this->link("this", array(
					"query" => $this->query,
					"locale" => $lanuageItem
			)));
		}
	}

}
