<?php
declare(strict_types=1);

namespace App\Components;

use App\Model\Files;
use BadFunctionCallException;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\ITemplate;
use Nette\InvalidArgumentException;
use Nette\Localization\Translator;
use Nette\SmartObject;
use Nette\Utils\Strings;

/**
 * Class Shortcodes
 */
class Shortcodes
{
	use SmartObject;

	private array $shortcodes = array();

	/**
	 * Constructor
	 */
	public function __construct(
		private FileManager\FileManager $fileManager,
		public Files $filesModel,
		public LinkGenerator $linkGenerator,
		public Translator $translator)
	{
		//register default filters
		$this->registerDefault();
	}


	/**
	 * Register to template
	 * @deprecated Use register in config.neon
	 */
	public function register(ITemplate $template)
	{
		$template->addFilter("shortcodes", array($this, "transform"));
		$this->registerDefault();
	}


	/**
	 * Add
	 */
	public function add(string $tag, callable $function)
	{
		if (!is_callable($function)) {
			throw new BadFunctionCallException("Function in shortcode '$tag' is not callable");
		}
		$this->shortcodes[$tag] = $function;
	}


	/**
	 * Transform
	 */
	public function transform(string $text): string
	{
		//dump($this->shortcodes);
		foreach ($this->shortcodes as $shortcode => $function) {

			/* $findeer = preg_match("~\[" . $shortcode . ",(\s)*(.*?)\]~", $text, $finded);
			 *
			 */
			$pattern = "\[" . $shortcode . "(,\s*)*(.*?)\]";
			//replace with text
			$text = Strings::replace($text, "~" . $pattern . "~", function($params) use ($shortcode, $function) {
					$shortcodeParameters = $params[2];

					//remove all &quot;
					$textNoQuots = Strings::replace($shortcodeParameters, '~&quot;~', "\"");
					//remove all whitespaces, out of quots
					$textNoSpaces = Strings::replace($textNoQuots, '~(&nbsp;|\s)+(?=([^"]*"[^"]*")*[^"]*$)~');

					//split parameters to array
					$itemParams = Strings::split($textNoSpaces, "~,~"/* "~[,\s]+~" */);

					//user callback
					$return = call_user_func($function, $itemParams);
					if (!is_string($return)) {
						throw new InvalidArgumentException(sprintf('Shortcode function "' . $shortcode . '" must return string, %s given.', is_object($return) ? get_class($return) : gettype($return)));
					}
					return $return;
				});
		}
		return $text;
	}


	/**
	 * Register some default shortcodes
	 */
	private function registerDefault()
	{
		$this->add("image", array($this, "shortcodeImage"));
		$this->add("file", array($this, "shortcodeFile"));
	}


	/**
	 * Image shortcode
	 */
	private function shortcodeImage($params)
	{
		$file_id = $params[0];
		$file = $this->filesModel->findById($file_id)->fetch();

		$fileEntity = $this->filesModel->toFileEntity($file);
		$imageRequest = FileManager\Requests\ImageRequest::fromMacro($fileEntity, array($params[1]));

		return '<img src="' . $this->fileManager->link($imageRequest) . '">';
	}


	/**
	 * File shortcode
	 */
	private function shortcodeFile($params)
	{
		$file_id = $params[0];
		$overrideName = null;
		$showSize = false;
		for ($i = 1; $i < count($params); $i ++) {
			if (Strings::startsWith($params[$i], "name=&gt;\"")) {
				$overrideName = Strings::substring($params[$i], 10, -1);
			}

			if (Strings::startsWith($params[$i], "showSize=&gt;\"")) {
				$showSize = Strings::substring($params[$i], 14, -1) == "true";
			}
		}

		$file = $this->filesModel->findById($file_id)->fetch();
		if($file){

			$name = $file["new_name"] ? $file["new_name"] : $file["original_name"];
			$file_hash = $file["disk_name"];

			//link to counter
			return '<a href="' . $this->linkGenerator->link("Front:Files:default", array("hash" => $file_hash, "locale"=>"jn")) . '" alt="' . ($overrideName ? $overrideName : $name) . '">'
				. ($overrideName ? $overrideName : $name) . ($showSize ? " <span class=\"file-info\">(" . \Latte\Runtime\Filters::bytes($file["size"]) . ")</span>" : "")
				. '</a>';
		}else{
			return '<b>'.$this->translator->translate("File doesn't exist.").'</b>';
		}
	}

}
