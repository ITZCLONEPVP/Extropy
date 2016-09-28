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

use pocketmine\event\player\PlayerGlassBottleEvent;
use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\GraySplashSound;
use pocketmine\level\sound\SpellSound;
use pocketmine\level\sound\SplashSound;
use pocketmine\tile\Cauldron as TileCauldron;

class Cauldron extends Solid {

	protected $id = self::CAULDRON_BLOCK;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getHardness() {
		return 2;
	}

	public function getName() : string {
		return "Cauldron";
	}

	public function getToolType() {
		return Tool::TYPE_PICKAXE;
	}

	public function onBreak(Item $item) {
		$this->getLevel()->setBlock($this, new Air(), true);

		return true;
	}

	public function getDrops(Item $item) : array {
		if($item->isPickaxe() >= 1) {
			return [[Item::CAULDRON, 0, 1]];
		}

		return [];
	}

	public function isEmpty() {
		return $this->meta === 0x00;
	}

	public function isFull() {
		return $this->meta === 0x06;
	}
}