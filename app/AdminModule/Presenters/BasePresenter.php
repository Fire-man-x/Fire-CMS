<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Security\User;
use App\Service\LanguageService;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;


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
	protected \App\Model\Sections $sectionsModel;


	/**
	 * Inject metoda (ne veřejná @inject vlastnost) - potomci si konstruktor nechávají pro vlastní závislosti
	 */
	public function injectSections(\App\Model\Sections $sectionsModel): void
	{
		$this->sectionsModel = $sectionsModel;
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
	 * @param \Nette\Application\UI\MethodReflection|\Nette\Application\UI\ComponentReflection $element
	 * @return void
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Nette\Application\AbortException
	 * @throws \Nette\Application\UI\InvalidLinkException
	 */
	public function checkRequirements($element): void
	{
		//different login in backend and frontend
		$this->getUser()->getStorage()->setNamespace('admin');

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

		// $element is a ReflectionClass (presenter access) or a ReflectionMethod
		// (action*/handle*/render* call). Both expose getAttributes().
		$resource = $element;

		try{
			if ($resource->getAttributes(Secured::class)) {
				$resourceAttrs = $resource->getAttributes(Resource::class);
				$privilegeAttrs = $resource->getAttributes(Privilege::class);

				if ($resourceAttrs && $privilegeAttrs) {
					$resources = [];
					foreach ($resourceAttrs as $attr) {
						$resources = array_merge($resources, $attr->newInstance()->names);
					}

					$privileges = [];
					foreach ($privilegeAttrs as $attr) {
						$privileges = array_merge($privileges, $attr->newInstance()->names);
					}

					foreach ($resources as $securedResource) {
						foreach ($privileges as $securedPrivilege) {
							if (!$this->user->isAllowed($securedResource, $securedPrivilege)) {
								throw new ForbiddenRequestException($this->translator->translate("You have not access to resouce '%s' with privilege '%s'.", $securedResource, $securedPrivilege));
							}
						}
					}
				}
			}
		} catch(ForbiddenRequestException $e) {
			$this->flashMessage($e->getMessage(), FLASH_FAILED, false);
			$this->redirect(":Admin:Default:default");
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
