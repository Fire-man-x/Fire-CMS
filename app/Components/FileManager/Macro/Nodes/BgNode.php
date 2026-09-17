<?php
declare(strict_types=1);

namespace App\Components\FileManager\Macro\Nodes;

use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * n:bg="$image [, width [, height]]"
 * Renders the cropped image URL into a `style="background-image: url(...)"` attribute.
 */
class BgNode extends StatementNode
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
				echo ' style="background-image: url(\'';
				echo %escape($__imagestore->link(App\Components\FileManager\Request\ImageRequest::crop(%node, %node?))) %line;
				echo '\');"';
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
