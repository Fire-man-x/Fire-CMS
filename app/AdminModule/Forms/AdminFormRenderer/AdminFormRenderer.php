<?php
declare(strict_types=1);

namespace App\AdminModule\Forms\AdminFormRenderer;

use App\AdminModule\Forms\AdminForm;
use Latte\Engine;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\Button;
use Nette\Forms\Controls\Checkbox;
use Nette\Forms\Controls\MultiSelectBox;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Form;
use Nette\Forms\FormRenderer;
use Nette\Forms\Rendering\TwitterBootstrapRenderer;

/**
 * Renders an AdminForm's visual groups as a Bootstrap 5 accordion, one accordion-item per group,
 * plus whatever control isn't in any group (e.g. the submit button) below it. Falls back to the
 * app's normal TwitterBootstrapRenderer for a plain (non-AdminForm) Nette\Application\UI\Form.
 */
class AdminFormRenderer implements FormRenderer
{
	public function render(Form $form): string
	{
		if (!$form instanceof AdminForm) {
			return (new TwitterBootstrapRenderer())->render($form);
		}

		$sections = [];
		$grouped = [];
		$index = 0;
		foreach ($form->getGroups() as $group) {
			if (!$group->getOption('visual')) {
				continue;
			}

			$fields = [];
			foreach ($group->getControls() as $control) {
				$grouped[spl_object_id($control)] = true;
				if (!$control instanceof BaseControl || $control->getOption('type') === 'hidden') {
					continue;
				}

				$fields[] = ['control' => $control, 'class' => self::controlCssClass($control)];
			}

			$sections[] = [
				'id' => 'section-' . $index++,
				'title' => (string) $group->getOption('label'),
				'description' => $group->getOption('description'),
				'fields' => $fields,
			];
		}

		$loose = [];
		foreach ($form->getComponents() as $control) {
			if (!$control instanceof BaseControl || isset($grouped[spl_object_id($control)])) {
				continue;
			}

			$loose[] = ['control' => $control, 'class' => self::controlCssClass($control)];
		}

		$engine = new Engine();
		$engine->addExtension(new \Nette\Bridges\FormsLatte\FormsExtension());
		return $engine->renderToString(__DIR__ . '/default.latte', [
			'form' => $form,
			'sections' => $sections,
			'loose' => $loose,
		]);
	}


	private static function controlCssClass(BaseControl $control): string
	{
		return match (true) {
			$control instanceof Checkbox => 'form-check-input',
			$control instanceof SelectBox, $control instanceof MultiSelectBox => 'form-select',
			$control instanceof Button => 'btn btn-primary',
			default => 'form-control',
		};
	}

}
