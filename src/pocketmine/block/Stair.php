<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 * 
 *
*/

namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;

abstract class Stair extends Transparent {

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$faces = [0 => 0, 1 => 2, 2 => 1, 3 => 3,];
		$this->meta = $faces[$player->getDirection()] & 0x03;
		if(($fy > 0.5 and $face !== 1) or $face === 0) {
			$this->meta |= 0x04; //Upside-down stairs
		}
		$this->getLevel()->setBlock($block, $this, true, true);

		return true;
	}

	public function getHardness() {
		return 2;
	}

	public function getResistance() {
		return 15;
	}

	public function getDrops(Item $item) : array {
		if($item->isPickaxe() >= Tool::TIER_WOODEN) {
			return [[$this->getId(), 0, 1],];
		} else {
			return [];
		}
	}

	protected function recalculateBoundingBox() {

		if(($this->getDamage() & 0x04) > 0) {
			return new AxisAlignedBB($this->x, $this->y + 0.5, $this->z, $this->x + 1, $this->y + 1, $this->z + 1);
		} else {
			return new AxisAlignedBB($this->x, $this->y, $this->z, $this->x + 1, $this->y + 0.5, $this->z + 1);
		}
	}
}