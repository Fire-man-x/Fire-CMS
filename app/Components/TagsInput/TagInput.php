<?php
declare(strict_types=1);

namespace Achse\TagInput;

use Nette;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Json;

class TagInput extends TextInput
{

	/**
	 * Separator for values
	 */
	public static string $separator = ",";

	private DataSourceDescriptor $dataSourceDescriptor;


	/**
	 * Register TagInput
	 * @return void
	 */
	public static function register(string $control_name = 'addTagInput')
	{
		\Nette\Forms\Container::extensionMethod(
			'Nette\Forms\Container::' . $control_name, function (Nette\Forms\Container $form, $name, $label = null, DataSourceDescriptor $dataSourceDescriptor = null, $maxLength = null) {
			$control = new self($dataSourceDescriptor, $label, $maxLength);

			return $form[$name] = $control;

			/* $form->addComponent($control, $name);
			  return $component; */
		}
		);
	}


	/**
	 * @inheritdoc
	 */
	public function __construct(DataSourceDescriptor $dataSourceDescriptor, $label = null, $maxLength = null)
	{
		parent::__construct($label, $maxLength);
		$this->dataSourceDescriptor = $dataSourceDescriptor;
	}


	/**
	 * @inheritdoc
	 */
	public function getControl(): Nette\Utils\Html
	{
		$input = parent::getControl();
		$input->addAttributes(['data-tagInput' => Json::encode($this->dataSourceDescriptor)]);
		return $input;
	}


	/**
	 * Sets control's value.
	 * @param string|array $value
	 * @internal
	 */
	public function setValue(mixed $value): static
	{
		if (is_array($value)) {
			$value = implode(self::$separator, $value);
		}

		return parent::setValue($value);
	}


	/**
	 * Value getter
	 */
	public function getValue(): mixed
	{
		$value = parent::getValue();
		if($value === null || empty($value)){
			return array();
		}
		return \Nette\Utils\Arrays::map(explode(self::$separator, $value), function($value){
				return \Nette\Utils\Strings::trim($value);
		});
	}

}
