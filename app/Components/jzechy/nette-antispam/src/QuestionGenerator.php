<?php
declare(strict_types=1);

namespace Zet\AntiSpam;

use Nette\Localization\Translator;
use Nette\Utils\Html;

/**
 * Class QuestionGenerator
 *
 * @author  Zechy <email@zechy.cz>
 * @package Zet\AntiSpam
 */
class QuestionGenerator {
	
	private string $htmlName;
	
	private array $numbers;
	
	private string $question;
	
	private string $htmlId;
	
	private array $operation = [
		"+", "-"
	];
	
	private Html $labelPrototype;
	
	private Html $inputPrototype;
	
	private int $result;
	
	private Translator $translator;
	
	/**
	 * QuestionGenerator constructor.
	 *
	 * @param string      $question
	 */
	public function __construct(array $numbers, $question, Translator $translator = null) {
		$this->numbers = $numbers;
		$this->question = $question;
		
		$this->labelPrototype = Html::el("label style='display:block'");
		
		$this->inputPrototype = Html::el("input type='text' required");
		$this->translator = $translator;
		
		$first = $this->getRandomNumber();
		$operation = $this->operation[ rand(0, 1) ];
		$second = $this->getRandomNumber();
		$this->result = $this->evalOperation($first, $operation, $second);
		$this->labelPrototype->setText($this->createQuestion($first, $operation, $second));
	}
	
	public function getContainerId(): string {
		return sprintf("%s-%s", $this->htmlId, "question");
	}
	
	public function getQuestionName(): string {
		return sprintf("%s-%s", $this->htmlName, "question-input");
	}
	
	public function getQuestionId(): string {
		return sprintf("%s-%s", $this->htmlId, "question-input");
	}
	
	public function getResult(): int {
		return $this->result;
	}
	
	public function getQuestion(): Html {
		$containerId = $this->getContainerId();
		$container = Html::el("div");
		$container->setAttribute("id", $containerId);
		
		$questionId = $this->getQuestionId();
		
		$this->labelPrototype->setAttribute("for", $questionId);
		$this->inputPrototype->setAttribute("id", $questionId);
		$this->inputPrototype->setAttribute("name", $this->getQuestionName());
		
		$container->addHtml($this->labelPrototype);
		$container->addHtml($this->inputPrototype);
		
		$script = Html::el("script");
		$script->setHtml(
			"document.getElementById('$containerId').style.display = 'none';\n" .
			"document.getElementById('$questionId').value = " . $this->result . ";"
		);
		$container->addHtml($script);
		
		return $container;
	}
	
	private function getRandomNumber(): int {
		return rand(0, 9);
	}
	
	private function evalOperation(int $first, string $operation, int $second): int {
		switch($operation) {
			case "+":
				return $first + $second;
			case "-":
				return $second > $first ? $second - $first : $first - $second;
		}
		
		return 0;
	}
	
	private function createQuestion(int $first, string $operation, int $second): string {
		if($operation == "-" && $second > $first) {
			$tmp = $first;
			$first = $second;
			$second = $tmp;
		}
		
		$first = $this->stringify($first);
		$second = $this->stringify($second);
		
		$question = $this->translator === null ? $this->question : $this->translator->translate($this->question);
		
		return sprintf("%s %s %s %s?", $question, $first, $operation, $second);
	}
	
	private function stringify(int $number): int|string {
		if(rand(0, 1)) {
			$number = $this->numbers[$number];
			return $this->translator === null ? $number : $this->translator->translate($number);
		} else {
			return $number;
		}
	}
	
	public function getLabelPrototype(): Html {
		return $this->labelPrototype;
	}
	
	public function setLabelPrototype(Html $labelPrototype) {
		$this->labelPrototype = $labelPrototype;
	}
	
	public function getInputPrototype(): Html {
		return $this->inputPrototype;
	}
	
	public function setInputPrototype(Html $inputPrototype) {
		$this->inputPrototype = $inputPrototype;
	}
	
	public function setHtmlName(string $htmlName) {
		$this->htmlName = $htmlName;
	}
	
	public function setHtmlId(string $htmlId) {
		$this->htmlId = $htmlId;
	}
}