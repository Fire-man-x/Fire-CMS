<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Components\TagsInput\DataSourceDescriptor;
use App\Components\TagsInput\TagInput;
use App\AdminModule\Forms\CategorySubtype\DefaultFormPart;
use App\AdminModule\Forms\CategorySubtype\ICategoryFormType;
use App\Model;
use App\Modules\UrlModule\UrlManager;
use App\Service\Category;
use App\Service\Tag;
use Nette\InvalidArgumentException;
use Nette\Security\User;
use Nette\Utils\ArrayHash;


class CategoryFormFactory extends AdminFormFactory
{
	private ICategoryFormType $categoryFormType;

	private string $language;

	private ?int $parent = null;

	private ?int $sectionId = null;


	public function __construct(
		private readonly Model\Database\Categories $model,
		private readonly User $user,
		private readonly Category $categoryService,
		private readonly Tag $tagService,
		private readonly UrlManager $urlManager,
	) {
		parent::__construct();
	}


	/**
	 * Parent category id
	 */
	public function setParent(?int $parent): void
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


	public function create(int|string|null $editId = null, ?string $language = null, ?callable $linkCallback = null, int|string|null $revision = null, string $tagInputDataLoadUrl = ""): AdminForm
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

		/* @var $dtm \App\Components\DateInput\DateInput */
		/*$dtm = $form->addDate("datum", "datum");
		$dtm->setRequired();*/


		$form->data->addCheckbox('active', 'Active');
		$form->data->addCheckbox('showInMenu', 'Show in menu')
			->setDefaultValue(true);
		$form->data->addCheckbox('public', 'Public')
			->setDefaultValue(true);
		$form->data->addSelect('status', 'Status', ArticleFormFactory::$statuses)
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue("draft");

		$form->data->addText('publishingDate', 'Publishing date')
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue(date(DATETIME_FORMAT))
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS);

		$form->data->addText('expiringDate', 'Expiration date')
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS)->placeholder("Never");

		// kategorie článků nemají typ (odkazy řeší položky menu, obsahové stránky App\Model\Pages)
		$this->categoryFormType = new DefaultFormPart($this->urlManager);
		$this->categoryFormType->createFormPart($form, $this->language);

		//Tags
		$tagsControl = new TagInput(new DataSourceDescriptor($tagInputDataLoadUrl), "Tags");
		$tagsControl->setNullable();
		$form->data->addComponent($tagsControl, 'tags');

		//send
		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		//defaults
		if($this->isEditMode()){
			$categoryId = (int) $this->getEditId();
			if($revision){ //category revision
				$data = $this->model->findById((int) $revision)->where("historyId", $categoryId)->fetch();
				$dataTranslation = $this->model->findTranslationBy((int) $revision, $this->language)->fetch();
			}else{ //normal category
				$data = $this->model->getById($categoryId);
				$dataTranslation = $this->model->findTranslationBy($categoryId, $this->language)->fetch();
			}

			if(!$data/* || !$dataTranslation*/){
				throw new \InvalidArgumentException("Can not edit item with id '".($revision ? $revision : $categoryId)."'");
			}

			$values = $data->toArray();
			if($data->publishingDate instanceof \DateTimeInterface){
				$values["publishingDate"] = $data->publishingDate->format(DATETIME_FORMAT);
			}
			if($data->expiringDate instanceof \DateTimeInterface){
				$values["expiringDate"] = $data->expiringDate->format(DATETIME_FORMAT);
			}
			$translation = $dataTranslation ? $dataTranslation->toArray() : array();
			//url
			try{
				$translation['url'] = $this->urlManager->getUrlByTypeAndKey('category', $categoryId, $this->language);
			} catch(InvalidArgumentException $e) {}
			$values['locales'] = array($this->language => $translation);


			//categoryFormType
			$values = $this->categoryFormType->setDefaultValuesToFormPart($form, $values);

			//tags
			$tags = $this->tagService->getRelationTags(Tag::TYPE_CATEGORY, $this->language, $categoryId);
			foreach ($tags as &$tag){
				$tag = $tag["label"];
			}
			$values['tags'] = $tags;

			$form->setDefaults($values);
		}

		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		$data = $values->data;

		$tags = $data->tags;
		unset($data->tags);

		//images
		unset($data->images);

		//publishingDate
		$data->publishingDate = $this->checkDateTimeFormat($data->publishingDate);
		if($data->publishingDate == null){
			$data->publishingDate = new \DateTime();
		}
		$data->expiringDate = $this->checkDateTimeFormat($data->expiringDate);

		//categoryFormType
		$values = $this->categoryFormType->onSuccessFormPart($form, $values, $this->language, (int) $this->getEditId());

		//translation container
		$translation = ArrayHash::from($values->locales[$this->language]);

		//url
		$url = $translation->url;
		unset($translation->url);

		$presenter = $form->getPresenter();

		//updating
		if ($this->isEditMode()) {
			$categoryId = (int) $this->getEditId();

			//make backup and update
			$this->categoryService->update($categoryId, (array) $data, $this->language, $translation);

			//url
			if($url)
			{
				$this->urlManager->saveUrl('category', $categoryId, $this->language, $url);
			}

			//tags
			$this->tagService->useTags(Tag::TYPE_CATEGORY, $categoryId, $this->language, $tags);

			//redirect
			$presenter->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$presenter->redirect('this');
		} else {
			if($this->parent !== null){
				$data->parentId = $this->parent;
			}
			if ($this->sectionId === null) {
				throw new \LogicException('Section of a new category is not set (CategoryFormFactory::setSectionId()).');
			}
			$data->createdBy = $this->user->getId();
			$data->sectionId = $this->sectionId;

			//insert
			$id = $this->categoryService->insert($data, $this->language, $translation);

			//url
			if($url)
			{
				$this->urlManager->saveUrl('category', $id, $this->language, $url);
			}

			//redirect
			$presenter->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$presenter->redirect("this", array("id" => $id, "parent"=>null));
		}
	}

}
