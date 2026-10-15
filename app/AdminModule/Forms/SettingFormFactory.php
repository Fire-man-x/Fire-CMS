<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model;
use App\Service\LanguageService;
use App\Service\ProjectFolders;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nette\Utils\Finder;
use Nette\Utils\Html;


class SettingFormFactory extends AdminFormFactory
{
	public function __construct(
		private readonly Model\Database\Settings $model,
		private readonly LanguageService $languages,
		private readonly Translator $translator,
		private readonly ProjectFolders $projectFolders,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$templatesItems = array('default' => $this->translator->translate('Default'));
		$dirs = Finder::findDirectories()->in($this->projectFolders->getWwwThemeDir());
		foreach ($dirs as $dir) {
			/* * @var \SplFileInfo $dir */
			$dirName = $dir->getFilename();
			if ($dirName == 'default') {
				continue;
			}

			$templatesItems[$dirName] = $dirName;
		}

		// globální (nepřekládané) hodnoty jednou v kontejneru `data` (klíče z Settings::GLOBAL_COLUMN_MAP) - dřív se
		// opakovaly v každém jazyce a při uložení vyhrála hodnota z posledního jazykového kontejneru
		$form->addGroup('Global settings');
		$global = $form->data;
		$global->addText('image_resolution', 'Resize image after upload to')
			->setNullable()
			->setTranslator(null)
			->addRule(Form::Pattern, VALIDATE_FORMAT, "[0-9]*x[0-9]*")
			->getControlPrototype()->placeholder("1000x1000");
		$global->addSelect('themePath', 'Templates', $templatesItems)
			->setTranslator(null);
		$global->addText('main_email', 'Email')
			->setHtmlType('email')
			->setNullable()
			->setMaxLength(100)
			->addRule(Form::Email, VALIDATE_FORMAT);
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
			$group = $form->addGroup(Html::el()->setText($this->translator->translate('Language') . ': ' . $language));
			// kontejner jazyka přebírá skupinu z `locales` (ten vznikl u prvního jazyka) - skupinu nastavíme sami
			$container = $form->locales->addContainer((string) $languageId);
			$container->setCurrentGroup($group);

			$container->addText('main_title', 'Web title')
				->setNullable();
			$container->addText('main_description', 'Web description')
				->setNullable();
			// e-mail a telefon jsou i v globálním nastavení - prázdné pole jazyka = použije se globální hodnota
			$container->addText('main_email', 'Email')
				->setHtmlType('email')
				->setNullable()
				->setMaxLength(100)
				->setOption('description', 'If empty, the value from global settings is used.')
				->addCondition(Form::Filled)
					->addRule(Form::Email, VALIDATE_FORMAT);
			$container->addText('contact_phone', 'Phone')
				->setHtmlType('tel')
				->setNullable()
				->setMaxLength(50)
				->setOption('description', 'If empty, the value from global settings is used.');
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
		$form->buttons->addSubmit('send', 'Save');

		//default values, global values + one language per container - každá tabulka zvlášť, bez doplnění globální
		// hodnoty do jazyka (uložení by ji jinak zkopírovalo do jazyka a změna globální by se už neprojevila)
		$defaults = ['data' => $this->model->getGlobalValues(), 'locales' => []];
		foreach ($this->languages->getLanguages() as $languageId => $language) {
			$defaults['locales'][$languageId] = $this->model->getLanguageValues((string) $languageId);
		}
		$form->setDefaults($defaults);

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		$settingId = $this->model->getMainSettingId();
		$this->model->saveGlobal($settingId, $values->data);
		foreach ($values->locales as $languageId => $languageValues) {
			$this->model->saveForLanguage($settingId, (string) $languageId, ArrayHash::from($languageValues));
		}
	}
}
