# 2026-10-16 — `addFileUpload()` s popiskem

**Co:** Extension metoda `addFileUpload()` (jquery-fileupload) má nově signaturu
`addFileUpload(string $name, string|Stringable|null $label = null, ?int $maxFiles = null, ?string $maxFileSize = null)`
— popisek je druhý parametr jako u `addUpload()`/`addText()`. Správce souborů má popisek `Files`.

**Proč:** Prvek neměl jak dostat popisek. Konstruktor `FileUploadControl` navíc předával rodiči (`UploadControl`)
název prvku jako popisek, takže se ve formuláři zobrazovalo např. „files“.

**Dotčené soubory/oblasti:**
- `libs/jzechy/jquery-fileupload/src/FileUploadControl.php` — `register()`: parametr `$label`, `setCaption($label)`.
- `app/AdminModule/Forms/AdminForm.php`, `app/Forms/BaseFormFactory.php` — `@method` s novou signaturou.
- `app/AdminModule/Forms/FilesManagerUploadFormFactory.php` — `addFileUpload('files', 'Files')`.

**Rozhodnutí a kompromisy:**
- Popisek jako druhý parametr mění pozici `$maxFiles`/`$maxFileSize`. V jádru je nikdo pozičně nepředával;
  klientský projekt, který volá `addFileUpload('x', 10)`, musí doplnit popisek (`addFileUpload('x', null, 10)`).
- Konstruktor `FileUploadControl` zůstal beze změny (popisek se nastaví až v `register()`), aby přímé
  `new FileUploadControl(...)` dál fungovalo.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md`? ano (sekce o `addFileUpload()` v `AdminForm`)
- DB migrace potřeba? ne
