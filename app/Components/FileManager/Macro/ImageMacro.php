<?php
declare(strict_types=1);

namespace App\Components\FileManager\Macro;

use App\Components\FileManager\Macro\Nodes\BgNode;
use App\Components\FileManager\Macro\Nodes\CropNode;
use App\Components\FileManager\Macro\Nodes\ImageNode;
use App\Components\FileManager\Macro\Nodes\SrcNode;
use Latte\Extension;

/**
 * Latte template engine image tags
 */
class ImageMacro extends Extension
{

	/**
	 * Returns a list of parsers for Latte tags.
	 */
	public function getTags(): array
	{
		return [
			'n:src' => SrcNode::create(...),
			'n:bg' => BgNode::create(...),
			'image' => ImageNode::create(...),
			'n:image' => ImageNode::create(...),
			'crop' => CropNode::create(...),
			'n:crop' => CropNode::create(...),
		];
	}

}
