<?php
declare(strict_types=1);

namespace App\Forms;

use App\Components\Menu\Model\MenuLinkType;
use App\Components\Menu\Model\Menus;
use App\Model\Articles;
use App\Model\Categories;
use App\Model\Pages;
use App\Model\Sections;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

/**
 * Formulář položky menu (modal v :Admin:Menus:detail). Cíl položky se vybírá podle typu odkazu -
 * kategorie/článek ze selectu, URL/route jako text - a ukládá se do jednoho sloupce `target`.
 * Popisek se ukládá pro jazyk, ve kterém se administrace menu právě edituje.
 */
class MenuItemFormFactory extends BaseFormFactory
{
	/** Tvar cíle typu route: ":Front:Presenter:action" nebo "Presenter:action", volitelně "?param=hodnota&..." */
	private const RoutePattern = '#^:?([A-Z][A-Za-z0-9]*:)+[a-z][A-Za-z0-9]*(\?.*)?$#';


	public function __construct(
		private readonly Menus $model,
		private readonly Categories $categoriesModel,
		private readonly Articles $articlesModel,
		private readonly Pages $pagesModel,
		private readonly Sections $sectionsModel,
		private readonly Translator $translator,
		private readonly LanguageService $languages,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null, ?int $menuId = null, ?string $language = null): Form
	{
		if ($menuId === null || $language === null) {
			throw new \InvalidArgumentException('Menu item form needs menu id and language.');
		}

		$form = parent::create($editId);

		$form->addCheckbox('active', 'Active')
			->setDefaultValue(true);

		$linkType = $form->addSelect('linkType', 'Link type', MenuLinkType::options())
			->setRequired(VALIDATE_REQUIRED);

		$categoryId = $form->addSelect('categoryId', 'Category', $this->getCategoryOptions($language))
			->setTranslator(null)
			->setPrompt($this->translate('Select'))
			->setOption('id', 'menuItem-categoryId');

		$articleId = $form->addSelect('articleId', 'Article', $this->getArticleOptions($language))
			->setTranslator(null)
			->setPrompt($this->translate('Select'))
			->setOption('id', 'menuItem-articleId');

		$pageId = $form->addSelect('pageId', 'Page', $this->getPageOptions($language))
			->setTranslator(null)
			->setPrompt($this->translate('Select'))
			->setOption('id', 'menuItem-pageId');

		$sectionId = $form->addSelect('sectionId', 'Section', $this->sectionsModel->getList($language))
			->setTranslator(null)
			->setPrompt($this->translate('Select'))
			->setOption('id', 'menuItem-sectionId');

		$url = $form->addText('url', 'URL')
			->setHtmlAttribute('placeholder', 'https://..., /contact, #section')
			->setOption('id', 'menuItem-url');

		$route = $form->addText('route', 'Route')
			->setHtmlAttribute('placeholder', ':Front:Properties:default?town=Brno')
			->setOption('description', 'Presenter:action of the system, optionally with ?parameters')
			->setOption('id', 'menuItem-route');

		$linkType->addCondition(Form::Equal, MenuLinkType::Category->value)
			->toggle('menuItem-categoryId');
		$linkType->addCondition(Form::Equal, MenuLinkType::Article->value)
			->toggle('menuItem-articleId');
		$linkType->addCondition(Form::Equal, MenuLinkType::Page->value)
			->toggle('menuItem-pageId');
		$linkType->addCondition(Form::Equal, MenuLinkType::Section->value)
			->toggle('menuItem-sectionId');
		$linkType->addCondition(Form::Equal, MenuLinkType::Url->value)
			->toggle('menuItem-url');
		$linkType->addCondition(Form::Equal, MenuLinkType::Route->value)
			->toggle('menuItem-route');

		$categoryId->addConditionOn($linkType, Form::Equal, MenuLinkType::Category->value)
			->setRequired('Select a category.');
		$articleId->addConditionOn($linkType, Form::Equal, MenuLinkType::Article->value)
			->setRequired('Select an article.');
		$pageId->addConditionOn($linkType, Form::Equal, MenuLinkType::Page->value)
			->setRequired('Select a page.');
		$sectionId->addConditionOn($linkType, Form::Equal, MenuLinkType::Section->value)
			->setRequired('Select a section.');
		$url->addConditionOn($linkType, Form::Equal, MenuLinkType::Url->value)
			->setRequired('Enter the URL.');
		$route->addConditionOn($linkType, Form::Equal, MenuLinkType::Route->value)
			->setRequired('Enter the route.')
			->addRule(Form::Pattern, 'The route must look like :Front:Presenter:action, optionally with ?parameters.', self::RoutePattern);

		// popisek pro každý jazyk (firecms_menuItemDescriptions); prázdný = název kategorie/článku/stránky
		$defaultLanguage = $this->languages->getDefaultLanguage();
		$labels = $form->addContainer('labels');
		foreach ($this->languages->getLanguages() as $languageId => $shortcut) {
			$labelControl = $labels->addText((string) $languageId, \Nette\Utils\Html::el()->setText($this->translate('Label') . ' (' . $shortcut . ')'))
				->setNullable()
				->setMaxLength(255);
			if ($languageId === $defaultLanguage) {
				$labelControl->setOption('description', $this->translate('Empty = title of the category/article. Required for URL and route.'));
			}
		}
		$defaultLabel = $labels[(string) $defaultLanguage];

		$parent = $form->addSelect('parentId', 'Parent item', $this->getParentOptions($menuId, $language, null))
			->setTranslator(null)
			->setPrompt($this->translate('— top level —'));

		$form->addCheckbox('newWindow', 'Open in new window');

		$form->addSubmit('send', 'Save');

		$form->onValidate[] = function () use ($linkType, $defaultLabel, $parent): void {
			$type = MenuLinkType::tryFrom(self::toString($linkType->getValue()));
			// URL/route nemají vlastní název - popisek aspoň ve výchozím jazyce (ostatní jazyky ho převezmou)
			if ($defaultLabel instanceof \Nette\Forms\Controls\TextInput) {
				$labelValue = $defaultLabel->getValue();
				if ($type !== null && !$type->targetsContent() && (!is_string($labelValue) || trim($labelValue) === '')) {
					$defaultLabel->addError('Enter the label - URL and route items have no title of their own.');
				}
			}
			// editId nastavuje onValidate z BaseFormFactory::create() (skryté pole modalu), který běží dřív
			$parentId = self::toNullableInt($parent->getValue());
			if ($this->isEditMode() && $parentId !== null
				&& in_array($parentId, $this->model->getSubtreeIds(self::toInt($this->getEditId())), true)) {
				$parent->addError('The item cannot be placed under itself or its sub-item.');
			}
		};
		$form->onSuccess[] = function (Form $form) use ($menuId, $language): void {
			$this->formSucceeded($form, $menuId, $language);
		};

		return $form;
	}


	/**
	 * Hodnoty položky do formuláře - `target` se rozloží do pole podle typu odkazu
	 */
	public function setItemDefaults(Form $form, int $itemId, string $language): void
	{
		$item = $this->model->getItem($itemId);
		if (!$item) {
			throw new \InvalidArgumentException("Can not edit item with id '" . $itemId . "'");
		}
		$this->setEditId($itemId);

		$targetField = match ($item->getLinkType()) {
			MenuLinkType::Category => 'categoryId',
			MenuLinkType::Article => 'articleId',
			MenuLinkType::Page => 'pageId',
			MenuLinkType::Section => 'sectionId',
			MenuLinkType::Url => 'url',
			MenuLinkType::Route => 'route',
			default => null,
		};

		$defaults = [
			'editId' => $itemId,
			'active' => $item->active,
			'linkType' => $item->linkType,
			'labels' => $this->model->getItemLabels($itemId),
			'parentId' => $item->parentId,
			'newWindow' => $item->newWindow,
		];
		if ($targetField !== null) {
			$defaults[$targetField] = in_array($targetField, ['categoryId', 'articleId', 'pageId', 'sectionId'], true) ? self::toInt($item->target) : $item->target;
		}

		$parentId = $form['parentId'];
		if ($parentId instanceof \Nette\Forms\Controls\SelectBox) {
			$parentId->setItems($this->getParentOptions($item->menuId, $language, $itemId));
		}
		$form->setDefaults($defaults);
	}


	private function formSucceeded(Form $form, int $menuId, string $language): void
	{
		/** @var array<string, mixed> $values */
		$values = $form->getValues('array');
		$linkType = MenuLinkType::from(self::toString($values['linkType']));
		$data = [
			'active' => (bool) $values['active'],
			'linkType' => $linkType->value,
			'target' => match ($linkType) {
				MenuLinkType::Category => (string) self::toInt($values['categoryId']),
				MenuLinkType::Article => (string) self::toInt($values['articleId']),
				MenuLinkType::Page => (string) self::toInt($values['pageId']),
				MenuLinkType::Section => (string) self::toInt($values['sectionId']),
				MenuLinkType::Url => trim(self::toString($values['url'])),
				MenuLinkType::Route => trim(self::toString($values['route'])),
			},
			'parentId' => self::toNullableInt($values['parentId']),
			'newWindow' => (bool) $values['newWindow'],
		];
		$labels = [];
		foreach (is_array($values['labels']) ? $values['labels'] : [] as $languageId => $label) {
			$labels[(string) $languageId] = is_string($label) ? $label : null;
		}

		if ($this->isEditMode()) {
			$this->model->updateItem(self::toInt($this->getEditId()), $data, $labels);
		} else {
			$this->model->insertItem($menuId, $data, $labels);
		}
	}


	/**
	 * Kategorie ve stromovém pořadí (nested set `categoryLeft`), odsazené podle úrovně
	 * @return array<int, string>
	 */
	private function getCategoryOptions(string $language): array
	{
		$selection = $this->categoriesModel->findAll()
			->select('id, level')
			->where('historyId', null)
			->where('status NOT IN ?', ['auto-draft', 'trash'])
			->order('categoryLeft')
			->order('position');
		$this->categoriesModel->selectTitle($selection, '`' . $this->categoriesModel->getTableName() . '`.`id`', $language);

		$options = [];
		foreach ($selection as $category) {
			$id = self::toInt($category->id);
			$title = is_string($category->title) ? $category->title : '#' . $id;
			$options[$id] = str_repeat('— ', max(0, self::toInt($category->level))) . $title;
		}

		return $options;
	}


	/**
	 * @return array<int, string>
	 */
	private function getArticleOptions(string $language): array
	{
		$selection = $this->articlesModel->findAll()
			->select('id')
			->where('historyId', null)
			->where('status NOT IN ?', ['auto-draft', 'trash']);
		$this->articlesModel->selectTitle($selection, '`' . $this->articlesModel->getTableName() . '`.`id`', $language);

		$options = [];
		foreach ($selection->order('title') as $article) {
			$id = self::toInt($article->id);
			$options[$id] = is_string($article->title) ? $article->title : '#' . $id;
		}

		return $options;
	}


	/**
	 * Stránky (i nepublikované - menu je stejně zobrazí až po publikování)
	 * @return array<int, string>
	 */
	private function getPageOptions(string $language): array
	{
		$options = [];
		foreach ($this->pagesModel->getTree($language) as $page) {
			$options[$page['id']] = str_repeat('— ', $page['level']) . ($page['title'] ?? '#' . $page['id']);
		}

		return $options;
	}


	/**
	 * Položky menu, pod které jde položku zařadit (ne ona sama ani její potomci)
	 * @return array<int, string>
	 */
	private function getParentOptions(int $menuId, string $language, ?int $editId): array
	{
		$excluded = $editId !== null ? $this->model->getSubtreeIds($editId) : [];

		$options = [];
		foreach ($this->model->getItemsTree($menuId, $language) as $item) {
			if (in_array($item->id, $excluded, true)) {
				continue;
			}
			$options[$item->id] = str_repeat('— ', $item->level)
				. ($item->label ?? ($item->getLinkType()?->label() ?? $item->linkType) . ': ' . $item->target);
		}

		return $options;
	}


	/**
	 * Výzva selectu s vypnutým překladem položek (položky jsou názvy kategorií/článků, ne texty k překladu)
	 */
	private function translate(string $message): string
	{
		return (string) $this->translator->translate($message);
	}


	/**
	 * Hodnota z DB/formuláře jako int - s kontrolou za běhu, ne slepé přetypování
	 */
	private static function toInt(mixed $value): int
	{
		if (is_int($value)) {
			return $value;
		}
		if (is_string($value) && preg_match('#^-?\d+$#D', $value)) {
			return (int) $value;
		}
		throw new \UnexpectedValueException('Expected integer value, got ' . get_debug_type($value) . '.');
	}


	private static function toNullableInt(mixed $value): ?int
	{
		return $value === null || $value === '' ? null : self::toInt($value);
	}


	private static function toString(mixed $value): string
	{
		if (is_string($value) || is_int($value)) {
			return (string) $value;
		}
		throw new \UnexpectedValueException('Expected string value, got ' . get_debug_type($value) . '.');
	}
}
