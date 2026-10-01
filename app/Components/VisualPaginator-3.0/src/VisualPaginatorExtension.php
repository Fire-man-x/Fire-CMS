<?php

/**
 * This file is part of the AlesWita\VisualPaginator
 * Copyright (c) 2015 Ales Wita (aleswita@gmail.com)
 */

declare(strict_types=1);

namespace AlesWita\Components;

use Nette;
use Nette\Schema\Helpers;


/**
 * @author Aleš Wita
 */
class VisualPaginatorExtension extends Nette\DI\CompilerExtension
{
	public array $defaults = [
		"session" => null,
		"translator" => null,
		"itemsPerPageList" => null,
		"template" => null,
		"texts" => null,/** @deprecated */
		"messages" => [],
	];

	public function loadConfiguration(): void {
		$config = Helpers::merge($this->getConfig(), $this->defaults);
		$container = $this->getContainerBuilder();

		$vp = $container->addDefinition($this->prefix("visualpaginator"))
			->setClass("AlesWita\\Components\\VisualPaginator");

		if ($config["session"] !== null) {
			$vp->addSetup("\$service->setSession(?)", [$config["session"]]);
		}
		if ($config["translator"] !== null) {
			$vp->addSetup("\$service->setTranslator(?)", [$config["translator"]]);
		}
	}

	/**
	 * @param Nette\PhpGenerator\ClassType
	 * @throws Nette\InvalidArgumentException
	 */
	public function afterCompile(Nette\PhpGenerator\ClassType $class): void {
		$initialize = $class->getMethod("initialize");
		$config = $this->validateConfig($this->defaults);

		// items per page list
		if ($config["itemsPerPageList"] !== null) {
			if (is_array($config["itemsPerPageList"])) {
				if ($config["itemsPerPageList"] === array_filter($config["itemsPerPageList"], function ($s): bool {return is_numeric($s);})) {
					$initialize->addBody("AlesWita\\Components\\VisualPaginator::\$itemsPerPageList = ?;", [$config["itemsPerPageList"]]);
				} else {
					throw new Nette\InvalidArgumentException("Keys in \$defaults[\"itemsPerPageList\"] array must be numeric.");
				}
			} else {
				throw new Nette\InvalidArgumentException("\$defaults[\"itemsPerPageList\"] must be array.");
			}
		}

		// template
		if ($config["template"] !== null) {
			if (is_string($config["template"])) {
				$config["template"] = constant($config["template"]);
			}

			if (is_array($config["template"])) {
				if (array_keys($config["template"]) === array_keys(VisualPaginator::TEMPLATE_NORMAL)) {
					if (is_file($config["template"]["main"]) && is_file($config["template"]["paginator"]) && is_file($config["template"]["itemsPerPage"])) {
						$initialize->addBody("AlesWita\\Components\\VisualPaginator::\$paginatorTemplate = ?;", [$config["template"]]);
					} else {
						throw new Nette\InvalidArgumentException("One or more files in \$defaults[\"template\"] does not exist.");
					}
				} else {
					throw new Nette\InvalidArgumentException("Array \$defaults[\"template\"] must have these keys: main, paginator and itemsPerPage.");
				}
			} else {
				throw new Nette\InvalidArgumentException("\$defaults[\"template\"] must be array.");
			}
		}

		// work around for deprecated texts
		if (isset($config["texts"]) && $config["messages"] === []) {/** @deprecated */
			trigger_error("\$defaults[\"texts\"] is deprecated.", E_USER_DEPRECATED);
			foreach ($config["texts"] as $val) {
				$config["messages"][$val[0]] = $val[1];
			}
		}

		// messages
		foreach ((array) $config["messages"] as $name => $value) {
			if (isset(VisualPaginator::$messages[$name])) {
				$initialize->addBody("AlesWita\\Components\\VisualPaginator::\$messages[?] = ?;", [$name, $value]);
			} else {
				throw new Nette\InvalidArgumentException("AlesWita\\Components\\VisualPaginator::\$messages[{$name}] does not exist.");
			}
		}
	}
}
