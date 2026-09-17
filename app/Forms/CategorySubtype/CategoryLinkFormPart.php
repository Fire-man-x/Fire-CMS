<?php
declare(strict_types=1);

namespace App\Forms\CategorySubtype;

use App\Model;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;

class CategoryLinkFormPart implements ICategoryFormType
{

	private Model\Categories $categoryModel;

	private Translator $translator;

	private array $categoriesList;


	public function __construct(Model\Categories $categoryModel, Translator $translator)
	{
		$this->categoryModel = $categoryModel;
		$this->translator = $translator;
	}


	public function getType()
	{
		return "categoryLink";
	}


	public function createFormPart(Form $form)
	{
		$translationContainer = $form->addContainer('translation');

		$translationContainer->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED);

		//link to category
		$this->categoriesList = $this->categoryModel->findAll()
			->order("IF(type=?,0,1)", "homepage")
			->order("grid_name")
			->fetchAll(); //("category_id", "grid_name");

		$list = array();
		foreach ($this->categoriesList as $category) {
			$list[$category->category_id] = \Nette\Utils\Html::el(null, array("class" => !$category->active ? "font-italic" : ""))
					->setValue($category->category_id)->setText($category->grid_name);
		}
		$translationContainer->addSelect('link_to_category_id', $this->translator->translate('Category'), $list)
			->setTranslator(null);
	}


	public function setDefaultValuesToFormPart(Form $form, $values)
	{
		if ($values['translation'] && array_key_exists($values['translation']['url'], $this->categoriesList)) {
			$values['translation']['link_to_category_id'] = $values['translation']['url'];
		}

		return $values;
	}


	public function onSuccessFormPart(Form $form, ArrayHash $values, int $editId): ArrayHash
	{
		$values->translation->url = $values->translation->link_to_category_id;
		unset($values->translation->link_to_category_id);

		return $values;
	}

}
