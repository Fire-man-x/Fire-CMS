<?php
declare(strict_types=1);

namespace App\Components\FileManager;

use Nette\Application\UI\Template;

trait TPresenter
{

	/** @inject */
	public FileManager $fileManager;


	protected function createTemplate(?string $class = null): Template
	{
		//$template = $template ?: parent::createTemplate();
		//or
		$template = parent::createTemplate();
		$template->__imagestore = $this->fileManager;
		return $template;
	}

}
