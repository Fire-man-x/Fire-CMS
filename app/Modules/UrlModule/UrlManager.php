<?php
declare(strict_types=1);

namespace App\Modules\UrlModule;


use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\InvalidArgumentException;
use Nette\SmartObject;
use Nette\Utils\ArrayHash;

/**
 * Class UrlManager
 */
class UrlManager
{
	use SmartObject;


	/**
	 * List of url
	 */
	private array $url;

	/**
	 * List of url
	 */
	private array $urlTypeAndKey;


	/**
	 * UrlManager constructor.
	 */
	public function __construct(
		protected Model $model,
		protected RedirectionsModel $redirectionsModel,
		protected LanguageService $languages,
		protected Explorer $db)
	{
	}


	/**
	 * Url
	 */
	public function getUrl($urlId): array
	{
		if (!isset($this->url)) {
			$this->url = $this->model->getById($urlId)?->toArray();
		}

		return $this->url;
	}


	/**
	 * Url info
	 * @return false|\Nette\Database\Table\ActiveRow
	 * @throws InvalidArgumentException
	 */
	public function getUrlInfoByTypeAndKey(string $type, int $key, ?string $languageId = null)
	{
		if(!isset($languageId))
		{
			$languageId = $this->languages->getDefaultLanguage();
		}

		$urlInfo = $this->model->findAll()
			->where('language_id', $languageId)
			->where('type', $type)
			->where('key', $key)
			->fetch();

		if(!$urlInfo)
		{
			throw new InvalidArgumentException("Url with type '$type' and key '$key' does not exist.");
		}

		return $urlInfo;
	}


	/**
	 * Exist url?
	 * @return false|\Nette\Database\Table\ActiveRow
	 * @throws InvalidArgumentException
	 */
	public function existUrlByTypeAndKey(string $type, int $key, ?string $languageId = null)
	{
		try
		{
			$this->getUrlInfoByTypeAndKey($type, $key, $languageId);
			return true;
		}
		catch(InvalidArgumentException $e)
		{
			return false;
		}
	}


	/**
	 * Url info
	 * @return false|\Nette\Database\Table\ActiveRow
	 */
	public function getUrlInfoByUrl(string $url, ?string $languageId = null)
	{
		if(!isset($languageId))
		{
			$languageId = $this->languages->getDefaultLanguage();
		}

		$urlInfo = $this->model->findAll()
			->where('language_id', $languageId)
			->where('url', $url)
			->fetch();

		if(!$urlInfo)
		{
			throw new InvalidArgumentException("Url with '$url' does not exist.");
		}

		return $urlInfo;
	}


	/**
	 * Url
	 * @return string|false
	 */
	public function getUrlByTypeAndKey(string $type, int $key, ?string $languageId = null)
	{
		$urlInfo = $this->getUrlInfoByTypeAndKey($type,$key,$languageId);
		return $urlInfo["url"];
	}


	/**
	 * Save URL
	 * @param string $languageId
	 */
	public function saveUrl(string $type, int $key, $language, string $url)
	{
		$webalizedUrl = $this->validateUrl($url, $type, $key, $key);

		try
		{
			$urlInfo = $this->getUrlInfoByTypeAndKey($type, $key, $language);
			$oldUrl = $urlInfo['url'];

			//redirection
			if($oldUrl != $webalizedUrl) {
				//update old redirection if exist
				$this->redirectionsModel->findAll()
					->where("language_id", $language)
					->where("old_url", $oldUrl)
					->update(array("new_url" => $webalizedUrl));

				//update all old to new
				$this->redirectionsModel->findAll()
					->where("language_id", $language)
					->where("new_url", $oldUrl)
					->update(array("new_url" => $webalizedUrl));

				//insert
				$this->redirectionsModel->insert(array(
					"language_id" => $language,
					"old_url" => $oldUrl,
					"new_url" => $webalizedUrl
				));

				//remove recursive
				$this->redirectionsModel->findAll()
					->where("language_id", $language)
					->where("old_url", $webalizedUrl)
					->delete();

			}

			//update url
			$this->model->update($urlInfo["url_id"], array("url" => $webalizedUrl));

		}catch(InvalidArgumentException $e) {
			//insert url
			$this->model->insert(ArrayHash::from(array(
				"type" => $type,
				"key" => $key,
				"language_id" => $language,
				"url" => $webalizedUrl
			)));
		}
	}

	/**
	 * Validate URL handler
	 * @param int $category_id
	 */
	public function validateUrl(string $url, $type, $key, $notInKey = null): string
	{
		$exist = true;
		$index = 0;
		while ($exist){
			$webalizedUrl = \Nette\Utils\Strings::webalize($url . ($index == 0 ? "" : " " . $index));
			$sql = $this->model->findAll()
				->where("url", $webalizedUrl);
			if($notInKey){
				$sql->where("key != ?", $notInKey);
			}
			$exist = $sql->fetch();

			$index++;
		}

		return $webalizedUrl;
	}


	/**
	 * Redirection url info
	 * @return false|\Nette\Database\Table\ActiveRow
	 */
	public function getRedirectionInfoByUrl(string $url, ?string $languageId = null)
	{
		if(!isset($languageId))
		{
			$languageId = $this->languages->getDefaultLanguage();
		}

		$urlInfo = $this->redirectionsModel->findAll()
			->where('language_id', $languageId)
			->where('old_url', $url)
			->fetch();

		if(!$urlInfo)
		{
			throw new InvalidArgumentException("Url with '$url' does not exist.");
		}

		return $urlInfo;
	}
}
