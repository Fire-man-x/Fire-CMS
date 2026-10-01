<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Forms\BaseFormFactory;
use App\Model;
use App\Service\LanguageService;
use App\Service\ProjectFolders;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\Finder;
use Nette\Utils\Html;


class SettingFormFactory extends BaseFormFactory
{
	/** Kontejner globálních (nepřekládaných) hodnot - klíče z Settings::GLOBAL_COLUMN_MAP */
	private const GlobalContainer = 'global';

	public function __construct(
		private Model\Database\Settings $model,
		private LanguageService         $languages,
		private Translator              $translator,
		private ProjectFolders          $projectFolders,
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

		// globální (nepřekládané) hodnoty jednou ve vlastním kontejneru - dřív se opakovaly v každém jazyce
		// a při uložení vyhrála hodnota z posledního jazykového kontejneru
		$form->addGroup('Global settings');
		$global = $form->addContainer(self::GlobalContainer);
		$global->addText('image_resolution', 'Resize image after upload to')
			->setNullable()
			->setTranslator(null)
			->addRule(Form::Pattern, VALIDATE_FORMAT, "[0-9]*x[0-9]*")
			->getControlPrototype()->placeholder("1000x1000");
		$global->addSelect('themePath', 'Templates', $templatesItems)
			->setTranslator(null);
		$global->addText('contact_phone', 'Phone')
			->setHtmlType('tel')
			->setNullable()
			->setMaxLength(50);
		$global->addText('map_latitude', 'Map - latitude (GPS)')
			->setNullable()
			->setHtmlType('number')
			->setHtmlAttribute('step', 'any')
			->addCondition(Form::Filled)
				->addRule(Form::Float, VALIDATE_FORMAT)
				->addRule(Form::Range, VALIDATE_FORMAT, [-90, 90]);
		$global->addText('map_longitude', 'Map - longitude (GPS)')
			->setNullable()
			->setHtmlType('number')
			->setHtmlAttribute('step', 'any')
			->addCondition(Form::Filled)
				->addRule(Form::Float, VALIDATE_FORMAT)
				->addRule(Form::Range, VALIDATE_FORMAT, [-180, 180]);

		foreach ($this->languages->getLanguages() as $languageId => $language) {
			// Html = renderer popisek skupiny už znovu nepřekládá (jinak by "Jazyk: CS" skončil v nepřeložených)
			$form->addGroup(Html::el()->setText($this->translator->translate('Language') . ': ' . $language));
			$container = $form->addContainer($languageId);

			$container->addText('main_title', 'Web title')
				->setNullable();
			$container->addText('main_description', 'Web description')
				->setNullable();
			$container->addText('main_email', 'Email')
				->setHtmlType('email')
				->setNullable()
				->addRule(Form::Email, VALIDATE_FORMAT);
			$container->addTextArea('contact_address', 'Address', null, 3)
				->setNullable();

			$container->addText('seo_title', 'SEO title')
				->setNullable();
			$container->addText('seo_description', 'SEO description')
				->setNullable();
			$container->addText('seo_keywords', 'SEO keywords')
				->setNullable();
		}

		$form->setCurrentGroup();
		$form->addSubmit('send', 'Save');

		//default values, global values + one language per container
		$defaults = [self::GlobalContainer => $this->model->getAllForLanguage($this->languages->getDefaultLanguage())];
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
		$this->model->saveGlobal($settingId, $values->{self::GlobalContainer});
		unset($values->{self::GlobalContainer});
		foreach ($values as $languageId => $languageValues) {
			$this->model->saveForLanguage($settingId, $languageId, $languageValues);
		}
	}
}
