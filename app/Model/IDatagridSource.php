<?php
declare(strict_types=1);

namespace App\Model;

/**
 * Data source for datagrid
 */
interface IDatagridSource
{


	/**
	 * Get items as source for datagrid
	 */
	public function getDatagridSource(): \Nette\Database\Table\Selection;

}
