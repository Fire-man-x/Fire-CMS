<?php
declare(strict_types=1);

namespace Alnux\NetteBreadCrumb;

/**
 * Class BreadCrumbControl
 *
 * BreadCrumb Component
 * @author David Zadražil <me@davidzadrazil.cz> edit by Leonardo Allende <alnux@ya.ru>
 *
 */
use Nette\Application\UI\Control;
use \Exception;

class BreadCrumb extends Control
{

	public array $links = array();

	private string $templateFile;


	public function customTemplate(string $template = null)
	{
		$this->templateFile = $template ?: __DIR__ . '/BreadCrumb.latte';
	}


	/**
	 * Render function
	 */
	public function render()
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);

		$this->template->links = $this->links;
		$this->template->render();
	}


	/**
	 * Add link
	 */
	public function addLink(string $title, ?string $link = null, ?string $icon = null)
	{
		$this->links[md5($title)] = array(
			'title' => $title,
			'link'  => $link,
			'icon'  => $icon
		);
	}

	/**
	 * Remove link
	 *
	 * @param $key
	 *
	 * @throws Exception
	 */
	public function removeLink($key)
	{
		$key = md5($key);
		if(array_key_exists($key, $this->links))
		{
			unset($this->links[$key]);
		}
		else
		{
			throw new Exception("Key does not exist.");
		}
	}

	/**
	 * Edit link
	 * @author Leonardo Allende <alnux@ya.ru>
	 * @param $title
	 * @param null $icon
	 */
	public function editLink($title, ?\Nette\Application\UI\Link $link = null, $icon = null): void
	{
		if(array_key_exists(md5($title), $this->links))
		{
			$this->addLink($title, $link, $icon);
		}
	}
}