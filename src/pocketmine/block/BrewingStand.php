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
use pocketmine\tile\BrewingStand as TileBrewingStand;

class BrewingStand extends Transparent {

	protected $id = self::BREWING_STAND_BLOCK;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getHardness() {
		return 0.5;
	}

	public function getResistance() {
		return 2.5;
	}

	public function getLightLevel() {
		return 1;
	}

	public function getName() : string {
		return "Brewing Stand";
	}

	public function getDrops(Item $item) : array {
		$drops = [];
		if($item->isPickaxe() >= Tool::TIER_WOODEN) {
			$drops[] = [Item::BREWING_STAND, 0, 1];
		}

		return $drops;
	}
}
