# Visual Paginator
Visual Paginator for Nette Framework.

[![Build Status](https://github.com/aleswita/VisualPaginator/workflows/build/badge.svg)](https://github.com/aleswita/VisualPaginator/actions?query=workflow%3Abuild)
[![Coverage Status](https://coveralls.io/repos/github/aleswita/VisualPaginator/badge.svg?branch=master)](https://coveralls.io/github/aleswita/VisualPaginator?branch=master)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

## Requirements
PHP 8.3+, Nette 3 (`nette/application` 3.2+, `nette/utils` 4, `nette/http` 3.3+) and Latte 3.

## Installation
The best way to install AlesWita/VisualPaginator is using [Composer](http://getcomposer.org/):
```sh
$ composer require aleswita/visualpaginator
```

## Usage
#### Neon
```yaml
services:
	- AlesWita\VisualPaginator\VisualPaginatorFactory

	# or with a template for all paginators (TEMPLATE_NORMAL, TEMPLATE_BOOTSTRAP4, TEMPLATE_BOOTSTRAP5)
	- AlesWita\VisualPaginator\VisualPaginatorFactory(templateFile: AlesWita\VisualPaginator\VisualPaginator::TEMPLATE_BOOTSTRAP5)
```

#### Presenter
```php
<?php declare(strict_types = 1);

use AlesWita\VisualPaginator\VisualPaginator;
use AlesWita\VisualPaginator\VisualPaginatorFactory;
use Nette\Application\UI\Presenter;

final class HomePresenter extends Presenter
{

	public function __construct(
		private readonly VisualPaginatorFactory $visualPaginatorFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(): void
	{
		$this['paginator']->setItemCount(1000);
		$offset = $this['paginator']->getOffset();
		$itemsPerPage = $this['paginator']->getItemsPerPage();

		['SELECT * FROM `orders` LIMIT ? OFFSET ?', $itemsPerPage, $offset];
	}

	protected function createComponentPaginator(): VisualPaginator
	{
		$paginator = $this->visualPaginatorFactory->create();

		$paginator->ajax = true;
		$paginator->canSetItemsPerPage = true;
		$paginator->templateFile = __DIR__ . '/my_awesome_template.latte';

		return $paginator;
	}

}
```

#### Items per page
- `setDefaultItemsPerPage(int)` - fixed number of items per page, no selector and nothing stored in session.
- `$canSetItemsPerPage = true` - shows the "items per page" form, the visitor's choice is stored in session and takes
  precedence over the default. `setItemsPerPage(int)` sets that choice from code (value must be in `$itemsPerPageList`).

```php
$paginator = $this->visualPaginatorFactory->create()
	->setDefaultItemsPerPage(20);
```

#### Template
```html
{control paginator}
```

#### Custom paginator template
```html
{templateType AlesWita\VisualPaginator\VisualPaginatorTemplate}
...
```
