<?php
declare(strict_types=1);

namespace Nette\Forms\Rendering;

use Nette\Forms\Control;
use Nette\Forms\Controls;
use Nette\Forms\Form;
use Nette\Utils\Html;
use Vodacek\Forms\Controls\DateInput;

/**
 * Renderer for Twitter Bootstrap v3
 */
class TwitterBootstrapRenderer extends DefaultFormRenderer
{

	/**
	 * If first button, then is primary
	 */
	private bool $usedPrimary = false;

	public function __construct()
	{
		$this->wrappers['controls']['container'] = null;
		$this->wrappers['pair']['container'] = 'div class="form-group row"';
		$this->wrappers['pair']['.error'] = 'has-error';
		$this->wrappers['control']['container'] = 'div class="col-sm-9"'; //'div class="col-sm-9 input-group"'
		$this->wrappers['label']['container'] = 'div class="col-sm-3 control-label"';
		$this->wrappers['control']['description'] = 'span class=help-block';
		$this->wrappers['control']['errorcontainer'] = 'span class=help-block';
	}


	public function render(Form $form, string $mode = null): string
	{

		// make form and controls compatible with Twitter Bootstrap
		$form->getElementPrototype()->addClass('form-horizontal');

		/*if ($this->form !== $form) {
			$this->form = $form;
		}
		foreach ($form->getControls() as $control) {
			$this->renderControl($control);
		}*/

		return parent::render($form, $mode);
	}


	/**
	 * Renders validation errors (per form or per control).
	 * @note workaround Bugfix
	 */
	public function renderErrors(Control $control = null, bool $own = true): string
	{
		$translator = $this->form ? $this->form->getTranslator() : null;

		$errors = $control
			? $control->getErrors()
			: ($own ? $this->form->getOwnErrors() : $this->form->getErrors());
		if (!$errors) {
			return '';
		}
		$container = $this->getWrapper($control ? 'control errorcontainer' : 'error container');
		$item = $this->getWrapper($control ? 'control erroritem' : 'error item');

		foreach ($errors as $error) {
			$item = clone $item;
			if ($error instanceof IHtmlString) {
				$item->addHtml($error);
			} else {
				$item->setText($translator ? $translator->translate($error) : $error);
			}
			$container->addHtml($item);
		}
		return "\n" . $container->render($control ? 1 : 0);
	}


	/**
	 * Renders form body.
	 */
	public function renderBody(): string{
		foreach($this->form->getControls() as $control){
			if($control instanceof Controls\SubmitButton){
				$control->getControlPrototype()->addClass('btn btn-primary');
			}
		}
		return parent::renderBody();
	}


	/**
	 * Renders 'control' part of visual row of controls.
	 */
	public function renderControl(Control $control): Html
	{
		if ($control instanceof Controls\Button) {
				$control->getControlPrototype()->addClass(!$this->usedPrimary ? 'btn btn-primary' : 'btn btn-outline-dark');
				$this->usedPrimary = true;

			} elseif ($control instanceof Controls\TextBase || $control instanceof Controls\SelectBox ||
				$control instanceof Controls\MultiSelectBox || $control instanceof Controls\UploadControl ||
				$control instanceof DateInput) {
				/*if ($control instanceof Controls\TextBase){
					$icon = \Nette\Utils\Html::el("span")->setText("rusaci")->addClass('input-group-btn glyphicon glyphicon-th');
					$control->getControlPrototype()->add(\Nette\Utils\Html::el("span")->setText("praha"));
				}*/
				$control->getControlPrototype()->addClass('form-control');

			} elseif ($control instanceof Controls\Checkbox || $control instanceof Controls\CheckboxList || $control instanceof Controls\RadioList) {
				$control->getSeparatorPrototype()->setName('div')->addClass("form-check")->addClass($control->getControlPrototype()->type);
				$control->getLabelPrototype()->addClass("form-check-label");
				$control->getControlPrototype()->addClass("form-check-input");
			}
		return parent::renderControl($control);
	}

}
