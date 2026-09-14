<?php
declare(strict_types = 1);

namespace App\FrontModule\Components\DatagridExtended;

use Nette\ComponentModel\IContainer;
use Nette\Database\Explorer;
use Nextras;
use Nextras\Datagrid\Column;


/**
 * Extensions of Nextras\Datagrid
 *
 * @author     Vaclav Koterec
 */
abstract class DatagridExtended extends Nextras\Datagrid\Datagrid
{
	/**
	 * File of base template
	 */
	protected ?string $templateFile = null;

	protected int $itemsPerPage = 50;

	/**
	 * Filter container for validating filters
	 */
	protected \Nette\Forms\Container $filtersContainer;

	public function __construct(protected Explorer $db)
	{
		$this->setTranslator(new DatagridExtendedTranslator());
	}


	public function render(): void
	{

		if($this->filterFormFactory) {
			$this['form']['filter']->setDefaults($this->filter
			);
		}

		$this->template->form = $this['form'];
		$this->template->data = $this->getData();
		$this->template->columns = $this->columns;
		$this->template->editRowKey = $this->editRowKey;
		$this->template->rowPrimaryKey = $this->rowPrimaryKey;
		$this->template->paginator = $this->paginator;
		$this->template->sendOnlyRowParentSnippet = $this->sendOnlyRowParentSnippet;
		if(!isset($this->template->caption)) {
			$this->template->caption = NULL;
		}

		$this->template->cellsTemplates = $this->getCellsTemplates();
		$this->template->showFilterCancel = $this->filterDataSource != $this->filterDefaults; // @ intentionaly
		if($this->templateFile) {
			$this->template->setFile($this->templateFile
			);
		} else {
			$this->template->setFile(__DIR__ . '/Datagrid.latte'
			);
		}

		$this->onRender($this
		);
		$this->template->render();
	}


	/**
	 * Create grid
	 */
	abstract public function buildGrid();


	public function setDatabase(\Nette\Database\Context $db): void
	{

		$this->db = $db;
	}


	public function setTemplateFile(string $templateFile): void
	{

		if(!file_exists($templateFile
		)) {
			throw new \RuntimeException("Template file '{$templateFile}' does not exist."
			);
		}
		$this->templateFile = $templateFile;
	}


	public function setFilterFormFactory(callable $filterFormFactory = NULL): void
	{

		parent::setFilterFormFactory($filterFormFactory
		);

		//for validating Filters
		$this->filtersContainer = $filterFormFactory();
	}


	/*******************************************************************************/


	/**
	 * Validate Filters
	 */
	public function validateFilters(array $filters): array
	{

		//no filters
		if(!$filters) {
			return array();
		}

		//input with name doesnt exist
		foreach($filters as $filter => $value){
			if(!isset($this->filtersContainer[$filter])) {
				unset($filters[$filter]);
			}
		}
		return $filters;
	}


	/**
	 * Validate Orders
	 */
	public function validateOrders(?array $order): ?array
	{

		//no sorting
		if($order === NULL) {
			return NULL;
		}

		$column = $this->getColumn($order[0]
		);
		//column not exist
		if($column === NULL) {
			return NULL;
		}
		//column cannot sort
		if(!$column->canSort()) {
			return NULL;
		}

		//not asc, desc => default asc
		if(!in_array(strtolower($order[1]
		), array(self::ORDER_ASC, self::ORDER_DESC)
		)) {
			return NULL;
		}

		return $order;
	}


	/**
	 * Column with <i>name</i> getter
	 */
	public function getColumn(string $name): ?Column
	{

		foreach($this->columns as $column){
			if($column->name === $name) {
				return $column;
			}
		}
		return null;
	}


	/**
	 * Does column with <i>name</i> exist?
	 */
	protected function isColumnExist(string $name): bool
	{

		if($this->getColumn($name
			) !== NULL) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
}

