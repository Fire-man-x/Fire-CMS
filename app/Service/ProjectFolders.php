<?php
declare(strict_types=1);

namespace App\Service;

use Nette\SmartObject;

class ProjectFolders
{
	use SmartObject;

	/**
	 * ThemePath constructor.
	 */
	public function __construct(
		protected string $rootDir,
		protected string $appDir,
		protected string $wwwDir,
		protected string $wwwThemeDir
	)
	{
	}

	public function getRootDir(): string
	{
		return $this->rootDir;
	}

	public function getAppDir(): string
	{
		return $this->appDir;
	}

	public function getWwwDir(): string
	{
		return $this->wwwDir;
	}


	public function getThemeDir(): string
	{
		return $this->rootDir."/theme";
	}


	public function getWwwThemeDir(): string
	{
		return $this->wwwThemeDir;
	}

	/**
	 * @deprecated Use getThemeDir() instead
	 */
	public function getThemePath(): string
	{
		return $this->getWwwThemeDir();
	}
}
