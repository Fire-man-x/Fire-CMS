<?php
declare(strict_types=1);

namespace App\FileStorage\Macro;

use App\FileStorage\Macro\Nodes\BgNode;
use App\FileStorage\Macro\Nodes\CropNode;
use App\FileStorage\Macro\Nodes\ImageNode;
use App\FileStorage\Macro\Nodes\SrcNode;
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
