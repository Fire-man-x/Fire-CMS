<?php
declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * Abstract Base Form factory
 * @method self addOwnDate($label = null, $type = self::TYPE_DATETIME_LOCAL)
 */
abstract class BaseFormFactory
{
	use SmartObject;

	/**
	 * Edit id, for indication of edit mode
	 */
	private int|string|null $editId = null;

	/**
	 * Is form for modal?
	 */
	private bool $isModal = false;

	private FormFactory $factory;

	private array $formParts = array();


	/**
	 * Set edit mode, store edit id
	 */
	public function setEditId(int|string $editId)
	{
		$this->editId = $editId;
	}


	/**
	 * Get edit id, if edit mode
	 */
	public function getEditId(): null|int|string
	{
		return $this->editId;
	}


	/**
	 * Reset edit mode
	 */
	public function resetEditMode()
	{
		$this->editId = null;
	}


	/**
	 * Is form for edit?
	 */
	public function isEditMode(): bool
	{
		return isset($this->editId);
	}


	/**
	 * Mark form for modal use
	 */
	public function asModal()
	{
		$this->isModal = true;
		return $this;
	}


	/**
	 * Is form for modal?
	 */
	public function isModal(): bool
	{
		return $this->isModal;
	}


	/**
	 * Add form part to form
	 */
	public function addFormPart(BaseFormPartFactory $formPart)
	{
		$this->formParts[] = $formPart;
	}


	/**
	 * Base form factory
	 */
	public function __construct(FormFactory $factory)
	{
		$this->factory = $factory;
	}


	protected function create(int|string|null $editId = null): Form
	{
		if ($this->isModal() && !is_null($editId)) {
			throw new \InvalidArgumentException("Can not be 'modal' and set 'editId' in constructor.");
		}

		if(!is_null($editId)){
			$this->setEditId($editId);
		}

		$form = $this->factory->create();

		if ($this->isModal()) {
			$form->addHidden("editId", $this->getEditId());
			$self = $this;
			$form->onValidate[] = function ($form, $values) use ($self) {
				if(!empty($values->editId)){
					$self->setEditId($values->editId);
				}
				unset($values->editId);
			};
		}

		foreach ($this->formParts as $formPart){
			/* @var $formPart BaseFormPartFactory */
			$formPart->createPart();
		}

		return $form;
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		if (!$this->isModal()) {
			throw new \InvalidArgumentException("Can not use in non 'modal' mode.");
		}
	}


	/**
	 * Check date format
	 */
	protected function checkDateFormat(string $string): ?\Nette\Utils\DateTime
	{
		if (preg_match("#^" . DATE_REGEXP . "$#", $string)) {
			return new \Nette\Utils\DateTime($string);
		} else {
			return null;
		}
	}


	/**
	 * Check time format
	 */
	protected function checkTimeFormat(string $string): ?string
	{
		if (preg_match("#^" . TIME_REGEXP . "$#", $string)) {
			return $string;
		} else {
			return null;
		}
	}


	/**
	 * Check date and time format
	 */
	protected function checkDateTimeFormat(string $string): ?\Nette\Utils\DateTime
	{
		if ($string instanceof DateTime || $string instanceof \DateTime || $string instanceof \DateTimeImmutable) {
			return $string;
		} elseif (preg_match("#^" . DATETIME_REGEXP . "$#", $string)) {
			return new \Nette\Utils\DateTime($string);
		} else {
			return null;
		}
	}

}
