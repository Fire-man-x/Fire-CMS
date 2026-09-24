<?php
declare(strict_types=1);

namespace App\Forms;

use App\Forms\CategorySubtype\DefaultFormPart;
use App\Forms\CategorySubtype\ICategoryFormType;
use App\Modules\CommentsModule;
use App\Service\Category;
use App\Service\LanguageService;
use App\Modules\UrlModule\UrlManager;
use App\Model;
use App\Service\Tag;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\InvalidArgumentException;
use Nette\Security\User;
use Nette\Utils\ArrayHash;


class CategoryFormFactory extends BaseFormFactory
{
	private ICategoryFormType $categoryFormType;

	private string $language;

	private ?int $parent;

	private ?int $sectionId = null;


	public function __construct(
		private Model\Categories $model,
		private User $user,
		private Category $categoryService,
		private Tag $tagService,
		private UrlManager $urlManager
	)
	{
		parent::__construct();
	}


	/**
	 * Parent category id
	 */
	public function setParent(?int $parent)
	{
		$this->parent = $parent;
	}


	/**
	 * Sekce nové kategorie (App\Model\Sections), existující kategorie sekci nemění
	 */
	public function setSectionId(int $sectionId): void
	{
		$this->sectionId = $sectionId;
	}


	public function create(int|string $editId = null, $language = null, $linkCallback = array(), $revision = null, $tagInputDataLoadUrl = ""): Form
	{
		$form = parent::create($editId);

		if(is_null($language)){
			throw new \InvalidArgumentException("Language cannot be null");
		}
		$this->language = $language;

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


		$form->addCheckbox('active', 'Active');
		$form->addCheckbox('showInMenu', 'Show in menu')
			->setDefaultValue(true);
		$form->addCheckbox('public', 'Public')
			->setDefaultValue(true);
		$form->addSelect('status', 'Status', ArticleFormFactory::$statuses)
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue("draft");

		$form->addText('publishingDate', 'Publishing date')
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue(date(DATETIME_FORMAT))
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS);

		$form->addText('expiringDate', 'Expiration date')
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS)->placeholder("Never");

		// kategorie článků nemají typ (odkazy řeší položky menu, obsahové stránky App\Model\Pages)
		$this->categoryFormType = new DefaultFormPart($this->urlManager);
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
				$data = $this->model->findById($revision)->where("historyId", $this->getEditId())->fetch();
				$dataTranslation = $this->model->findTranslationBy($revision, $this->language)->fetch();
			}else{ //normal category
				$data = $this->model->getById($this->getEditId());
				$dataTranslation = $this->model->findTranslationBy($this->getEditId(), $this->language)->fetch();
			}

			if(!$data/* || !$dataTranslation*/){
				throw new \InvalidArgumentException("Can not edit item with id '".($revision ? $revision : $this->getEditId())."'");
			}

			/* @var $values ActiveRow */
			$values = $data->toArray();
			$values["publishingDate"] = $data->publishingDate->format(DATETIME_FORMAT);
			if($data->expiringDate){
				$values["expiringDate"] = $data->expiringDate->format(DATETIME_FORMAT);
			}
			$values['translation'] = $dataTranslation == false ? array() : $dataTranslation->toArray();
			//url
			try{
				$values['translation']['url'] = $this->urlManager->getUrlByTypeAndKey('category',$this->getEditId(), $this->language);
			} catch(InvalidArgumentException $e) {}


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

		//publishingDate
		$values->publishingDate = $this->checkDateTimeFormat($values->publishingDate);
		if($values->publishingDate == null){
			$values->publishingDate = new \DateTime();
		}
		$values->expiringDate = $this->checkDateTimeFormat($values->expiringDate);

		//categoryFormType
		$values = $this->categoryFormType->onSuccessFormPart($form, $values, (int) $this->getEditId());

		//url
		$url = $values->translation->url;
		unset($values->translation->url);

		//translation container
		$translationContainer = $values->translation;
		unset($values->translation);

		//updating
		if ($this->isEditMode()) {
			//make backup and update
			$this->categoryService->update($this->getEditId(), (array) $values, $this->language, $translationContainer);

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
				$values->parentId = $this->parent;
			}
			if ($this->sectionId === null) {
				throw new \LogicException('Section of a new category is not set (CategoryFormFactory::setSectionId()).');
			}
			$values->createdBy = $this->user->getId();
			$values->sectionId = $this->sectionId;

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
