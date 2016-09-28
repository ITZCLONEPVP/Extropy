<?php
/*
 * Copied from ImagicalMine
 * THIS IS COPIED FROM THE PLUGIN FlowerPot MADE BY @beito123!!
 * https://github.com/beito123/PocketMine-MP-Plugins/blob/master/test%2FFlowerPot%2Fsrc%2Fbeito%2FFlowerPot%2Fomake%2FSkull.php
 *
 * Genisys Project
 */

namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\tile\FlowerPot as FlowerPotTile;

class FlowerPot extends Flowable {

	protected $id = Block::FLOWER_POT_BLOCK;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getName() : string {
		return "Flower Pot Block";
	}

	public function getBoundingBox() {
		return new AxisAlignedBB($this->x + 0.3125, $this->y, $this->z + 0.3125, $this->x + 0.6875, $this->y + 0.375, $this->z + 0.6875);
	}

	public function getDrops(Item $item) : array {
		return [Item::FLOWER_POT, 0, 1];
	}
}
