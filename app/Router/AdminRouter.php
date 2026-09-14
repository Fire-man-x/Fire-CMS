<?php
declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;


class AdminRouter implements RouterProvider
{
	public function create(): RouteList
	{
		$router = new RouteList;

		$router->addRoute('/administrace/<presenter>/<action>[/<id>]', array(
			'module' => 'Admin',
			'presenter' => 'Default',
			'action' => 'default'
		));

		return $router;
	}

}
