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
use pocketmine\tile\Dropper as TileDropper;

class Dropper extends Solid {

	protected $id = self::DROPPER;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getHardness() {
		return 3.5;
	}

	public function getName() : string {
		return "Dropper";
	}

	public function getToolType() {
		return Tool::TYPE_PICKAXE;
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$dispenser = null;
		if($player instanceof Player) {
			$pitch = $player->getPitch();
			if(abs($pitch) >= 45) {
				if($pitch < 0) $f = 4; else $f = 5;
			} else $f = $player->getDirection();
		} else $f = 0;
		$faces = [3 => 3, 0 => 4, 2 => 5, 1 => 2, 4 => 0, 5 => 1];
		$this->meta = $faces[$f];

		return true;
	}

	public function getDrops(Item $item) : array {
		return [[$this->id, 0, 1],];
	}
}
