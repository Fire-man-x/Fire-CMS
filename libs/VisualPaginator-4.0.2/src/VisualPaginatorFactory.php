<?php declare(strict_types = 1);

namespace AlesWita\VisualPaginator;

use Nette\Http\Session;
use Nette\Localization\Translator;

final readonly class VisualPaginatorFactory
{

	public function __construct(
		private Session $session,
		private ?Translator $translator = null,
	)
	{
	}

	public function create(): VisualPaginator
	{
		$paginator = new VisualPaginator($this->session);

		$translator = $this->translator;
		if ($translator !== null) {
			// Translator::translate() vrací string|Stringable
			$paginator->messages = array_map(
				static fn (string $message): string => (string) $translator->translate($message),
				$paginator->messages,
			);
		}

		return $paginator;
	}

}
