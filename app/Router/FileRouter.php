<?php
declare(strict_types=1);

namespace App\Router;

use App\FileStorage\DirectThumbnailRoutes;
use App\Service\LanguageService;
use Nette\Application\Routers\RouteList;


class FileRouter implements RouterProvider
{

	public function __construct(
		private LanguageService $languages,
		private DirectThumbnailRoutes $directThumbnailRoutes,
	)
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
		// generátor náhledů FlysystemStorage - vytvoří náhled obrázku při prvním zobrazení (viz FilesPresenter::actionThumbnail()).
		// Klíč náhledu je na konci, aby URL nekončila příponou obrázku - takovou by .htaccess nepustil do index.php.
		$router->addRoute('files/thumbnail/<storage [a-zA-Z0-9_-]+>/<path .+>/<thumbnail [^/]+>', array(
			'module' => 'Front',
			'presenter' => 'Files',
			'action' => 'thumbnail',
			'locale' => $this->languages->getDefaultLanguage()
		));

		// úložiště s directThumbnails: existující náhled pošle web server, požadavek na chybějící soubor pod jejich
		// publicUrl dojde sem a FilesPresenter::actionMissingThumbnail() náhled vytvoří (FlysystemStorage::thumbnailFromPath())
		foreach ($this->directThumbnailRoutes->getPrefixes() as $name => $prefix) {
			$router->addRoute($prefix . '/<path .+>', [
				'module' => 'Front',
				'presenter' => 'Files',
				'action' => 'missingThumbnail',
				'storage' => $name,
				'locale' => $this->languages->getDefaultLanguage(),
			]);
		}

		return $router;
	}

}
