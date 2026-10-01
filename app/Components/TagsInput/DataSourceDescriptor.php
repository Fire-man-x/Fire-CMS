<?php
declare(strict_types=1);

namespace Achse\TagInput;

use JsonSerializable;
use Nette\SmartObject;

class DataSourceDescriptor implements JsonSerializable
{
	use SmartObject;

	const DEFAULT_LABEL_PROPERTY = 'label';
	const DEFAULT_VALUE_PROPERTY = 'value';

	/**
	 * @var
	 */
	private $url;

	private string $valuePropertyName = self::DEFAULT_VALUE_PROPERTY;

	private string $labelPropertyName = self::DEFAULT_LABEL_PROPERTY;

	private ?int $maxTags = null;


	/**
	 * @param string $url
	 */
	public function __construct($url)
	{
		$this->url = $url;
	}


	/**
	 * @inheritdoc
	 */
	public function jsonSerialize(): mixed
	{
		return (object) [
				'url' => $this->url,
				'valuePropertyName' => $this->valuePropertyName,
				'labelPropertyName' => $this->labelPropertyName,
				'maxTags' => $this->maxTags,
		];
	}


	public function setLabelPropertyName(string $labelPropertyName)
	{
		$this->labelPropertyName = $labelPropertyName;
	}


	public function setValuePropertyName(string $valuePropertyName)
	{
		$this->valuePropertyName = $valuePropertyName;
	}


	public function setMaxTags(?int $maxTags)
	{
		$this->maxTags = $maxTags;
	}

}
