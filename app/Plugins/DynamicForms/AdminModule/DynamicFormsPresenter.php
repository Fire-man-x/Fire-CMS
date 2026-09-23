<?php
declare(strict_types=1);

namespace App\Plugins\DynamicForms\AdminModule;

use App\AdminModule\Presenters\BasePresenter;
use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Modules\UrlModule\UrlManager;
use App\Plugins\DynamicForms\Forms\DynamicFormFormFactory;
use App\Plugins\DynamicForms\Forms\DynamicFormItemFormFactory;
use App\Plugins\DynamicForms\Model\DynamicForms;
use App\Service\LanguageService;
use Contributte\Datagrid\Datagrid;
use Nette;
use Nette\Application\Attributes\Persistent;

/**
 * DynamicForm Presenter
 */
#[Secured]
#[Resource('DynamicForms')]
#[Privilege('view')]
class DynamicFormsPresenter extends BasePresenter
{
	/**
	 * Not persistent revision parameter
	 */
	public static string $revisionParameter = "revision";

	/**
	 * Id
	 */
	#[Persistent]
	public int $id;

	/**
	 * Language
	 */
	//#[Persistent]
	//public string $language;

	/**
	 * Show filter
	 * @persistentInDefault
	 */
	public ?string $show;

	/**
	 * DynamicForm parent
	 */
	private ?int $parent;

	private ?string $language = null;

	/**
	 * Actual language
	 */
	public string $actualLanguage;

	/** @inject */
	public DynamicFormFormFactory $dynamicFormFormFactory;

	/** @inject */
	public DynamicFormItemFormFactory $dynamicFormItemFormFactory;

	/** @inject */
	public DynamicForms $dynamicFormsModel;

	/** @ inject */
	//public Service\DynamicForm $dynamicFormService;

	/** @inject */
	public UrlManager $urlManager;

	/** @inject */
	public LanguageService $languages;


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Dynamic forms", $this->link(":Admin:DynamicForms:default", array("id" => null)) );

		//default language
		if($this->language == $this->languages->getDefaultLanguage()) {
			$this->redirect("this", array("language" => null));
		}
	}


	#[Secured]
	#[Resource('DynamicForms')]
	#[Privilege('view')]
	public function actionDefault(string $show = null): void
	{
		$this->show = $show;
		if (!is_null($this->show) && !in_array($this->show, array("personal","pending","trash"))) {
			throw  new \InvalidArgumentException("Parameter show '$this->show' is not permited");
		}
		$this->template->show = $this->show;

	}


	/**
	 * @SecuredInside
	 * @Resource (DynamicForms)
	 * @Privilege (edit)
	 */
	public function actionDetail(int $parent = null): void
	{
		$this->parent = $parent;

		if ($this->languages->existLanguage($this->language)) {
			$this->actualLanguage = $this->language == null ? $this->languages->getDefaultLanguage() : $this->language;
		} else {
			$this->actualLanguage = $this->languages->getDefaultLanguage();
		}

		$this->template->dynamicForms = $this->dynamicFormsModel;

		//edit own
		$dynamicFormInfo = $this->dynamicFormsModel->getById($this->id);
		if(!$this->user->isAllowed(new \App\Security\Resource("DynamicForms", $dynamicFormInfo ? $dynamicFormInfo->createdBy : $this->getUser()->getId()),"edit")){
			throw new \Nette\Application\ForbiddenRequestException("You have not access to 'DynamicForms' with priviledge 'edit'.");
		}


		if($this->id)
		{
			if($dynamicFormInfo) {
				$this->addBreadCrumbLink($this->translator->translate("Dynamic form items"). ' - '.$dynamicFormInfo->templateName, $this->link(":Admin:DynamicForms:detail", array("id" => $this->id)), null, false);
			} else {
				$this->addBreadCrumbLink("Dynamic form items", $this->link(":Admin:DynamicForms:detail", array("id" => $this->id)));
			}
		}
		else
		{
			$this->addBreadCrumbLink("New", $this->link(":Admin:DynamicForms:detail", array("id" => null)));
		}
	}


	public function renderDetail(): void
	{
		$this->template->languages = $this->languages->getLanguages();
		$this->template->actualLanguage = $this->actualLanguage;
	}


	/**
	 * DynamicForms grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 */
	protected function createComponentDynamicFormsGrid(string $name): Datagrid
	{
		$source = $this->dynamicFormsModel->findAll()
			->select($this->dynamicFormsModel->getTableName() . ".*")
			->order($this->dynamicFormsModel->getTableName() . ".templateName")
			->order($this->dynamicFormsModel->getTableName() . ".".$this->dynamicFormsModel->getColumnId());
		/*switch ($this->show) {
			case "personal":
				$source->where($this->dynamicFormsModel->getTableName() . ".createdBy = ?", $this->user->getId());
				break;
			case "pending":
				$source->where($this->dynamicFormsModel->getTableName() . ".status = ?","pending");
				break;
			case "trash":
				$source->where($this->dynamicFormsModel->getTableName() . ".status = ?","trash");
				break;

			case null:
			default:
			$source->where($this->dynamicFormsModel->getTableName() . ".status != ?","trash");
				break;
		}*/

		$primaryKey = $this->dynamicFormsModel->getColumnId();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		//active
		/*$active_column = $grid->addColumnStatus('active', 'A.');
		$active_column->getElementPrototype("th")->setTitle($this->translator->translate("Active"));
		$active_column->addOption(0, 'Unactive') // show if status == 0
		->setClass('btn-danger')
			->setIcon('ban')
			->setTitle('Set as active');
		$active_column->addOption(1, 'Active') // show if status == 1
		->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Set as unactive');
		$active_column->onChange[] = function($id, $value) {
			$this->handleActivate($id, $value);
		};*/

		/*$activateButton
			->setCallbackArguments(array($activateButton))
			->setCallback(function ($row, $selfButton) {
				/* @var $selfButton \Mesour\DataGrid\Components\StatusButton * /
				if (!$this->user->isAllowed(new \App\Security\Resource("DynamicForms", $row["createdBy"]), "edit")) {
					$selfButton->setDisabled();
				} else {
					$selfButton->setDisabled(false);
				}
				if ($selfButton->getStatus() == $row["active"]) {
					return $selfButton;
				}
			});
		$deactivateButton = $active_column->addButton()
			->setStatus('1') // show if status == 1
			->setType('btn-success')
			//->setClassName('ajax')
			->setIcon('fa fa-check-circle')
			->setTitle('Set as unactive (active)')
			->setAttribute('href', new Link('activate!', array(
				$primaryKey => '{' . $primaryKey . '}',
				'status' => 0
			)));
		$deactivateButton
			->setCallbackArguments(array($deactivateButton))
			->setCallback(function ($row, $selfButton) {
				/* @var $selfButton \Mesour\DataGrid\Components\StatusButton * /
				if (!$this->user->isAllowed(new \App\Security\Resource("DynamicForms", $row["createdBy"]), "edit")) {
					$selfButton->setDisabled();
				} else {
					$selfButton->setDisabled(false);
				}
				if ($selfButton->getStatus() == $row["active"]) {
					return $selfButton;
				}
			});*/

		$grid->addColumnText("templateName", "Name");

		//Actions
		$grid->addAction('edit', 'Edit', 'edit!', array('dynamicFormId' => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-primary btn-sm ajax '.(!$this->user->isAllowed(new \App\Security\Resource("DynamicForms", $item["createdBy"]), "edit") ? ' disabled' : '');
			})
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('items', 'Items', 'detail', array('id' => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-primary btn-sm'.(!$this->user->isAllowed(new \App\Security\Resource("DynamicForms", $item["createdBy"]), "edit") ? ' disabled' : '');
			})
			->setIcon(ICON_ITEMS)
			->setTitle('Items');

		$grid->addAction('delete', 'Delete', 'delete!', array('dynamicFormId' => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-danger btn-sm '.(!$this->user->isAllowed(new \App\Security\Resource("DynamicForms", $item["createdBy"]), "edit") ? ' disabled' : '');
			})
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes(array(
				'data-bs-toggle' => 'modal',
				"data-bs-target" => "#confirm-modal",
				"data-confirm-text" => $this->translator->translate('Delete?'),
			));

		return $grid;
	}


	/**
	 * DynamicFormItems grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Ublaboo\DataGrid\Exception\DataGridColumnStatusException
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 */
	protected function createComponentDynamicFormItemsGrid(string $name): DataGrid
	{
		$source = $this->dynamicFormsModel->getItems($this->id);
		if($source == null)
		{
			$source = array();
		}

		$primaryKey = "name";

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$grid->addColumnText("name", "Input name");
		//$grid->addColumnText("label", "Label");
		$grid->addColumnText("type", "Type");

		//Actions
		$grid->addAction('edit', 'Edit', 'editItem!', array($primaryKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('delete', 'Delete', 'removeItem!', array($primaryKey => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-danger btn-sm float-right';
			})
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes(array(
				'data-bs-toggle' => 'modal',
				"data-bs-target" => "#confirm-modal",
				"data-confirm-text" => $this->translator->translate('Delete?'),
			));

		return $grid;
	}


	/**
	 * DynamicForm form factory.
	 * @throws Nette\Application\UI\InvalidLinkException
	 */
	protected function createComponentDynamicFormForm(): Nette\Application\UI\Form
	{
		$this->dynamicFormFormFactory->asModal();
		$form = $this->dynamicFormFormFactory->create();
		$form->setTranslator($this->translator);

		$form->onSuccess[] = function ($form) {
			$this->redirect('this');
		};

		return $form;
	}


	/**
	 * DynamicFormItem form factory.
	 */
	protected function createComponentDynamicFormItemForm(): Nette\Application\UI\Form
	{
		if($this->getParameter("name")){
			$this->dynamicFormItemFormFactory->setEditId($this->getParameter("name"));
		}

		$this->dynamicFormItemFormFactory->setDynamicFormId($this->id);
		$this->dynamicFormItemFormFactory->asModal();
		$form = $this->dynamicFormItemFormFactory->create();
		$form->setTranslator($this->translator);

		//$form->getElementPrototype()->addClass("ajax");

		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->redrawControl("dynamicFormItemsGrid");
			$this->redirect('this');
		};

		return $form;
	}

	/**
	 * Delete handler
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('DynamicForms')]
	#[Privilege('delete')]
	public function handleDelete(int $dynamicFormId): void
	{
		$this->dynamicFormsModel->delete($dynamicFormId);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->redirect('this');
	}


	/**
	 * Edit DynamicForm handler
	 */
	#[Secured]
	#[Resource('DynamicForms')]
	#[Privilege('edit')]
	public function handleEdit(int $dynamicFormId): void
	{
		$this->dynamicFormFormFactory->setEditId($dynamicFormId);
		$this->dynamicFormFormFactory->setDefaultValues($this["dynamicFormForm"], $dynamicFormId);

		$this->redrawControl("dynamicFormForm");
	}


	/**
	 * Add DynamicFormItem handler
	 */
	#[Secured]
	#[Resource('DynamicForms')]
	#[Privilege('add')]
	public function handleAddDynamicFormItem($DynamicFormItem_id): void
	{
		$this->categoriesModel->insertRelationDynamicForm($DynamicFormItem_id, $this->id);

		$this->redrawControl("dynamicFormItemsGrid");
	}


	/**
	 * Edit DynamicFormItem handler
	 */
	#[Secured]
	#[Resource('DynamicForms')]
	#[Privilege('edit')]
	public function handleEditItem(string $name): void
	{
		$this->dynamicFormItemFormFactory->setEditId($name);
		$this->dynamicFormItemFormFactory->setDefaultValues($this["dynamicFormItemForm"], $name);

		$this->redrawControl("dynamicFormItemForm");
	}


	/**
	 * Delete DynamicFormItem handler
	 */
	#[Secured]
	#[Resource('DynamicForms')]
	#[Privilege('delete')]
	public function handleRemoveItem($name): void
	{
		$this->dynamicFormsModel->removeItem($this->id, $name);
		$this->dynamicFormsModel->removeItemTranslation($this->id, $name);

		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		if($this->isAjax()){
			$this->redrawControl("dynamicFormItemsGrid");
		} else {
			$this->redirect('this');
		}
	}

}
