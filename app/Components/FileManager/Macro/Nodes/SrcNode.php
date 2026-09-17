<?php
declare(strict_types=1);

namespace App\Components\FileManager\Macro\Nodes;

use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * n:src="$image [, width [, height]]"
 * Renders the image URL into the `src` attribute.
 */
class SrcNode extends StatementNode
{
	public ExpressionNode $image;
	public ArrayNode $args;


	public static function create(Tag $tag): static
	{
		$tag->expectArguments();

		$node = new static;
		$node->image = $tag->parser->parseUnquotedStringOrExpression();
		$tag->parser->stream->tryConsume(',');
		$node->args = $tag->parser->parseArguments();
		return $node;
	}


	public function print(PrintContext $context): string
	{
		$context->beginEscape()->enterHtmlAttribute();
		$res = $context->format(
			<<<'XX'
				echo ' src="'; echo %escape($__imagestore->link(App\Components\FileManager\Request\ImageRequest::fromMacro(%node, %node?))) %line; echo '"';
				XX,
			$this->image,
			$this->args,
			$this->position,
		);
		$context->restoreEscape();
		return $res;
	}


	public function &getIterator(): \Generator
	{
		yield $this->image;
		yield $this->args;
	}
}
