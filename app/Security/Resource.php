<?php
declare(strict_types=1);

namespace App\Security;

class Resource implements \Nette\Security\Resource
{
	function __construct(private string $name, public ?int $createdById = null)
	{
	}


	public function getResourceId(): string
	{
		return $this->name;
	}

}
