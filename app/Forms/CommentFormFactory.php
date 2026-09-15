<?php
declare(strict_types=1);

namespace App\Forms;

use App\Modules\CommentsModule;
use Nette\Application\UI\Form;
use Nette\Security\User;

class CommentFormFactory extends BaseFormFactory
{


	private string $languageId;

	private User $user;

	private CommentsModule\Model\Comments $model;

	private CommentsModule\Comment $service;

	private string $type;

	private int $columnId;


	public function __construct(FormFactory $factory, User $user, CommentsModule\Model\Comments $model, CommentsModule\Comment $service)
	{
		parent::__construct($factory);
		$this->user = $user;
		$this->model = $model;
		$this->service = $service;
	}


	public function setType($type): self
	{
		if (!in_array($type, array(CommentsModule\Comment::TYPE_ARTICLE, CommentsModule\Comment::TYPE_CATEGORY))) {
			throw new \InvalidArgumentException("Type '$type' is not valid.");
		}

		$this->type = $type;
		return $this;
	}


	public function create(int|string $editId = null, $languageId = null, $columnId = null): Form
	{
		//setter
		$this->languageId = $languageId;
		if (!isset($languageId)) {
			throw new \InvalidArgumentException("Language must be setted.");
		}

		$this->columnId = $columnId;

		//form
		$form = parent::create($editId);

		if(!$this->user->isLoggedIn()){
			$form->addText('author', 'Author')
				->setNullable();
		}

		$form->addText('title', 'Title')
			->setNullable();

		$form->addTextArea('text', 'Comment')
			->setRequired(VALIDATE_REQUIRED);

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded(Form $form, $values)
	{
		unset($values->editId);

		if (!isset($this->type)) {
			throw new \InvalidArgumentException("Type is not defined, use setType() method.");
		}

		$values->language_id = $this->languageId;
		$values->user_ip = "";
		$values->user_agent = "";

		//@todo: vkladani do tabulky - column id
		$model = $this->service->insert($this->type, $this->columnId, $values );

		/*if ($this->isEditMode()) {
			//if default - dont update name
			$model->update($this->getEditId(), $values);
		} else {
			$model->insert($values);
		}*/

		$form->getPresenter()->redirect('this');
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		$this->setType($this->service->getTypeByStoredItem($editId));

		$defaults = $this->model->getById($editId);

		$form->setDefaults($defaults);
	}


	/**
	 * Reply form
	 */
	public function createReply($replyTo = null, $languageId = null): Form
	{
		//setter
		$this->languageId = $languageId;
		if (!isset($languageId)) {
			throw new \InvalidArgumentException("Language must be setted.");
		}

		//form
		$form = parent::create(null);

		if(!$this->user->isLoggedIn()){
			$form->addText('author', 'Author')
				->setNullable();
		}

		$form->addText('title', 'Title')
			->setNullable();

		$form->addTextArea('text', 'Comment')
			->setRequired(VALIDATE_REQUIRED);

		$form->addHidden("reply_to", $replyTo);

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceededReply');
		return $form;
	}


	public function formSucceededReply(Form $form, $values)
	{
		unset($values->editId);

		$replyTo = $values["reply_to"];
		unset($values["reply_to"]);

		$values->language_id = $this->languageId;
		$values->user_ip = "";
		$values->user_agent = "";

		//@todo: vkladani do tabulky - column id
		$this->service->insertReply($replyTo, $values );

		/*if ($this->isEditMode()) {
			//if default - dont update name
			$model->update($this->getEditId(), $values);
		} else {
			$model->insert($values);
		}*/

		$form->getPresenter()->redirect('this');
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValuesReply(Form $form, int $editId): void
	{
		//$this->setType($this->service->getTypeByStoredItem($editId));

		//$defaults = $this->model->findById($editId)->fetch();
		$defaults = array(
			"reply_to" => $editId
		);

		$form->setDefaults($defaults);
	}

}
