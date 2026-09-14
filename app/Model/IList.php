<?php
declare(strict_types=1);

namespace App\Model;

/**
 * Data availabile as list
 */
interface IList
{


	/**
	 * Get items represented as list
	 * @return \Nette\Database\Table\Selection
	 */
	public function getList();

}
