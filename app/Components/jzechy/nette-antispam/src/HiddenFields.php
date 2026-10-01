<?php
declare(strict_types=1);

namespace Zet\AntiSpam;

use Nette\Utils\Html;

/**
 * Class HiddenFields
 *
 * @author  Zechy <email@zechy.cz>
 * @package Zet\AntiSpam
 */
class HiddenFields {
	
	/**
	 * [inputType => inputName]
	 */
	private array $inputs = [
		"url" => "text",
		"email" => "text",
		"rules" => "checkbox"
	];
	
	private string $htmlId;
	
	private string $htmlName;
	
	private string $hideClass;
	
	public function getGroupId(): string {
		return sprintf("%s-%s", $this->htmlId, "fields");
	}
	
	public function getControls(): Html {
		$groupId = $this->getGroupId();
		$group = Html::el("div");
		$group->setAttribute("id", $groupId);
		
		foreach($this->inputs as $name => $type) {
			$el = Html::el("input");
			$el->setAttribute("type", $type);
			$el->setAttribute("name", sprintf("%s-%s", $this->htmlName, $name));
			$group->addHtml($el);
		}
		
		if($this->hideClass === null) {
			$script = Html::el("script");
			$script->setHtml("document.getElementById('$groupId').style.display = 'none';");
			$group->addHtml($script);
		} else {
			$group->appendAttribute("class", $this->hideClass);
		}
		
		
		return $group;
	}
	
	public function hideByClass(string $class) {
		$this->hideClass = $class;
	}
	
	public function getInputs(): array {
		return $this->inputs;
	}
	
	public function setHtmlId(string $htmlId) {
		$this->htmlId = $htmlId;
	}
	
	public function setHtmlName(string $htmlName) {
		$this->htmlName = $htmlName;
	}
}