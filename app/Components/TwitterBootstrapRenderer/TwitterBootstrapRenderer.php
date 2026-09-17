<?php
declare(strict_types=1);

namespace Nette\Forms\Rendering;

use Nette\Forms\Control;
use Nette\Forms\Controls;
use Nette\Forms\Form;
use Nette\HtmlStringable;
use Nette\Utils\Html;
use Vodacek\Forms\Controls\DateInput;

/**
 * Renderer for Twitter Bootstrap v5
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
		// Bootstrap 5 removed `.form-group` - horizontal rows are plain `.row` + spacing utility.
		$this->wrappers['pair']['container'] = 'div class="row mb-3"';
		$this->wrappers['control']['container'] = 'div class="col-sm-9"'; //'div class="col-sm-9 input-group"'
		// `.control-label` (Bootstrap 3) renamed to `.col-form-label` in Bootstrap 4/5.
		$this->wrappers['label']['container'] = 'div class="col-sm-3 col-form-label"';
		// `.help-block` (Bootstrap 3) replaced by `.form-text` in Bootstrap 4/5.
		$this->wrappers['control']['description'] = 'small class=form-text';
		// `.invalid-feedback` is hidden by default and only shown via a sibling `.is-invalid` control
		// (see 'control .error' below) - unlike Bootstrap 3's `.help-block`, it's not shown unconditionally.
		$this->wrappers['control']['errorcontainer'] = 'div class="invalid-feedback"';
		// Bootstrap 4/5 validation styling lives on the control itself, not on the row - there's no
		// row-level error class to set anymore (Bootstrap 3's `.has-error` had no effect since Bootstrap 4).
		$this->wrappers['control']['.error'] = 'is-invalid';
	}


	public function render(Form $form, string $mode = null): string
	{

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
			if ($error instanceof HtmlStringable) {
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

			} elseif ($control instanceof Controls\SelectBox || $control instanceof Controls\MultiSelectBox) {
				// Bootstrap 4's `.custom-select` was renamed to `.form-select` and became the only
				// supported way to style a native <select> in Bootstrap 5 - `.form-control` no longer
				// gives it the right appearance.
				$control->getControlPrototype()->addClass('form-select');

			} elseif ($control instanceof Controls\TextBase || $control instanceof Controls\UploadControl ||
				$control instanceof DateInput) {
				/*if ($control instanceof Controls\TextBase){
					$icon = \Nette\Utils\Html::el("span")->setText("rusaci")->addClass('input-group-btn glyphicon glyphicon-th');
					$control->getControlPrototype()->add(\Nette\Utils\Html::el("span")->setText("praha"));
				}*/
				$control->getControlPrototype()->addClass('form-control');

			} elseif ($control instanceof Controls\Checkbox || $control instanceof Controls\CheckboxList || $control instanceof Controls\RadioList) {
				$control->getSeparatorPrototype()->setName('div')->addClass("form-check");
				$control->getLabelPrototype()->addClass("form-check-label");
				$control->getControlPrototype()->addClass("form-check-input");
			}
		return parent::renderControl($control);
	}

}
