<?php

declare(strict_types=1);

namespace Tests\Model;

use App\Components\Menu\Model\MenuItem;
use App\Components\Menu\Model\MenuLinkType;
use App\Components\Menu\Model\Menus;
use App\Model\Languages;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Položky menu oddělené od kategorií (migrace structures/20260923200000.sql): stromové pořadí, pořadí mezi
 * sourozenci, popisky po jazycích, úklid podle cíle. Nadpisy menu po jazycích (structures/20260923210000.sql).
 */
final class MenusItemsTest extends TestCase
{
	private Explorer $db;
	private Menus $menus;


	protected function setUp(): void
	{
		$this->db = SqliteDatabase::create([
			'CREATE TABLE firecms_menus (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				active INTEGER NOT NULL DEFAULT 1,
				name TEXT NOT NULL,
				location TEXT NOT NULL
			)',
			'CREATE TABLE firecms_menuItems (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				menuId INTEGER NOT NULL REFERENCES firecms_menus(id) ON DELETE CASCADE,
				parentId INTEGER NULL REFERENCES firecms_menuItems(id) ON DELETE CASCADE,
				active INTEGER NOT NULL DEFAULT 1,
				position INTEGER NOT NULL DEFAULT 0,
				linkType TEXT NOT NULL,
				target TEXT NOT NULL,
				newWindow INTEGER NOT NULL DEFAULT 0
			)',
			'CREATE TABLE firecms_languages (
				languageId TEXT PRIMARY KEY, active INTEGER NOT NULL DEFAULT 1, "default" INTEGER NOT NULL DEFAULT 0,
				position INTEGER NOT NULL, name TEXT NOT NULL, shortcut TEXT NOT NULL
			)',
			'CREATE TABLE firecms_menuDescriptions (
				menuId INTEGER NOT NULL REFERENCES firecms_menus(id) ON DELETE CASCADE,
				languageId TEXT NOT NULL,
				title TEXT NULL,
				PRIMARY KEY (menuId, languageId)
			)',
			'CREATE TABLE firecms_files (id INTEGER PRIMARY KEY AUTOINCREMENT, originalName TEXT NOT NULL)',
			'CREATE TABLE firecms_menuItemFiles (
				menuItemId INTEGER NOT NULL REFERENCES firecms_menuItems(id) ON DELETE CASCADE,
				fileId INTEGER NOT NULL REFERENCES firecms_files(id) ON DELETE CASCADE,
				position INTEGER NOT NULL DEFAULT 0,
				PRIMARY KEY (menuItemId, fileId)
			)',
			'CREATE TABLE firecms_menuItemDescriptions (
				menuItemId INTEGER NOT NULL REFERENCES firecms_menuItems(id) ON DELETE CASCADE,
				languageId TEXT NOT NULL,
				label TEXT NULL,
				PRIMARY KEY (menuItemId, languageId)
			)',
		]);
		$this->db->query('PRAGMA foreign_keys = ON');
		$this->db->query("INSERT INTO firecms_menus (name, location) VALUES ('Main', 'main-menu')");
		$this->db->query("INSERT INTO firecms_languages (languageId, active, \"default\", position, name, shortcut) VALUES ('cs', 1, 1, 1, 'Čeština', 'CS'), ('en', 1, 0, 2, 'English', 'EN')");
		$this->menus = new Menus($this->db, new LanguageService(new Languages($this->db)));
	}


	/**
	 * @param list<MenuItem> $items
	 * @return list<string>
	 */
	private function shape(array $items): array
	{
		return array_map(fn(MenuItem $item): string => str_repeat('-', $item->level) . $item->target . '@' . $item->position, $items);
	}


	private function add(string $target, ?int $parentId = null, bool $active = true): int
	{
		return $this->menus->insertItem(1, [
			'parentId' => $parentId,
			'active' => $active,
			'linkType' => MenuLinkType::Url->value,
			'target' => $target,
		]);
	}


	public function testTreeOrderAndPositions(): void
	{
		$a = $this->add('a');
		$b = $this->add('b');
		$this->add('a1', $a);
		$this->add('a2', $a);
		$this->add('b1', $b);

		Assert::same(['a@1', '-a1@1', '-a2@2', 'b@2', '-b1@1'], $this->shape($this->menus->getItemsTree(1, 'cs')));
	}


	public function testMoveOnlyBetweenSiblings(): void
	{
		$a = $this->add('a');
		$b = $this->add('b');
		$c = $this->add('c');
		$a1 = $this->add('a1', $a);

		$this->menus->moveItem($c, null, $a); // c před a
		Assert::same(['c@1', 'a@2', '-a1@1', 'b@3'], $this->shape($this->menus->getItemsTree(1, 'cs')));

		$this->menus->moveItem($a1, $b, null); // soused z jiné úrovně - beze změny
		Assert::same(['c@1', 'a@2', '-a1@1', 'b@3'], $this->shape($this->menus->getItemsTree(1, 'cs')));
	}


	public function testDeleteRemovesSubtreeAndClosesGap(): void
	{
		$a = $this->add('a');
		$this->add('a1', $a);
		$this->add('b');

		$this->menus->deleteItem($a);

		Assert::same(['b@1'], $this->shape($this->menus->getItemsTree(1, 'cs')));
	}


	public function testChangingParentMovesToEndOfNewSiblings(): void
	{
		$a = $this->add('a');
		$b = $this->add('b');
		$this->add('b1', $b);

		$this->menus->updateItem($a, ['parentId' => $b, 'linkType' => 'url', 'target' => 'a']);

		Assert::same(['b@1', '-b1@1', '-a@2'], $this->shape($this->menus->getItemsTree(1, 'cs')));
		Assert::exception(
			fn() => $this->menus->updateItem($b, ['parentId' => $a, 'linkType' => 'url', 'target' => 'b']),
			\InvalidArgumentException::class,
		);
	}


	public function testLabelsPerLanguage(): void
	{
		$a = $this->menus->insertItem(1, ['linkType' => 'url', 'target' => '/kontakt'], ['cs' => 'Kontakt', 'en' => 'Contact']);

		Assert::same(['cs' => 'Kontakt', 'en' => 'Contact'], $this->menus->getItemLabels($a));
		Assert::same('Contact', $this->menus->getItemsTree(1, 'en')[0]->label);

		$this->menus->updateItem($a, ['linkType' => 'url', 'target' => '/kontakt'], ['en' => '  ']); // prázdný = smazat
		Assert::same(['cs' => 'Kontakt'], $this->menus->getItemLabels($a));

		$this->menus->updateItem($a, ['linkType' => 'url', 'target' => '/kontakt-2']); // null = popisky beze změny
		Assert::same(['cs' => 'Kontakt'], $this->menus->getItemLabels($a));

		// jazyk bez popisku: náhradní popisek z výchozího jazyka (cs)
		$item = $this->menus->getItemsTree(1, 'en')[0];
		Assert::null($item->label);
		Assert::same('Kontakt', $item->defaultLabel);
	}


	public function testInactiveItemHidesItsChildren(): void
	{
		$a = $this->add('a', null, false);
		$this->add('a1', $a);
		$this->add('b');

		Assert::same(['b@2'], $this->shape($this->menus->getActiveItemsByLocation('main-menu', 'cs')));
		Assert::same([], $this->menus->getActiveItemsByLocation('missing', 'cs'));
	}


	public function testMenuTitlesPerLanguage(): void
	{
		$this->menus->saveTitles(1, ['cs' => 'Pro klienty', 'en' => 'For clients']);
		Assert::same('Pro klienty', $this->menus->getTitle(1, 'cs'));
		Assert::same(['cs' => 'Pro klienty', 'en' => 'For clients'], $this->menus->getTitles(1));

		$this->menus->saveTitles(1, ['cs' => 'Klienti', 'en' => '']); // prázdný = smazat
		Assert::same(['cs' => 'Klienti'], $this->menus->getTitles(1));
		Assert::null($this->menus->getTitle(1, 'en'));
	}


	public function testItemFilesOrderAndCascade(): void
	{
		$a = $this->add('a');
		$b = $this->add('b');
		$this->db->query("INSERT INTO firecms_files (id, originalName) VALUES (10, 'a.png'), (11, 'b.png'), (12, 'c.png')");

		$this->menus->addItemFiles($a, [10, 11]);
		$this->menus->addItemFiles($a, [11, 12]); // 11 už je přiřazený
		$this->menus->addItemFiles($b, [10]);
		$this->menus->sortItemFiles($a, [12, 10, 11]);
		$this->menus->removeItemFile($a, 10);

		$files = $this->menus->getItemFiles([$a, $b]);
		Assert::same([12, 11], array_map(fn($row) => (int) $row->id, $files[$a]));
		Assert::same([10], array_map(fn($row) => (int) $row->id, $files[$b]));
		Assert::same([], $this->menus->getItemFiles([]));

		$this->menus->deleteItem($a); // vazby se smažou s položkou (FK)
		Assert::false(isset($this->menus->getItemFiles([$a])[$a]));
	}


	public function testDeleteItemsByTarget(): void
	{
		$category = $this->menus->insertItem(1, ['linkType' => 'category', 'target' => '6']);
		$this->menus->insertItem(1, ['linkType' => 'url', 'target' => '6'], ['cs' => 'URL 6']);
		$this->add('child', $category);

		$this->menus->deleteItemsByTarget(MenuLinkType::Category, '6');

		Assert::same(['6@1'], $this->shape($this->menus->getItemsTree(1, 'cs')));
	}
}

(new MenusItemsTest())->run();
