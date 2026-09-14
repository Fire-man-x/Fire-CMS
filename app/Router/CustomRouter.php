<?php
declare(strict_types=1);

namespace App\Router;

use App\Service\LanguageService;
use App\Modules\UrlModule\UrlManager;
use Nette;
use Nette\Application\Request;
use Nette\Http;
use Nette\Routing\Router;
use Nette\SmartObject;


class CustomRouter implements Router
{
	use SmartObject;

	private UrlManager $urlManager;

	private LanguageService $languages;

	/** (urls.type => presenter name) */
	private array $presenters = array(
		'default' => 'Front:Default',
		'user' => 'Users',
		'article' => 'Front:Articles',
		'category' => 'Front:Categories',
	);

	public function __construct(UrlManager $urlManager, LanguageService $languages)
	{
		$this->urlManager = $urlManager;
		$this->languages = $languages;
	}

	/**
	 * Maps HTTP request to a Request object.
	 *
	 */
	public function match(Nette\Http\IRequest $httpRequest): ?array
	{
		$url = $httpRequest->getUrl()->getPathInfo();

		//locale
		$existLocale = Nette\Utils\Strings::match($url, "~^([a-z]{2})/([a-z0-9-]*)~");
		$locale = null;
		if($existLocale)
		{
			$locale = $existLocale[1];
			$url = $existLocale[2];
			//language not exits
			if(!$this->languages->existLanguage($locale))
			{
				return null;
			}
		}

		//params
		$params = $httpRequest->getQuery();

		//try find
		try
		{
			$row = $this->urlManager->getUrlInfoByUrl($url, $locale);
		} catch(\InvalidArgumentException $e) {
			//try find redirection
			try
			{
				$redirectionInfo = $this->urlManager->getRedirectionInfoByUrl($url, $locale);
				$redirectionInfo->update(array("last_usage_date" => new Nette\Database\SqlLiteral("NOW()")));
				$row = $this->urlManager->getUrlInfoByUrl($redirectionInfo->new_url, $locale);
			} catch(\InvalidArgumentException $e) {
				//try find redirection by relativeUrl
				try
				{
					$redirectionInfo = $this->urlManager->getRedirectionInfoByUrl($httpRequest->getUrl()->getRelativeUrl(), $locale);
					$redirectionInfo->update(array("last_usage_date" => new Nette\Database\SqlLiteral("NOW()")));
					$row = $this->urlManager->getUrlInfoByUrl($redirectionInfo->new_url, $locale);
					$params = array();
				} catch(\InvalidArgumentException $e) {
					return null;
				}
			}
		}
		//not exist
		if (!$row || !isset($this->presenters[$row->type])){
			return null;
		}

		if($row->type == 'article' || $row->type == 'category') {
			$params['action'] = 'detail';
		}
		else {
			$params['action'] = 'default';
		}
		$presenter = $this->presenters[$row->type];

		$params['locale'] = $row->language_id;
		$params['id'] = $row->key;

		return $params;

		/*return new Request(
			$presenter,
			$httpRequest->getMethod(),
			$params,
			$httpRequest->getPost(),
			$httpRequest->getFiles(),
		);*/
	}

	/**
	 * Constructs absolute URL from Request object.
	 *
	 */
	public function constructUrl(array $params, Nette\Http\UrlScript $refUrl): ?string
	{
		if (!in_array($params['presenter'], $this->presenters)) {
			return null;
		}

		/* je podchyceno posledni routou, napr. i kvuli Sign:out
		if($params['presenter'] == 'Front:Default')
		{
			$url = new Http\Url($refUrl->getBaseUrl());
			$url->setQuery($params);
			return $url->getAbsoluteUrl();
		}*/

		//locale
		$locale = isset($params["locale"]) && $this->languages->getDefaultLanguage() != $params["locale"] ? $params["locale"]."/" : "";

		//Homepage
		if($params['presenter'] == "Front:Categories" && isset($params["id"]) && $params["id"] == 1)
		{

			unset($params['action'], $params['id'], $params['locale']);

			$url = new Http\Url($refUrl->getBaseUrl());
			$url->setPath($locale);
			$url->setQuery($params);
			return $url->getAbsoluteUrl();
		}

		if(!isset($params['id'])) {
			return null;
		}


		//normal
		$type = array_search($params['presenter'], $this->presenters);
		$urlFromDb = $this->urlManager->getUrlByTypeAndKey($type, $params['id'], $params['locale']);

		unset($params['action'], $params['id'], $params['locale']); // we don't want to have 'action' and 'id' in query parameters

		$url = new Http\Url($refUrl->getBaseUrl());
		$url->setPath($locale.$urlFromDb);
		$url->setQuery($params);
		return $url->getAbsoluteUrl();

		/* Original
		$url = isset($params['url']) ? $params['url'] : null;
		$action = isset($params['action']) ? $params['action'] : null;
		if ($action !== 'default' || !is_string($url)) {
			return null;
		}
		unset($params['action'], $params['url']); // we don't want to have 'action' and 'url' in query parameters

		$url = new Http\Url($refUrl->getBaseUrl() . $url);
		$url->setQuery($params);
		return $url->getAbsoluteUrl();
		*/
	}
}
