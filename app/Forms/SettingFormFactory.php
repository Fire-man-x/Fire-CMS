<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use App\Service\LanguageService;
use App\Service\ProjectFolders;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\Finder;


class SettingFormFactory extends BaseFormFactory
{

	public function __construct(
		private Model\Settings $model,
		private LanguageService $languages,
		private Translator $translator,
		private ProjectFolders $projectFolders,
	) {
		parent::__construct();
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$templatesItems = array('default' => $this->translator->translate('Default'));
		$dirs = Finder::findDirectories()->in($this->projectFolders->getWwwThemeDir());
		foreach ($dirs as $dir) {
			/** @var \SplFileInfo $dir */
			$dirName = $dir->getFilename();
			if ($dirName == 'default') {
				continue;
			}

			$templatesItems[$dirName] = $dirName;
		}

		foreach ($this->languages->getLanguages() as $languageId => $language) {
			$container = $form->addContainer($languageId);

			$container->addText('main_title', 'Web title')
				->setNullable();
			$container->addText('main_description', 'Web description')
				->setNullable();
			$container->addText('main_email', 'Email')
				->setHtmlType('email')
				->setNullable()
				->addRule(Form::Email, VALIDATE_FORMAT);

			$container->addText('seo_title', 'SEO title')
				->setNullable();
			$container->addText('seo_description', 'SEO description')
				->setNullable();
			$container->addText('seo_keywords', 'SEO keywords')
				->setNullable();

			$container->addText('image_resolution', $this->translator->translate('Resize image after upload to'))
				->setNullable()
				->setTranslator(null)
				->addRule(Form::Pattern, VALIDATE_FORMAT, "[0-9]*x[0-9]*")
				->getControlPrototype()->placeholder("1000x1000");

			$container->addSelect('themePath', $this->translator->translate('Templates'), $templatesItems)
				->setTranslator(null);
		}

		$form->addSubmit('send', 'Save');

		//default values, one language per container
		$defaults = [];
		foreach ($this->languages->getLanguages() as $languageId => $language) {
			$defaults[$languageId] = $this->model->getAllForLanguage($languageId);
		}
		$form->setDefaults($defaults);

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded($form, $values)
	{
		unset($values->editId);

		$settingId = $this->model->getMainSettingId();
		foreach ($values as $languageId => $languageValues) {
			$this->model->saveForLanguage($settingId, $languageId, $languageValues);
		}
	}
}
