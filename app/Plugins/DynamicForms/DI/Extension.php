<?php
declare(strict_types=1);

namespace App\Plugins\DynamicForms\DI;

use App\Plugins\DynamicForms\Components\ContactFormControl;
use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\Utils\Callback;

class Extension extends CompilerExtension
{
	private static ContactFormControl $contactFormControl;
	private static Application $application;

	/**
	 * Load configuration.
	 */
	public function loadConfiguration(): void
	{
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		/*$builder->addDefinition($this->prefix('cf'))
			->setClass('\App\Components\Shortcodes');*/
	}

	public function beforeCompile(): void
	{
		/** @var ContainerBuilder $builder */
		$builder = $this->getContainerBuilder();

		//renderForm shortcode "contactForm"
		/*$builder->getDefinitionByType('\App\Components\Shortcodes')
			//->addSetup("add", array("contactForm", $builder->getDefinition("contactFormControl")->render()));//array(ContactForm::class, "render")));
			->addSetup("add", array("contactForm", array(self::class, "renderForm")));*/
	}

	public function afterCompile(\Nette\PhpGenerator\ClassType $class): void
	{
		parent::afterCompile($class);

		// Skip in CLI (bin/console): this wiring eagerly builds ContactFormControl, which cascades
		// through ContactFormFactory -> CommentsModule\Comment -> security.user -> authorizator ->
		// Roles::getListWithName() - i.e. it queries the `roles` table as a side effect of container
		// initialize(), unconditionally, on EVERY boot. On a fresh/empty database (e.g. before the
		// first `bin/console migrations:continue`/`migrations:reset` has run) this crashes with
		// "Table 'roles' doesn't exist" before the console command itself ever gets a chance to run -
		// and a console script never renders the "contactForm" Latte shortcode anyway, so it's safe
		// to skip entirely here.
		if ($this->getContainerBuilder()->parameters['consoleMode']) {
			return;
		}

		$initialize = $class->getMethod('initialize');
		$initialize->addBody(self::class.'::setContactFormControl($this->getByType(?), $this->getService(?));',
			array(ContactFormControl::class, 'application.application')
		);
	}

	/**
	 * ContactFormFactory setter
	 * @note Is Called from "afterCompile" container
	 */
	public static function setContactFormControl(ContactFormControl $contactFormControl, Application $application): void
	{
		self::$contactFormControl = $contactFormControl;
		self::$application = $application;
	}

	/**
	 * Render form
	 * @param  string $controlNameFromTemplate string
	 */
	public static function renderForm($shortcodeParams): string
	{
		//set parent
		self::$contactFormControl->setParent(self::$application->getPresenter(), 'contactFormControl');

		//set language
		if(isset(self::$application->getPresenter()->editLocale))
		{
			self::$contactFormControl->setLanguage(self::$application->getPresenter()->editLocale);
		}

		ob_start();
		Callback::invokeArgs(array(self::$contactFormControl, 'render'), $shortcodeParams);

		//return text
		return ob_get_clean();
	}
}