<?php
declare(strict_types=1);

namespace App\Security;

class Resource implements \Nette\Security\Resource
{

	public $id = null;
	public $name = null;


	function __construct($name, $createdById = null)
	{
		$this->name = $name;
		$this->id = $createdById;
	}


	public function getResourceId(): string
	{
		return $this->name;
	}

}
