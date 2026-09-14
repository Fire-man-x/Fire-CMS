<?php
declare(strict_types=1);

namespace App\Forms;

use App\Forms\CategorySubtype\CategoryLinkFormPart;
use App\Forms\CategorySubtype\DefaultFormPart;
use App\Forms\CategorySubtype\GalleryFormPart;
use App\Forms\CategorySubtype\HomepageFormPart;
use App\Forms\CategorySubtype\ICategoryFormType;
use App\Forms\CategorySubtype\TextBoxFormPart;
use App\Forms\CategorySubtype\UrlFormPart;
use App\Modules\CommentsModule;
use App\Service\Category;
use App\Service\LanguageService;
use App\Modules\UrlModule\UrlManager;
use App\Model;
use App\Service\Tag;
use Nette\Application\UI\Form;
use Nette\InvalidArgumentException;
use Nette\Localization\Translator;
use Nette\Security\User;
use Nette\Utils\ArrayHash;


class CategoryFormFactory extends BaseFormFactory
{
	/**
	 * Types of form
	 */
	public static array $types = array(
		'site' => 'Site',
		'homepage' => 'Homepage',
		'gallery' => 'Gallery',
		'url' => 'URL',
		'categoryLink' => 'Link to category',
		'textBox' => 'Text box',
	);

	private string $type;

	private ICategoryFormType $categoryFormType;

	private Model\Categories $model;

	private Category $categoryService;

	private Tag $tagService;

	private UrlManager $urlManager;

	private LanguageService $languages;

	private Translator $translator;

	private User $user;

	private string $language;

	private ?int $parent;


	public function __construct(\Nette\DI\Container $container, FormFactory $factory, Model\Categories $model, LanguageService $languages,
		Translator $translator, User $user, Category $categoryService, Tag $tagService, UrlManager $urlManager)
	{
		parent::__construct($factory);
		$this->model = $model;
		$this->languages = $languages;
		$this->translator = $translator;
		$this->user = $user;
		$this->categoryService = $categoryService;
		$this->tagService = $tagService;
		$this->urlManager = $urlManager;


		//childs of ICategoryFormType
		/*$sites = array_filter(get_declared_classes(), function ($className) {
			return in_array(ICategoryFormType::class, class_implements($className));
		});

		foreach ($sites as $site){
			/* @var $byType ICategoryFormType * /
			$byType = $container->getByType($site);
		}*/
	}


	/**
	 * Parent category id
	 */
	public function setParent(?int $parent)
	{
		$this->parent = $parent;
	}


	public function create(int|string $editId = null, $language = null, $type = "site", $linkCallback = array(), $revision = null, $tagInputDataLoadUrl = ""): Form
	{
		$form = parent::create($editId);

		if(is_null($language)){
			throw new \InvalidArgumentException("Language cannot be null");
		}
		$this->language = $language;
		$this->type = $type;

		/*$this->edited_by_user_id = $edited_by_user_id;
		if(!$this->model->isRelationTableValid($relationWithTable)){
			throw new \InvalidArgumentException("Table name '$relationWithTable' is not valid as relation table.");
		}
		$this->relationWithTable = $relationWithTable;
		$this->relationTableId = $relationTableId;

		$form = parent::createBaseForm();*/

		/* @var $dtm \Vodacek\Forms\Controls\DateInput */
		/*$dtm = $form->addDate("datum", "datum");
		$dtm->setRequired();*/

		//add type, because "type" cannot be persistent
		$form->setAction($linkCallback("this", array("type" => $this->type)));


		$form->addCheckbox('active', 'Active');
		$form->addCheckbox('show_in_menu', 'Show in menu')
			->setDefaultValue(true);
		$form->addCheckbox('public', 'Public')
			->setDefaultValue(true);
		$form->addSelect('status', 'Status', ArticleFormFactory::$statuses)
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue("draft");

		$form->addText('publishing_date', 'Publishing date')
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue(date(DATETIME_FORMAT))
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS);

		$form->addText('expiring_date', 'Expiration date')
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS)->placeholder("Never");

		$types = array();
		foreach (self::$types as $typeId => $typeName) {
			$typeName = $this->translator->translate($typeName);
			$types[$typeId] = \Nette\Utils\Html::el(null, array(
					"data-link" => $linkCallback("this", array("type" => $typeId))))->setValue($typeId)->setText($typeName);
		}
		$typeControl = $form->addSelect('type', 'Type', $types)
			->setDefaultValue($this->type)
			->setRequired();
		$typeControl->addCondition(Form::EQUAL, "url");

		//categoryFormType
		if ($this->type == "homepage") {
			$this->categoryFormType = new HomepageFormPart($this->model);
		} elseif ($this->type == "gallery") {
			$this->categoryFormType = new GalleryFormPart($this->model, $this->urlManager);
		} elseif ($this->type == "url") {
			$this->categoryFormType = new UrlFormPart();
		} elseif ($this->type == "categoryLink") {
			$this->categoryFormType = new CategoryLinkFormPart($this->model, $this->translator);
		} elseif ($this->type == "textBox") {
			$this->categoryFormType = new TextBoxFormPart($this->model, $this->translator);
		} else {
			$this->categoryFormType = new DefaultFormPart($this->urlManager);
		}
		$this->categoryFormType->createFormPart($form);

		//Tags
		$dataSourceDescriptor = new \Achse\TagInput\DataSourceDescriptor($tagInputDataLoadUrl);
		$form->addTagInput('tags', "Tags", $dataSourceDescriptor)
			->setNullable();

		//send
		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		//defaults
		if($this->isEditMode()){
			if($revision){ //category revision
				$data = $this->model->findById($revision)->where("history_id", $this->getEditId())->fetch();
				$dataTranslation = $this->model->findTranslationBy($revision, $this->language)->fetch();
			}else{ //normal category
				$data = $this->model->findById($this->getEditId())->fetch();
				$dataTranslation = $this->model->findTranslationBy($this->getEditId(), $this->language)->fetch();
			}

			if(!$data/* || !$dataTranslation*/){
				throw new \InvalidArgumentException("Can not edit item with id '".($revision ? $revision : $this->getEditId())."'");
			}

			/* @var $values ActiveRow */
			$values = $data->toArray();
			$values["publishing_date"] = $data->publishing_date->format(DATETIME_FORMAT);
			if($data->expiring_date){
				$values["expiring_date"] = $data->expiring_date->format(DATETIME_FORMAT);
			}
			$values['translation'] = $dataTranslation == false ? array() : $dataTranslation->toArray();
			//url
			try{
				$values['translation']['url'] = $this->urlManager->getUrlByTypeAndKey('category',$this->getEditId(), $this->language);
			} catch(InvalidArgumentException $e) {}


			//force set type
			if ($this->type) {
				$values['type'] = $this->type;
			}

			//categoryFormType
			$values = $this->categoryFormType->setDefaultValuesToFormPart($form, $values);

			//tags
			$tags = $this->tagService->getRelationTags(Tag::TYPE_CATEGORY, $this->language, $this->getEditId());
			foreach ($tags as &$tag){
				$tag = $tag["label"];
			}
			$values['tags'] = $tags;

			$form->setDefaults($values);
		}

		return $form;
	}


	public function formSucceeded(Form $form, ArrayHash $values)
	{
		unset($values->editId);

		$tags = $values->tags;
		unset($values->tags);

		//images
		unset($values->images);

		//publishing_date
		$values->publishing_date = $this->checkDateTimeFormat($values->publishing_date);
		if($values->publishing_date == null){
			$values->publishing_date = new \DateTime();
		}
		$values->expiring_date = $this->checkDateTimeFormat($values->expiring_date);

		//categoryFormType
		$values = $this->categoryFormType->onSuccessFormPart($form, $values, $this->getEditId());

		//url
		$url = null;
		if($values->type != "homepage")
		{
			$url = $values->translation->url;
		}
		unset($values->translation->url);

		//translation container
		$translationContainer = $values->translation;
		unset($values->translation);

		//updating
		if ($this->isEditMode()) {
			//make backup and update
			$this->categoryService->update($this->getEditId(), $values, $this->language, $translationContainer);

			//url
			if($url)
			{
				$this->urlManager->saveUrl('category', $this->getEditId(), $this->language, $url);
			}

			//tags
			$this->tagService->useTags(Tag::TYPE_CATEGORY, $this->getEditId(), $this->language, $tags);

			//redirect
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		} else {
			if(isset($this->parent)){
				$values->parent_id = $this->parent;
			}
			$values->created_by = $this->user->getId();

			//insert
			$id = $this->categoryService->insert($values, $this->language, $translationContainer);

			//url
			if($url)
			{
				$this->urlManager->saveUrl('category', $id, $this->language, $url);
			}

			//redirect
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect("this", array("id" => $id, "parent"=>null));
		}
	}

}
