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
use pocketmine\item\Tool;
use pocketmine\Player;
use pocketmine\tile\Hopper as TileHopper;

class Hopper extends Transparent {

	protected $id = self::HOPPER_BLOCK;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getToolType() {
		return Tool::TYPE_PICKAXE;
	}

	public function getName() : string {
		return "Hopper";
	}

	public function getHardness() {
		return 3;
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$faces = [0 => 0, 1 => 0, 2 => 3, 3 => 2, 4 => 5, 5 => 4];
		$this->meta = $faces[$face];
		$this->getLevel()->setBlock($block, $this, true, true);

		return true;
	}

	public function getDrops(Item $item) : array {
		if($item->isPickaxe() >= 1) {
			return [[Item::HOPPER, 0, 1],];
		} else {
			return [];
		}
	}
}