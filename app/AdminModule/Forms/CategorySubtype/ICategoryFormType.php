<?php
declare(strict_types=1);

namespace App\AdminModule\Forms\CategorySubtype;

use App\AdminModule\Forms\AdminForm;
use App\AdminModule\Forms\AdminFormValues;

/**
 * Interface for form part
 */
interface ICategoryFormType //extends compone
{

	//public $type;
	/**
	 * Type getter
	 */
	public function getType(): string;


	/**
	 * Create form part - pole překladu patří do kontejneru `locales[$language]`, ostatní do `data`
	 */
	public function createFormPart(AdminForm $form, string $language): void;


	/**
	 * Default values setter
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	public function setDefaultValuesToFormPart(AdminForm $form, array $values): array;


	public function onSuccessFormPart(AdminForm $form, AdminFormValues $values, string $language, int $editId): AdminFormValues;

}
