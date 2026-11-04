<?php
declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

/**
 * `/mcp` -> App\Presenters\McpPresenter (MCP server). Priorita v config.neon (router.priorities) musí být vyšší než
 * FrontRouter, jinak by `mcp` zkoušel CustomRouter jako hezkou URL obsahu.
 *
 * URL si klienti ukládají do nastavení konektoru, musí proto zůstat stabilní.
 */
class McpRouter implements RouterProvider
{

	public function create(): RouteList
	{
		$router = new RouteList;
		$router->addRoute('mcp', 'Mcp:default');

		return $router;
	}

}
