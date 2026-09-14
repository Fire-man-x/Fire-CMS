<?php
declare(strict_types=1);

namespace App\Forms\CategorySubtype;

use Nette\Application\UI\Form;

/**
 * Interface for form part
 */
interface ICategoryFormType //extends compone
{

	//public $type;
	/**
	 * Type getter
	 */
	public function getType();


	/**
	 * Create form part
	 */
	public function createFormPart(Form $form);


	/**
	 * Default values setter
	 */
	public function setDefaultValuesToFormPart(Form $form, array $values);


	/**
	 *
	 * @return ArrayHash
	 */
	public function onSuccessFormPart(Form $form, array $values, $editId);

}
