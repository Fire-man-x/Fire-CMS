<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Nette\Http\Request;
use Nette\Http\UrlScript;

/**
 * Sestaví Nette\Http\Request pro testy routerů bez nutnosti reálného HTTP requestu.
 *
 * scriptPath je záměrně "/" (ne prázdný řetězec) — s prázdným scriptPath by
 * UrlScript::getPathInfo() vracelo vždy prázdný řetězec (viz zdroj
 * Nette\Http\UrlScript::setScriptPath()). Produkce běží přes mod_rewrite
 * (www/.htaccess), kde skutečný scriptPath odpovídá kořeni webu, tedy "/".
 */
final class RequestFactory
{
	public static function fromUrl(string $url): Request
	{
		return new Request(new UrlScript($url, '/'));
	}
}
