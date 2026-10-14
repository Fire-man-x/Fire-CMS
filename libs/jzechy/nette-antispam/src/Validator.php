<?php
declare(strict_types=1);

namespace Zet\AntiSpam;

use Nette\Http\Request;
use Nette\Http\Session;
use Tracy\Debugger;

/**
 * Class Validator
 *
 * @author  Zechy <email@zechy.cz>
 * @package Zet\AntiSpam
 */
class Validator {
	
	private Session $session;
	
	private Request $request;
	
	private string $method;
	
	private string $htmlName;
	
	private array $hiddenInputs;
	
	private string $questionInput;
	
	private string $htmlId;
	
	private int $error = ErrorType::NO_ERROR;
	
	/**
	 * Validator constructor.
	 *
	 */
	public function __construct(Request $request) {
		$this->request = $request;
	}
	
	public function setFormMethod(string $method) {
		$this->method = $method;
	}
	
	/**
	 * @param null|mixed $defaultValue
	 */
	public function getData(string $key, $defaultValue = null): mixed {
		switch($this->method) {
			case "get":
				return $this->request->getQuery($key, $defaultValue);
			default:
				return $this->request->getPost($key, $defaultValue);
		}
	}
	
	public function getSessionSection(): \Nette\Http\SessionSection|\stdClass {
		return $this->getSession()->getSection(sprintf("antispam-%s", $this->htmlId));
	}
	
	public function setHtmlName(string $htmlName) {
		$this->htmlName = $htmlName;
	}
	
	public function setHiddenInputs(array $inputs) {
		$this->hiddenInputs = $inputs;
	}
	
	public function setQuestionInput(string $questionInput) {
		$this->questionInput = $questionInput;
	}
	
	public function setQuestionResult(int $questionResult) {
		$this->getSessionSection()->result = $questionResult;
	}
	
	public function setLockTime(int $lockTime) {
		$this->getSessionSection()->locked = strtotime("+ $lockTime seconds");
	}
	
	public function setResendTime(int $resendTime) {
		if($resendTime > 0) {
			$this->getSessionSection()->resend = strtotime("+ $resendTime seconds");
		}
	}
	
	public function getError(): int {
		return $this->error;
	}
	
	public function validateForm(): bool {
		if(!$this->validateHiddenFields()) {
			$this->error = ErrorType::HIDDEN_FIELDS;
			
			return false;
		}
		if(!$this->validateQuestion()) {
			$this->error = ErrorType::QUESTION;
			
			return false;
		}
		if(!$this->validateLock()) {
			$this->error = ErrorType::LOCK_TIME;
			
			return false;
		}
		if(!$this->validateResendTime()) {
			$this->error = ErrorType::RESEND_TIME;
			
			return false;
		}
		
		return true;
	}
	
	private function validateHiddenFields(): bool {
		foreach($this->hiddenInputs as $name => $type) {
			$postKey = sprintf("%s-%s", $this->htmlName, $name);
			if($type == "checkbox") {
				$value = $this->getData($postKey, "off");
				
				if($value == "on") {
					return false;
				}
			} else {
				$value = $this->getData($postKey);
				if(!empty($value)) {
					return false;
				}
			}
		}
		
		return true;
	}
	
	private function validateQuestion(): bool {
		$value = $this->getData($this->questionInput);
		
		return $value == $this->getSessionSection()->result;
	}
	
	private function validateLock(): bool {
		return $this->getSessionSection()->locked <= time();
	}
	
	private function validateResendTime(): bool {
		if(isset($this->getSessionSection()->resend)) {
			return $this->getSessionSection()->resend <= time();
		} else {
			return true;
		}
	}
	
	public function barDumpSession() {
		Debugger::barDump("Výsledek: ". $this->getSessionSection()->result);
		Debugger::barDump("Blokován do: ". date("H:i:s", $this->getSessionSection()->locked));
		if($this->getSessionSection()->resend == null) {
			$time = "---";
		} else {
			$time = date("H:i:s", $this->getSessionSection()->resend);
		}
		Debugger::barDump("Znovuodeslání v: ". $time);
	}
	
	public function setSession(Session $session) {
		$this->session = $session;
	}
	
	public function getSession(): Session {
		if (!$this->session) {
			$this->session = new \Nette\Http\Session($this->request, new \Nette\Http\Response);
		}
		return $this->session;
	}
	
	public function setHtmlId(string $htmlId) {
		$this->htmlId = $htmlId;
	}
}
