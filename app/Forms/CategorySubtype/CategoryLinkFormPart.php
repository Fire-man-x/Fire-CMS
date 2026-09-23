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


	/**
	 * @param string|null $language Jazyk administrace pro názvy kategorií v selectu (null = výchozí jazyk webu)
	 */
	public function __construct(Model\Categories $categoryModel, Translator $translator, private ?string $language = null)
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
		$categories = $this->categoryModel->findAll()
			->select($this->categoryModel->getTableName() . ".*");
		$this->categoryModel->selectTitle($categories, "`" . $this->categoryModel->getTableName() . "`.`id`", $this->language);
		$this->categoriesList = $categories
			->order("IF(type=?,0,1)", "homepage")
			->order("title")
			->fetchAll();

		$list = array();
		foreach ($this->categoriesList as $category) {
			$list[$category->id] = \Nette\Utils\Html::el(null, array("class" => !$category->active ? "font-italic" : ""))
					->setValue($category->id)->setText((string) $category->title);
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
