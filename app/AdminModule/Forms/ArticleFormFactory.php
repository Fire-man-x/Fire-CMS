<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Components\TagsInput\DataSourceDescriptor;
use App\Components\TagsInput\TagInput;
use App\Model;
use App\Modules\UrlModule\UrlManager;
use App\Service\Article;
use App\Service\Tag;
use Nette\InvalidArgumentException;
use Nette\Security\User;
use Nette\Utils\ArrayHash;


class ArticleFormFactory extends AdminFormFactory
{
	/**
	 * Statuses
	 * @var array<string, string>
	 */
	public static array $statuses = array(
		'publish' => 'Publish',
		'pending' => 'Pending',
		'draft' => 'Draft'
		);

	private string $language;

	/** Sekce nového článku (App\Model\Sections), existující článek sekci nemění */
	private ?int $sectionId = null;


	public function __construct(
		private readonly Model\Database\Articles $model,
		private readonly User $user,
		private readonly Article $articleService,
		private readonly Tag $tagService,
		private readonly UrlManager $urlManager,
	) {
		parent::__construct();
	}


	public function setSectionId(int $sectionId): void
	{
		$this->sectionId = $sectionId;
	}


	public function create(int|string|null $editId = null, ?string $language = null, int|string|null $revision = null, string $tagInputDataLoadUrl = ""): AdminForm
	{
		$form = parent::create($editId);

		if(is_null($language)){
			throw new \InvalidArgumentException("Language cannot be null");
		}
		$this->language = $language;

		$form->data->addCheckbox('active', 'Active');
		$form->data->addCheckbox('public', 'Public')
			->setDefaultValue(true);
		$form->data->addSelect('status', 'Status', self::$statuses)
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue("draft");

		$form->data->addText('publishingDate', 'Publishing date')
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue(date(DATETIME_FORMAT))
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS);

		$form->data->addText('expiringDate', 'Expiration date')
			->getControlPrototype()->addClass(DATETIMEPICKER_CLASS)->placeholder("Never");

		$translationContainer = $form->locales->addContainer($this->language);

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
		$translationContainer->addText('seoTitle', 'SEO title');
		$translationContainer->addText('seoDescription', 'SEO description');
		$translationContainer->addText('seoKeywords', 'SEO keywords');

		//Tags
		$tagsControl = new TagInput(new DataSourceDescriptor($tagInputDataLoadUrl), "Tags");
		$tagsControl->setNullable();
		$form->data->addComponent($tagsControl, 'tags');

		//Files
		$form->data->addHidden('images');

		//send
		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		//defaults
		if($this->isEditMode()){
			$articleId = (int) $this->getEditId();
			if($revision){ //category revision
				$data = $this->model->findById((int) $revision)->where("historyId", $articleId)->fetch();
				$dataTranslation = $this->model->findTranslationBy((int) $revision, $this->language)->fetch();
			}else{ //normal category
				$data = $this->model->getById($articleId);
				$dataTranslation = $this->model->findTranslationBy($articleId, $this->language)->fetch();
			}

			if(!$data/* || !$dataTranslation*/){
				throw new \InvalidArgumentException("Can not edit item with id '".($revision ? $revision : $articleId)."'");
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
				$translation['url'] = $this->urlManager->getUrlByTypeAndKey('article', $articleId, $this->language);
			} catch(InvalidArgumentException $e) {}
			$values['locales'] = array($this->language => $translation);

			//tags
			$tags = $this->tagService->getRelationTags(Tag::TYPE_ARTICLE, $this->language, $articleId);
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
		$translation = ArrayHash::from($values->locales[$this->language]);

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

		//url
		$url = empty($translation->url) ? $translation->title : $translation->url;
		unset($translation->url);

		$presenter = $form->getPresenter();

		//updating
		if ($this->isEditMode()) {
			$articleId = (int) $this->getEditId();

			//make backup
			$this->articleService->makeBackup($articleId);

			//tags
			$this->tagService->useTags(Tag::TYPE_ARTICLE, $articleId, $this->language, $tags);

			//update
			$this->model->update($articleId, (array) $data);
			$this->model->updateTranslation($articleId, $this->language, $translation);

			//url
			$this->urlManager->saveUrl('article', $articleId, $this->language, $url);

			$presenter->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$presenter->redirect('this');
		} else {
			if ($this->sectionId === null) {
				throw new \LogicException('Section of a new article is not set (ArticleFormFactory::setSectionId()).');
			}
			$data->createdBy = $this->user->getId();
			$data->sectionId = $this->sectionId;
			$id = $this->model->insert($data);
			$this->model->insertTranslation($id, $this->language, $translation);

			//url
			$this->urlManager->saveUrl('article', $id, $this->language, $url);

			$presenter->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$presenter->redirect("this", array("id" => $id));
		}
	}

}
