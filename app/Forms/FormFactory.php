<?php
declare(strict_types=1);

namespace App\Forms;

use Nette;
use Nette\Application\UI\Form;
use Nette\Forms\Rendering\TwitterBootstrapRenderer;


class FormFactory
{
	use Nette\SmartObject;

	public function create(): Form
	{
		$form = new Form;
		$form->setRenderer(new TwitterBootstrapRenderer());
		return $form;
	}

}
