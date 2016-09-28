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
use pocketmine\level\Level;
use pocketmine\Player;

class RedstoneTorch extends RedstoneSource {

	protected $id = self::REDSTONE_TORCH;

	protected $ignore = "";

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getLightLevel() {
		return 7;
	}

	public function getName() : string {
		return "Redstone Torch";
	}

	public function onUpdate($type) {
		$faces = [1 => 4, 2 => 5, 3 => 2, 4 => 3, 5 => 0, 6 => 0, 0 => 0,];
		if($type === Level::BLOCK_UPDATE_NORMAL) {
			$below = $this->getSide(0);
			$side = $this->getDamage();

			if($this->getSide($faces[$side])->isTransparent() === true and !($side === 0 and ($below->getId() === self::FENCE or $below->getId() === self::COBBLE_WALL))) {
				$this->getLevel()->useBreakOn($this);

				return Level::BLOCK_UPDATE_NORMAL;
			}
			$this->activate([$faces[$side]]);
		}

		if($type == Level::BLOCK_UPDATE_SCHEDULED) {
			if($this->id == self::UNLIT_REDSTONE_TORCH) $this->turnOn($this->ignore); else $this->turnOff($this->ignore);

			return Level::BLOCK_UPDATE_SCHEDULED;
		}

		return false;
	}

	public function onBreak(Item $item) {
		$this->getLevel()->setBlock($this, new Air(), true, false);
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$below = $this->getSide(0);

		if($target->isTransparent() === false and $face !== 0) {
			$faces = [1 => 5, 2 => 4, 3 => 3, 4 => 2, 5 => 1,];
			$this->meta = $faces[$face];
			$this->getLevel()->setBlock($block, $this, true, true);

			return true;
		} elseif($below->isTransparent() === false or $below->getId() === self::FENCE or $below->getId() === self::COBBLE_WALL or $below->getId() == Block::INACTIVE_REDSTONE_LAMP or $below->getId() == Block::ACTIVE_REDSTONE_LAMP) {
			$this->meta = 0;
			$this->getLevel()->setBlock($block, $this, true, true);

			return true;
		}

		return false;
	}

	public function getDrops(Item $item) : array {
		return [[Item::LIT_REDSTONE_TORCH, 0, 1],];
	}

	public function isActivated(Block $from = null) {
		return true;
	}
}
