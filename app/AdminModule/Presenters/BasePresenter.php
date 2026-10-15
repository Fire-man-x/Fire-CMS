<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Secured;
use App\Model\Exceptions\RecordNotFoundException;
use App\Security\Resource;
use App\Security\ResourceOwners;
use App\Security\User;
use App\Service\LanguageService;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Bridges\SecurityHttp\SessionStorage;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends \App\Presenters\BasePresenter
{

	/**
	 * Language from url
	 */
	#[Persistent]
	public string $locale;

	/**
	 * Edit language from url
	 */
	#[Persistent]
	public string $editLocale;

	/**
	 * Is window mode?
	 */
	#[Persistent]
	public bool $isWindowMode = false;

	/** @inject */
	public LanguageService $languages;

	/** Sekce do menu administrace (články a kategorie po sekcích), viz beforeRender() */
	protected \App\Model\Database\Sections $sectionsModel;

	/** Autor záznamu pro #[Secured(recordIdParameter: ...)], viz checkSecured() */
	private ResourceOwners $resourceOwners;


	/**
	 * Inject metoda (ne veřejná @inject vlastnost) - potomci si konstruktor nechávají pro vlastní závislosti
	 */
	public function injectSections(\App\Model\Database\Sections $sectionsModel): void
	{
		$this->sectionsModel = $sectionsModel;
	}


	public function injectResourceOwners(ResourceOwners $resourceOwners): void
	{
		$this->resourceOwners = $resourceOwners;
	}


	protected function beforeRender(): void
	{
		parent::beforeRender();

		$this->template->adminSections = $this->getUser()->isLoggedIn() ? $this->sectionsModel->getList() : [];

		//is window mode
		$this->template->isWindowMode = $this->isWindowMode;
		$this->template->languages = $this->languages->getLanguages();
		$this->template->actualLanguage = $this->editLocale;
	}


	/**
	 * Check requirements to presenter
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Nette\Application\AbortException
	 * @throws \Nette\Application\UI\InvalidLinkException
	 */
	public function checkRequirements(\ReflectionClass|\ReflectionMethod $element): void
	{
		//different login in backend and frontend
		$userStorage = $this->getUser()->getStorage();
		if ($userStorage instanceof SessionStorage) {
			$userStorage->setNamespace('admin');
		}

		//setup translator
		//different translations in backend and frontend
		$this->translator->setAvailableLanguages($this->languages->getAdminLanguages());
		$this->translator->setCurrentLang($this->languages->getDefaultAdminLanguage());
		$this->translator->setNamespace("admin");

		if(!isset($this->editLocale)){
			throw new BadRequestException("Edit locale is not setted.");
		}
		if(!$this->languages->existLanguage($this->editLocale)){
			throw new BadRequestException("Language '".$this->editLocale."' doesn't exist.");
		}

		parent::checkRequirements($element);

		//Not logged in, so redirect to sign
		if (!$this->getUser()->isLoggedIn() && !$this->isLinkCurrent(":Admin:Sign:*")) {
			if ($this->user->getLogoutReason() == User::LogoutInactivity) {
				$this->flashMessage('You have been signed out due to inactivity. Please sign in again.');
			}

			$this->redirect(':Admin:Sign:in', array('id' => null, 'backlink' => $this->isLinkCurrent(":Admin:Default:default") ? null : $this->storeRequest()));
		}
		elseif($this->getUser()->isLoggedIn() && !$this->getUser()->isAllowed("Admin") && !$this->isLinkCurrent(":Admin:Sign:*")){
			$this->flashMessage("You have not access to administration.", FLASH_FAILED);

			$this->redirect(':Admin:Sign:in', array('id' => null, 'backlink' => $this->isLinkCurrent(":Admin:Default:default") ? null : $this->storeRequest()));
		}

		// $element je ReflectionClass (přístup k presenteru) nebo ReflectionMethod (volání action*/handle*/render*),
		// každý #[Secured] (atribut lze opakovat) se ověřuje zvlášť
		try {
			foreach ($element->getAttributes(Secured::class) as $attribute) {
				$this->checkSecured($attribute->newInstance());
			}
		} catch (ForbiddenRequestException $e) {
			$this->flashMessage($e->getMessage(), FLASH_FAILED, false);
			$this->redirect(":Admin:Default:default");
		}
	}


	/**
	 * Ověří jeden #[Secured]. S parametrem owner se ptá na zdroj s ID autora záznamu - autor smí se svým
	 * záznamem cokoliv (pravidlo v AuthorizatorFactory), bez ID záznamu v požadavku platí právo privilegeWithoutRecord.
	 * @throws ForbiddenRequestException
	 * @throws BadRequestException neplatné nebo neexistující ID záznamu
	 */
	private function checkSecured(Secured $secured): void
	{
		$resource = $secured->resource;
		$privilege = $secured->privilege;

		if ($secured->recordIdParameter !== null) {
			$id = $this->getParameter($secured->recordIdParameter);
			if ($id === null || $id === '') {
				$privilege = $secured->privilegeWithoutRecord ?? $privilege;
			} else {
				$recordId = filter_var($id, FILTER_VALIDATE_INT);
				if ($recordId === false) {
					$this->error("Parameter '$secured->recordIdParameter' is not a valid ID.");
				}

				try {
					$resource = new Resource($secured->resource, $this->resourceOwners->getOwnerId($secured->resource, $recordId));
				} catch (RecordNotFoundException $e) {
					$this->error($e->getMessage());
				}
			}
		}

		if (!$this->user->isAllowed($resource, $privilege)) {
			throw new ForbiddenRequestException((string) $this->translator->translate("You have not access to resouce '%s' with privilege '%s'.", $this->translator->translate($secured->resource), $this->translator->translate($privilege)));
		}
	}


	protected function startup(): void
	{
		parent::startup();

		//base breadcrumb
		$this->addBreadCrumbLink('Home', $this->link(':Admin:Default:', array("id"=>null)), 'fa fa-home');

		//BaseGrid global disable css and js
		//\Mesour\Datagrid\BaseGrid::$css_draw = false;
		//\Mesour\Datagrid\BaseGrid::$js_draw = false;

		//appdir
		//$configParameters = $this->context->getParameters();
		//$this->template->appDir = $configParameters["appDir"];

		//trace URL in stalker
		//$this->getPlugin('stalker')->traceUrl($this->user);
	}

}
