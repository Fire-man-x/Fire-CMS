<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Languages;
use Nette\SmartObject;

/**
 * Class Languages
 */
class LanguageService
{
	use SmartObject;

	/**
	 * Languages model
	 */
	private Languages $model;

	/**
	 * Default language
	 */
	private string $defaultLanguage;

	/**
	 * List of languages
	 */
	private array $languages;

	/**
	 * List of active languages
	 */
	private array $activelanguages;


	/**
	 * Languages
	 */
	public function __construct(Languages $model)
	{
		$this->model = $model;
	}


	/**
	 * Languages
	 */
	public function getLanguages(): array
	{
		if (!isset($this->languages)) {
			$this->languages = $this->model->getAll()
				->select($this->model->getColumnId())
				->select("shortcut")
				->order("default DESC")
				->order($this->model->getColumnId())
				->fetchPairs($this->model->getColumnId(), "shortcut");
		}

		return $this->languages;
	}


	/**
	 * Language by id
	 * @throws \InvalidArgumentException
	 */
	public function getLanguage(string $language): string
	{
		if (!$this->existLanguage($language)){
			throw new \InvalidArgumentException("Language with id '$language' doesn't exist.");
		}

		$languages = $this->getLanguages();

		return $languages[$language];
	}


	/**
	 * Default language
	 */
	public function getDefaultLanguage(): string
	{
		if (!isset($this->defaultLanguage)) {
			$this->defaultLanguage = $this->model->getAll()
				->select($this->model->getColumnId())
				->where("active", true)
				->where("default", true)
				->fetchField();
		}

		return $this->defaultLanguage;
	}


	/**
	 * Active languages
	 */
	public function getActiveLanguages(): array
	{
		if (!isset($this->activelanguages)) {
			$this->activelanguages = $this->model->getAll()
				->where("active", true)
				->order("default DESC")
				->order($this->model->getColumnId())
				->fetchAssoc($this->model->getColumnId());
		}

		return $this->activelanguages;
	}


	/**
	 * Test if language exist
	 */
	public function existLanguage($language): bool
	{
		return array_key_exists($language, $this->getLanguages());
	}

}
