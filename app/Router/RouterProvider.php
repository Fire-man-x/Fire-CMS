<?php declare(strict_types = 1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

interface RouterProvider
{

	public function create(): RouteList;

}
