<?php
declare(strict_types=1);

namespace App\Mcp;

/**
 * Služba, která přidává nástroje do MCP serveru webu (endpoint `/mcp`, viz App\Presenters\McpPresenter).
 *
 * Nástroje jsou její veřejné metody s atributem `#[Mcp\Capability\Attribute\McpTool]`: název, popis a anotace
 * se berou z atributu, schéma vstupu z parametrů metody (typy, výchozí hodnoty, `#[Mcp\Capability\Attribute\Schema]`
 * u parametru). Stačí službu zaregistrovat v DI (typicky v config.plugin.neon pluginu), McpServerFactory dostane
 * všechny přes `typed(App\Mcp\McpToolProvider)`, stejně jako CronRunner cron úlohy.
 *
 * Popisy nástrojů a parametrů čte jazykový model, pište je proto anglicky (komentáře pro vývojáře česky).
 * Chybu, kterou má model vidět a opravit (neplatné datum, neexistující záznam), hlaste výjimkou
 * Mcp\Exception\ToolCallException se srozumitelnou zprávou. Jiná výjimka skončí obecnou chybou
 * „Error while executing tool“ a zapíše se do logu.
 */
interface McpToolProvider
{

	/**
	 * Návod pro model, jak nástroje tohoto poskytovatele používat - spojí se do `instructions` serveru, které klient
	 * typicky přidá do system promptu. NULL = žádný.
	 */
	public function getInstructions(): ?string;

}
