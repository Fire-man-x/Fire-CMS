<?php
declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

/**
 * `/cron` -> App\Presenters\CronPresenter. Priorita v config.neon (router.priorities) musí být vyšší než
 * FrontRouter, jinak by `cron` zkoušel CustomRouter jako hezkou URL obsahu.
 */
class CronRouter implements RouterProvider
{

	public function create(): RouteList
	{
		$router = new RouteList;
		$router->addRoute('cron', 'Cron:default');

		return $router;
	}

}
