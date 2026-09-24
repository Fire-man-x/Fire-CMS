-- Kategorie článků bez typu: `homepage` (úvodní stránka je Front:Homepage) a `gallery` (galerie = stránka
-- s obrázky, App\Model\Pages) odstraněny. Zbýval jen `site`, sloupec tedy nemá smysl. Převod dat
-- existujících projektů se dělá ručně (viz docs/Changelog/2026-09-24-sections.md).
ALTER TABLE `firecms_categories` DROP COLUMN `type`;
