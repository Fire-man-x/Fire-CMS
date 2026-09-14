<?php
declare(strict_types=1);

/**
 * @package     App\Controls
 */

namespace App\Controls\Controls;

use Nette;

class TextInputCustomLabel extends Nette\Forms\Controls\TextInput
{

	/**
	 * Register TextInputCustomLabel
	 * @return void
	 */
	public static function register(string $control_name = 'addTextCustomLabel') {
		\Nette\Forms\Container::extensionMethod(
			'Nette\Forms\Container::' . $control_name,
			function ($form, $name, $label = null, array $items = null) {
				$control = new self($label, $items);

				return $form[$name] = $control;
			}
		);
	}


	/**
	 * Generates label's HTML element.
	 * @param  string
	 * @return Html|string
	 */
	public function getLabel($caption = null)
	{
		$label = clone $this->label;
		$label->for = $this->getHtmlId();

		if (!$label->getHtml()) {
			$label->setText($this->translate($caption === null ? $this->caption : $caption));
		}

		return $label;
	}

}
