<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use AdminForm\AdminContainer;
use AdminForm\TypeContainer;
use Generator;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Container;
use Nette\Forms\Rendering\TwitterBootstrapRenderer;

/**
 * Admin form that renders its groups as collapsible Bootstrap accordion sections instead of one
 * flat list of fields.
 *
 * Adapted from a nicer sectioned-form component found elsewhere (see /AdminForm at the repo root)
 * built for a different Nette app - one using Nextras ORM entities (Record/Collection, per-property
 * `isLocalised` metadata), a custom Form/FormRenderer/Translator/ImageStorage stack, and a
 * "one entity, per-language sub-form" localisation model. None of that exists in this app: models
 * here are plain Nette\Database\Table\ActiveRow (App\Model\BaseModel, no ORM/entity metadata), and
 * translatable content (Articles/Categories/Tags) is a separate DB row per language, not per-property
 * fields on one entity. So only the actual idea worth keeping was ported - naming and rendering
 * groups of fields as accordion sections - built on Nette's own existing addGroup()/ControlGroup
 * mechanism (which already keeps grouped controls flat on the form/in $values, just tagged with a
 * group) rather than a new Container-per-section abstraction, so a plain flat formSucceeded()
 * doesn't have to change shape just because a form gets sectioned. The ORM-metadata-driven
 * default-value population and the per-language tab machinery were not ported - they don't map onto
 * anything this app has.
 *
 * @property-read Container $data Data Container
 * @property-read Generator<string,Container> $locales All language containers
 * @property-read Container $buttons Buttons container
 * @property array<callable(static, AdminFormValues): void> $onSuccess
 * @property array<callable(static, AdminFormValues): void> $onValidate
 */
class AdminForm extends Form
{
	public function __construct()
	{
		parent::__construct();
		//$this->setRenderer(new AdminFormRenderer());
		$this->setMappedType(AdminFormValues::class);
		$this->setRenderer(new TwitterBootstrapRenderer());
	}

	/**
	 * Výchozí hodnoty rozdělí do kontejnerů `data` a `locales`:
	 * - `data`: ploché hodnoty (typicky `$row->toArray()` z modelu), případně už připravené `$values['data']`;
	 * - `locales`: `$values['locales']` jako [languageId => hodnoty překladu] (řádek *Descriptions pro jazyk).
	 *   Z plochého řádku se překlady rozdělit nedají, proto jen přes tento klíč;
	 * - klíč, který odpovídá prvku přímo na formuláři (např. skryté `editId` v modalu), zůstane nahoře.
	 *
	 * Hodnoty můžou být pole, ActiveRow, ArrayHash nebo jiný Traversable/objekt.
	 */
	public function setDefaults(object|array $values, bool $erase = false): static
	{
		$values = self::toArray($values);

		$defaults = [
			'data' => self::toArray($values['data'] ?? []),
			'locales' => [],
		];
		foreach (self::toArray($values['locales'] ?? []) as $language => $localeValues) {
			$defaults['locales'][$language] = self::toArray($localeValues);
		}
		if (array_key_exists('buttons', $values)) {
			$defaults['buttons'] = self::toArray($values['buttons']);
		}
		unset($values['data'], $values['locales'], $values['buttons']);

		foreach ($values as $key => $value) {
			if ($this->getComponent((string) $key, false) !== null) {
				$defaults[$key] = $value;
			} else {
				$defaults['data'][$key] = $value;
			}
		}

		return parent::setDefaults($defaults, $erase);
	}


	/**
	 * @return array<int|string, mixed>
	 */
	private static function toArray(mixed $values): array
	{
		return match (true) {
			is_array($values) => $values,
			$values instanceof ActiveRow => $values->toArray(),
			$values instanceof \Traversable => iterator_to_array($values),
			is_object($values) => get_object_vars($values),
			default => [],
		};
	}


	protected function getData(): Container
	{
		$container = $this->getComponent('data', false);
		if (!$container instanceof Container) {
			$container = $this->addContainer('data');
		}

		return $container;
	}


	protected function getLocales(): Container
	{
		$container = $this->getComponent('locales', false);
		if (!$container instanceof Container) {
			$container = $this->addContainer('locales');
		}

		return $container;
	}

	protected function getButtons(): Container
	{
		$container = $this->getComponent('buttons', false);
		if (!$container instanceof Container) {
			$container = $this->addContainer('buttons');
		}

		return $container;
	}

	/**
	 * @return Generator<int|string,Container>
	 */
	protected function getDataContainers(): Generator
	{
		foreach ($this->getComponents() as $component) {
			if ($component instanceof AdminContainer && $component->type === TypeContainer::Tab) {
				yield $component->name => $component;
			}
		}
	}

	/**
	 * @return Generator<int|string,Container>
	 */
	protected function getTabContainers(): Generator
	{
		foreach ($this->getComponents() as $component) {
			if ($component instanceof AdminContainer && $component->type === TypeContainer::Tab) {
				yield $component->name => $component;
			}
		}
	}

	/**
	 * @return Generator<int|string,Container>
	 */
	protected function getLocalesContainers(): Generator
	{
		foreach ($this->getComponents() as $component) {
			if ($component instanceof AdminContainer && $component->type === TypeContainer::Tab) {
				yield $component->name => $component;
			}
		}
	}

	/**
	 * @return Generator<int|string,Container>
	 */
	protected function getButtonContainers(): Generator
	{
		foreach ($this->getComponents() as $component) {
			if ($component instanceof AdminContainer && $component->type === TypeContainer::Button) {
				yield $component->name => $component;
			}
		}
	}

}
