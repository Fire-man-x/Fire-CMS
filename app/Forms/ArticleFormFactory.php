<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use App\Modules\UrlModule\UrlManager;
use App\Service\Article;
use App\Service\Tag;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\InvalidArgumentException;
use Nette\Security\User;
use Nette\Utils\ArrayHash;


class ArticleFormFactory extends BaseFormFactory
{
	/**
	 * Statuses
	 */
	public static array $statuses = array(
		'publish' => 'Publish',
		'pending' => 'Pending',
		'draft' => 'Draft'
		);

	private string $language;


	public function __construct(
		FormFactory $factory,
		private Model\Articles $model,
		private User $user,
		private Article $articleService,
		private Tag $tagService,
		private UrlManager $urlManager)
	{
		parent::__construct($factory);
	}


	public function create(int|string $editId = null, $language = null, $revision = null, $tagInputDataLoadUrl = ""): Form
	{
		$form = parent::create($editId);

		if(is_null($language)){
			throw new \InvalidArgumentException("Language cannot be null");
		}
		$this->language = $language;

		$form->addCheckbox('active', 'Active');
		$form->addCheckbox('public', 'Public')
			->setDefaultValue(true);
		$form->addSelect('status', 'Status', self::$statuses)
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue("draft");

		$form->addText('publishing_date', 'Publishing date')
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue(date(DATETIME_FORMAT))
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS);

		$form->addText('expiring_date', 'Expiration date')
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS)->placeholder("Never");

		$translationContainer = $form->addContainer('translation');

		$translationContainer->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED)
			->getControlPrototype()->addClass("validate-url");

		$translationContainer->addText('url', 'URL')
			->getControlPrototype()->addClass("validate-url-output");

		$translationContainer->addTextArea('excerpt', 'Excerpt', null, 4)
			->getControlPrototype()->addClass(WYSIWYG_CLASS);

		$translationContainer->addTextArea('content', 'Text', null, 7)
			->getControlPrototype()->addClass(WYSIWYG_CLASS);

		//SEO
		$translationContainer->addText('seo_title', 'SEO title');
		$translationContainer->addText('seo_description', 'SEO description');
		$translationContainer->addText('seo_keywords', 'SEO keywords');

		//Tags
		$dataSourceDescriptor = new \Achse\TagInput\DataSourceDescriptor($tagInputDataLoadUrl);
		$form->addTagInput('tags', "Tags", $dataSourceDescriptor)
			->setNullable();

		//Files
		$form->addHidden('images');

		//send
		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		//defaults
		if($this->isEditMode()){
			if($revision){ //category revision
				$data = $this->model->findById($revision)->where("history_id", $this->getEditId())->fetch();
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
			$values["publishing_date"] = $data->publishing_date->format(DATETIME_FORMAT);
			if($data->expiring_date){
				$values["expiring_date"] = $data->expiring_date->format(DATETIME_FORMAT);
			}
			$values['translation'] = $dataTranslation == false ? array() : $dataTranslation->toArray();
			//url
			try{
				$values['translation']['url'] = $this->urlManager->getUrlByTypeAndKey('article', $this->getEditId(), $this->language);
			} catch(InvalidArgumentException $e) {}

			//tags
			$tags = $this->tagService->getRelationTags(Tag::TYPE_ARTICLE, $this->language, $this->getEditId());
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

		$translationContainer = $values->translation;
		unset($values->translation);
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

		//url
		$url = empty($translationContainer->url) ? $translationContainer->title : $translationContainer->url;
		unset($translationContainer->url);

		//updating
		if ($this->isEditMode()) {
			//make backup
			$this->articleService->makeBackup($this->getEditId());

			//tags
			$this->tagService->useTags(Tag::TYPE_ARTICLE, $this->getEditId(), $this->language, $tags);

			//update
			$this->model->update($this->getEditId(), (array) $values);
			$this->model->updateTranslation($this->getEditId(), $this->language, $translationContainer);

			//url
			$this->urlManager->saveUrl('article', $this->getEditId(), $this->language, $url);

			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		} else {
			$values->created_by = $this->user->getId();
			$id = $this->model->insert($values);
			$this->model->insertTranslation($id, $this->language, $translationContainer);

			//url
			$this->urlManager->saveUrl('article', $id, $this->language, $url);

			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect("this", array("id" => $id));
		}
	}

}
