<?php
declare(strict_types=1);

namespace App\Router;

use App\Service\LanguageService;
use Nette\Application\Routers\RouteList;


class FileRouter implements RouterProvider
{

	public function __construct(private LanguageService $languages)
	{

	}

	public function create(): RouteList
	{
		$router = new RouteList;

		/*$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]search', 'Front:Search:default');
		$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]tag/<url>', 'Front:Tags:default');
		$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]files/download/<hash>', 'Front:Files:default');
		$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]a/<url>', 'Front:Articles:detail');
		$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]', 'Front:Default:default');
		$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]<url>', 'Front:Categories:detail');

		//$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]<presenter>/<action>[/<id>]', 'Front:Default:default');
		*/
		$router->addRoute('files/<imagePath .+>', array(
			'module' => 'Front',
			'presenter' => 'Files',
			'action' => 'generateImageThumbnail',
			'locale' => $this->languages->getDefaultLanguage()
		));

		return $router;
	}

}
