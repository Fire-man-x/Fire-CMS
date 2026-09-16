# AdminModule

Administrace, routovaná přes `AdminRouter` na `/administrace/<presenter>/<action>[/<id>]` (viz
`Architecture/routing.md`). `App\AdminModule\Presenters\BasePresenter` vynucuje ACL (`#[Secured]` atributy)
a přepíná session na samostatný `'admin'` auth namespace — viz `Architecture/presenters.md`.

## Struktura

- `app/AdminModule/Presenters/*Presenter.php` — obsahové CRUD presentery jádra (`Articles`, `Categories`,
  `Menus`, `Comments`, `FilesManager`, `Pets`, `Owners`, `Reservations`, `Facilities`,
  `ContractTemplates`, ...).
- `app/AdminModule/SettingsModule/Presenters/*Presenter.php` — nastavení (`Users`, `Roles`, `Languages`,
  `Tags`, `Urls`, ...), vlastní `BasePresenter` dědící z `AdminModule\Presenters\BasePresenter`.
- `app/AdminModule/templates/@layout.latte` — sdílený layout administrace, obsahuje hlavní navigaci
  (dropdown menu "Content", dynamicky generovaný "Other" pro plugin komponenty s tagem `presenter.menu`,
  "Settings", "Hotel"). Nový presenter s vlastním menu odkazem se registruje sem ručně — přidat `<a
  n:if="$user->isAllowed('X', 'view')" n:href=":Admin:X:default...">`, ne přepisovat existující sekce.
- Presentery pluginů/modulů se do administrace napojují mimo tenhle strom (viz
  `Architecture/plugins.md`), ale renderují se přes stejný `@layout.latte`.

## CRUD vzor

Naprostá většina presenterů zde je grid + modální formulář (viz `Architecture/presenters.md` pro plný
kód). Konkrétní implementace najdete v `AI-Context/patterns.md`.

## Datagrid

`Contributte\Datagrid\Datagrid` (přejmenovaný nástupce `ublaboo/datagrid` — composer.json na `ublaboo/*`
odkazuje jen ve starém, dnes nepoužívaném pluginovém kódu, nový kód vždy `Contributte\Datagrid\Datagrid`).
Datový zdroj přímo `Nette\Database\Table\Selection` (z `App\Model\IDatagridSource::getDatagridSource()`),
takže filtrování/řazení/stránkování běží v SQL, ne v PHP.
