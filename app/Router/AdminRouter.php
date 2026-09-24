<?php
declare(strict_types=1);

namespace App\Router;

use App\Service\LanguageService;
use Nette\Application\Routers\RouteList;


class AdminRouter implements RouterProvider
{

	public function __construct(private LanguageService $languages)
	{
	}

	public function create(): RouteList
	{
		$router = new RouteList;

		$router->addRoute('/administrace/<presenter>/<action>[/<id>] ? editLocale=<editLocale>', array(
			'module' => 'Admin',
			'presenter' => 'Default',
			'action' => 'default',
			'editLocale' => $this->languages->getDefaultLanguage()
		));

		return $router;
	}

}
