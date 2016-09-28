<?php

/*
 *
 *  _____   _____   __   _   _   _____  __    __  _____
 * /  ___| | ____| |  \ | | | | /  ___/ \ \  / / /  ___/
 * | |     | |__   |   \| | | | | |___   \ \/ /  | |___
 * | |  _  |  __|  | |\   | | | \___  \   \  /   \___  \
 * | |_| | | |___  | | \  | | |  ___| |   / /     ___| |
 * \_____/ |_____| |_|  \_| |_| /_____/  /_/     /_____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author iTX Technologies
 * @link https://itxtech.org
 *
 */

namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\tile\ItemFrame as ItemFrameTile;

class ItemFrame extends Transparent {

	protected $id = self::ITEM_FRAME_BLOCK;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getName() : string {
		return "Item Frame";
	}

	public function onBreak(Item $item) {
		$this->getLevel()->setBlock($this, new Air(), true, false);
	}

	public function getDrops(Item $item) : array {
		return [[Item::ITEM_FRAME, 0, 1]];
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		if($target->isTransparent() === false and $face > 1 and $block->isSolid() === false) {
			$faces = [2 => 3, 3 => 2, 4 => 1, 5 => 0,];
			$this->meta = $faces[$face];
			$this->getLevel()->setBlock($block, $this, true, true);
		}
	}
}