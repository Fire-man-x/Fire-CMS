<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use App\Service\ProjectFolders;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\Finder;


class SettingFormFactory extends BaseFormFactory
{

	public function __construct(FormFactory $factory, private Model\Options $model, private Translator $translator, private ProjectFolders $projectFolders)
	{
		parent::__construct($factory);
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addGroup("Main");

		$form->addText('main_title', 'Web title')
			->setRequired(VALIDATE_REQUIRED);
		$form->addText('main_description', 'Web description');
		//$form['Setting_id']->setDefaultValue($editId);
		$form->addText('main_email', 'Email')
			->setHtmlType('email')
			->setRequired()
			->addRule(Form::Email, VALIDATE_FORMAT);

		$form->addGroup("Default SEO");
		$form->addText('seo_title', 'SEO title');
		$form->addText('seo_description', 'SEO description');
		$form->addText('seo_keywords', 'SEO keywords');

		$form->addGroup("Images");
		$form->addText('image_resolution', $this->translator->translate('Resize image after upload to'))
			->setRequired(false)
			->setTranslator(null)
			->addRule(Form::Pattern, VALIDATE_FORMAT, "[0-9]*x[0-9]*")
			->getControlPrototype()->placeholder("1000x1000");

		//templates
		$templatesItems = array('default' => $this->translator->translate('Default'));
		$dirs = Finder::findDirectories()->in($this->projectFolders->getWwwThemeDir());
		foreach($dirs as $dir)
		{
			/** @var \SplFileInfo $dir */
			$dirName = $dir->getFilename();
			if($dirName == 'default')
			{
				continue;
			}

			$templatesItems[$dirName] = $dirName;
		}
		$form->addSelect('themePath', $this->translator->translate('Templates'), $templatesItems)
			->setTranslator(null);

		//send
		$form->addSubmit('send', 'Save');

		//default values
		$values = $this->model->findAll()->fetchPairs("key", "value");
		$form->setDefaults($values);

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded($form, $values)
	{
		unset($values->editId);

		foreach ($values as $key => $value){
			$this->model->useOption($key, $value);
		}
	}
}
