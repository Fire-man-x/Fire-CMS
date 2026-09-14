<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Model;
use Nette;
use Nette\Application\Attributes\Persistent;

class TagsPresenter extends BasePresenter
{

	/**
	 * url
	 */
	#[Persistent]
	public string $url;

	/** @inject */
	public Model\Tags $tagsModel;

	public function renderDefault(): void
	{
		$tag = $this->tagsModel->getTranslationTable()
			->where("language_id", $this->language)
			->where("name", $this->url)
			->fetch();
		if(!$tag || !$this->url){
			throw new Nette\Application\BadRequestException("Tag with url '$this->url' doesn't exist.");
		}

		//breadcrumb
		$this->addBreadCrumbLink("Tag", $this->link(":Front:Tags:default", array("url" => $this->url)));
		$this->addBreadCrumbLink($tag->name, $this->link(":Front:Tags:default", array("url" => $this->url)), null, false );

		//change to object
		$tag = Nette\Utils\ArrayHash::from($tag->toArray());

		$this->template->tags = $this->tagsModel;
		$this->template->tag = $tag;

		//set query to control
		$this->articles->whereTag($tag->tag_id);
		$this->categories->whereTag($tag->tag_id);

		//set links to languageChanger
		$lanuageItems = $this->tagsModel->getTranslationTable()->where($this->tagsModel->getColumnId(), $tag->tag_id)->fetchAll();
		foreach ($lanuageItems as $lanuageItem) {
			$this->languageChanger->setLinkForLanguage($lanuageItem->language_id, $this->link("this", array(
					"id" => $lanuageItem->name,
					"locale" => $lanuageItem->language_id
			)));
		}
	}

}
