<?php
declare(strict_types=1);

namespace Zet\AntiSpam;

use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use Nette\Http\Request;
use Nette\Http\Session;
use Nette\Utils\Html;

/**
 * Class AntiSpamControl
 *
 * @author  Zechy <email@zechy.cz>
 * @package Zet\AntiSpam
 */
class AntiSpamControl extends BaseControl {
	
	# --------------------------------------------------------------------
	# Registration
	# --------------------------------------------------------------------
	public static function register(array $configuration, Session $session, Request $request) {
		$class = __CLASS__;
		
		Container::extensionMethod("addAntiSpam", function(
			Container $container, $name, $lockTime = null, $resendTime = null
		) use ($class, $configuration, $session, $request) {
			/** @var AntiSpamControl $control */
			$control = new $class($configuration, $session, $request, $name);
			if($lockTime !== null) $control->setLockTime($lockTime);
			if($resendTime !== null) $control->setResendTime($resendTime);
			
			$container->addComponent($control, $name, key($container->getComponents()));
			
			return $control;
		});
	}
	
	# --------------------------------------------------------------------
	# Control definition
	# --------------------------------------------------------------------
	private array $configuration = [
		"lockTime" => null,
		"resendTime" => null,
		"numbers" => [],
		"question" => null,
		"translate" => false
	];
	
	private HiddenFields $hiddenFields;
	
	private QuestionGenerator $question;
	
	private Validator $validator;
	
	/**
	 * AntiSpamControl constructor.
	 *
	 * @param string  $name
	 */
	public function __construct(array $configuration, Session $session, Request $request, $name) {
		parent::__construct($name);
		
		$this->configuration = $configuration;
		$this->validator = new Validator($request);
		$this->monitor(Presenter::class);
	}
	
	protected function attached(IComponent $parent): void {
		parent::attached($parent);
		
		if($parent instanceof Presenter) {
			$this->validator->setSession($parent->getSession());
		}
		
		$this->hiddenFields = new HiddenFields();
		$translator = $this->configuration["translate"] ? $this->getTranslator() : null;
		$this->question = new QuestionGenerator(
			$this->configuration["numbers"], $this->configuration["question"], $translator
		);
		
		/*$self = $this;
		$form->onAnchor[] = function() use ($form, $self) {
			if(!$form->isSubmitted()) {
				$self->validator->setQuestionResult($self->question->getResult());
				$self->validator->setLockTime($self->configuration["lockTime"]);
			}
		};*/
	}
	
	/**
	 * @return AntiSpamControl
	 */
	public function setLockTime(int $lockTime) {
		$this->configuration["lockTime"] = $lockTime;
		
		return $this;
	}
	
	/**
	 * @return AntiSpamControl
	 */
	public function setResendTime(int $resendTime) {
		$this->configuration["resendTime"] = $resendTime;
		
		return $this;
	}
	
	/**
	 * @return AntiSpamControl
	 */
	public function setNumbers(array $numbers) {
		$this->configuration["numbers"] = $numbers;
		
		return $this;
	}
	
	/**
	 * @return AntiSpamControl
	 */
	public function setQuestion(string $question) {
		$this->configuration["question"] = $question;
		
		return $this;
	}
	
	/**
	 * @return HiddenFields
	 */
	public function getHiddenFields() {
		return $this->hiddenFields;
	}
	
	/**
	 * @return QuestionGenerator
	 */
	public function getQuestionGenerator() {
		return $this->question;
	}
	
	public function getControl(): Html {
		$element = parent::getControl();
		
		$this->validator->setHtmlName($this->getHtmlName());
		$this->validator->setHtmlId($this->getForm()->getElementPrototype()->getAttribute("id"));
		
		$this->hiddenFields->setHtmlName($this->getHtmlName());
		$this->hiddenFields->setHtmlId($this->getForm()->getElementPrototype()->getAttribute("id"));
		
		$this->question->setHtmlName($this->getHtmlName());
		$this->question->setHtmlId($this->getForm()->getElementPrototype()->getAttribute("id"));
		
		$element->setName("div");
		$element->addHtml($this->hiddenFields->getControls());
		$element->addHtml($this->question->getQuestion());
		
		$this->validator->setQuestionResult($this->question->getResult());
		$this->validator->setLockTime($this->configuration["lockTime"]);
		
		return $element;
	}
	
	/**
	 * @param null $caption
	 */
	public function getLabel($caption = null): \Nette\Utils\Html|string {
		return "";
	}
	
	public function getValue(): mixed {
		$this->hiddenFields->setHtmlName($this->getHtmlName());
		$this->question->setHtmlName($this->getHtmlName());
		$this->validator->setHtmlName($this->getHtmlName());
		$this->validator->setHtmlId($this->getForm()->getElementPrototype()->getAttribute("id"));
		
		$this->validator->setHtmlName($this->getHtmlName());
		
		$this->validator->setFormMethod($this->form->getMethod());
		$this->validator->setHiddenInputs($this->hiddenFields->getInputs());
		$this->validator->setQuestionInput($this->question->getQuestionName());
		
		$validation = $this->validator->validateForm();
		if($validation) {
			$this->validator->setQuestionResult($this->question->getResult());
			$this->validator->setResendTime($this->configuration["resendTime"]);
		}
		
		return $validation;
	}
	
	public function getError(): ?string {
		return $this->validator->getError();
	}
}