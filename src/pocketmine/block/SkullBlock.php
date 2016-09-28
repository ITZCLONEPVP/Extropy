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

/*
 * THIS IS COPIED FROM THE PLUGIN FlowerPot MADE BY @beito123!!
 * https://github.com/beito123/PocketMine-MP-Plugins/blob/master/test%2FFlowerPot%2Fsrc%2Fbeito%2FFlowerPot%2Fomake%2FSkull.php
 *
 */
namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;
use pocketmine\tile\Skull;

class SkullBlock extends Transparent {

	const SKELETON = 0;
	const WITHER_SKELETON = 1;
	const ZOMBIE_HEAD = 2;
	const STEVE_HEAD = 3;
	const CREEPER_HEAD = 4;

	protected $id = self::SKULL_BLOCK;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getHardness() {
		return 1;
	}

	public function isHelmet() {
		return true;
	}

	public function isSolid() {
		return false;
	}

	public function getBoundingBox() {
		return new AxisAlignedBB($this->x - 0.75, $this->y - 0.5, $this->z - 0.75, $this->x + 0.75, $this->y + 0.5, $this->z + 0.75);
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$down = $this->getSide(0);
		if($face !== 0 && $fy > 0.5 && $target->getId() !== self::SKULL_BLOCK && !$down instanceof SkullBlock) {
			$this->getLevel()->setBlock($block, Block::get(Block::SKULL_BLOCK, $face), true, true);

			return true;
		}

		return false;
	}

	public function getResistance() {
		return 5;
	}

	public function getName() : string {
		static $names = [0 => "Skeleton Skull", 1 => "Wither Skeleton Skull", 2 => "Zombie Head", 3 => "Head", 4 => "Creeper Head"];

		return $names[$this->meta & 0x04];
	}

	public function getToolType() {
		return Tool::TYPE_PICKAXE;
	}

	public function onBreak(Item $item) {
		$this->getLevel()->setBlock($this, new Air(), true, true);

		return true;
	}

	public function getDrops(Item $item) : array {
		return [[Item::SKULL, 0, 1]];
	}
}
