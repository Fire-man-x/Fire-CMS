# FrontModule

Veřejný frontend. `App\FrontModule\Presenters\BasePresenter` (dědí `App\Presenters\BasePresenter`) — bez
ACL enforcementu (frontend nemá privilegovaný obsah stejným způsobem jako administrace), vlastní auth
namespace odlišný od administrace (viz `Architecture/presenters.md`).

## Struktura

- `app/FrontModule/Presenters/*Presenter.php` — `Default`, `Articles`, `Categories`, `Tags`, ...
- `app/FrontModule/Components/*` — front-specifické komponenty (`Articles`, `Categories`,
  `SearchControl`, `LoginLinkControl`).
- `app/FrontModule/templates/` — latte šablony, `@layout.latte` pro layout webu.

## Routing na frontend

Řeší `CustomRouter`/`FrontRouter`, viz `Architecture/routing.md` — hezká URL z DB (`UrlModule`), fallback
`[<locale>/]<presenter>/<action>[/<id>]`.

## Homepage a subtypy kategorií

Homepage je `Categories` s `Homepage` subtypem (`app/Forms/CategorySubtype/HomepageFormPart`) — obsahový
model kategorií se zásuvnými subtypy je popsaný v `Architecture/orm.md`.

Pluginy mohou do frontendu přidat komponentu registrovanou pod tagem `presenter.component` (viz
`Architecture/plugins.md`) — např. `SimpleSignUp`'s `signInFastForm`, `DynamicForms`'s
`contactFormControl`.
