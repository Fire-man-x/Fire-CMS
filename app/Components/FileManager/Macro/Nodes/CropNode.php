<?php
declare(strict_types=1);

namespace App\Components\FileManager\Macro\Nodes;

use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {crop $image [, width [, height]]}
 * n:crop="$image [, width [, height]]"
 * Renders the cropped image URL, either as plain output or as a `src` attribute.
 */
class CropNode extends StatementNode
{
	public ExpressionNode $image;
	public ArrayNode $args;
	public bool $isAttribute;


	public static function create(Tag $tag): static
	{
		$tag->outputMode = $tag::OutputKeepIndentation;
		$tag->expectArguments();

		$node = new static;
		$node->image = $tag->parser->parseUnquotedStringOrExpression();
		$tag->parser->stream->tryConsume(',');
		$node->args = $tag->parser->parseArguments();
		$node->isAttribute = $tag->isNAttribute();
		return $node;
	}


	public function print(PrintContext $context): string
	{
		if ($this->isAttribute) {
			$context->beginEscape()->enterHtmlAttribute();
			$res = $context->format(
				<<<'XX'
					echo ' src="'; echo %escape($__imagestore->link(App\Components\FileManager\Macro\ImageRequest::crop(%node, %node?))) %line; echo '"';
					XX,
				$this->image,
				$this->args,
				$this->position,
			);
			$context->restoreEscape();
			return $res;
		}

		return $context->format(
			'echo %escape($__imagestore->link(App\Components\FileManager\Macro\ImageRequest::crop(%node, %node?))) %line;',
			$this->image,
			$this->args,
			$this->position,
		);
	}


	public function &getIterator(): \Generator
	{
		yield $this->image;
		yield $this->args;
	}
}
