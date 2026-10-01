<?php
declare(strict_types=1);

namespace App\Components\Menu\Model;

/**
 * Typ odkazu položky menu (`firecms_menuItems.linkType`) - určuje, co je v `target`
 */
enum MenuLinkType: string
{
	/** target = id kategorie; webové menu k položce připojí i podkategorie se showInMenu */
	case Category = 'category';

	/** target = id článku */
	case Article = 'article';

	/** target = id stránky (App\Model\Pages) */
	case Page = 'page';

	/** target = id sekce (App\Model\Sections) - výpis kategorií a článků sekce */
	case Section = 'section';

	/** target = URL nebo kotva (https://..., /kontakt, #jak-to-funguje) */
	case Url = 'url';

	/** target = Nette odkaz presenteru, volitelně s parametry (":Front:Properties:default?town=Brno") */
	case Route = 'route';


	/**
	 * Anglický popisek pro administraci (překládá se přes translator)
	 */
	public function label(): string
	{
		return match ($this) {
			self::Category => 'Category',
			self::Article => 'Article',
			self::Page => 'Page',
			self::Section => 'Section',
			self::Url => 'URL',
			self::Route => 'Page of the system (route)',
		};
	}


	/**
	 * Cíl je id kategorie/článku (ne volný text) a popisek může zůstat prázdný
	 */
	public function targetsContent(): bool
	{
		return $this === self::Category || $this === self::Article || $this === self::Page || $this === self::Section;
	}


	/**
	 * @return array<string, string> hodnota => popisek pro select
	 */
	public static function options(): array
	{
		$options = [];
		foreach (self::cases() as $case) {
			$options[$case->value] = $case->label();
		}

		return $options;
	}
}
