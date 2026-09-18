<?php
declare(strict_types=1);

namespace App\Router;

use App\Modules\UrlModule\UrlManager;
use App\Service\DomainService;
use App\Service\LanguageService;
use Nette\Application\Routers\RouteList;


class FrontRouter implements RouterProvider
{
	public function __construct(private UrlManager $urlManager, private LanguageService $languages, private DomainService $domains)
	{

	}

	public function create(): RouteList
	{
		$router = new RouteList;

		$router->add(new CustomRouter($this->urlManager, $this->languages, $this->domains));
		/*$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]<url>', array(
			'module' => 'Front',
			'presenter' => array(
				Route::VALUE => 'Default',
				Route::FILTER_IN => function($url) use ($urlManager) {
					Debugger::barDump($url);
					$urlManager->getUrlInfoByUrl($url);
					return 'Article';
				},
				Route::FILTER_OUT => function($url){
					var_dump("xxxxxx");
					die;
					dump("out");
					Debugger::barDump($url);
					return 'defaultxxx';
				},
			),
			'action' => array(
				Route::VALUE => 'default',
				Route::FILTER_IN => function($url) use ($urlManager) {
					Debugger::barDump($url);
					$urlManager->getUrlInfoByUrl($url);
					return 'Article';
				},
				Route::FILTER_OUT => function($url){
					dump("out");
					Debugger::barDump($url);
					return 'defaultxxx';
				},
			)
		));*/
		/*$router->addRoute('[<locale='.$languages->getDefaultLanguage().' [a-z]{2}>/]<presenter>/<action>[/<id>]', array(
			'module' => 'Front',
			'presenter' => array(
				Route::VALUE => 'Default',
				Route::FILTER_IN => function($url) use ($urlManager) {
					Debugger::barDump($url);
					$urlManager->getUrlInfoByUrl($url);
					return 'Article';
				},
				Route::FILTER_OUT => function($url){
					dump($url);die;
					return 'defaultxxx';
				},
			),
			'action' => array(
				Route::VALUE => 'default',
				Route::FILTER_IN => function($url) use ($urlManager) {
					Debugger::barDump($url);
					$urlManager->getUrlInfoByUrl($url);
					return 'Article';
				},
				Route::FILTER_OUT => function($url){
					dump("out");
					Debugger::barDump($url);
					return 'defaultxxx';
				},
			)
		));*/

		$router->addRoute('[<locale='.$this->languages->getDefaultLanguage().' [a-z]{2}>/]<presenter>/<action>[/<id>]', array(
			'module' => 'Front',
			'presenter' => 'Homepage',
			'action' => 'default'
			));

		return $router;
	}

}
