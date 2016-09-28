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
use pocketmine\math\Vector3;
use pocketmine\Player;

class RedstoneWire extends RedstoneSource {

	const ON = 1;
	const OFF = 2;
	const PLACE = 3;
	const DESTROY = 4;

	protected $id = self::REDSTONE_WIRE;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getName() : string {
		return "Redstone Wire";
	}

	public function updateNormalWire(Block $block, $strength, $type, array $hasUpdated) {
		/** @var RedstoneWire $block */
		if($block->getId() == Block::REDSTONE_WIRE) {
			if($block->getStrength() < $strength) {
				return $block->calcSignal($strength, $type, $hasUpdated);
			}
		}

		return $hasUpdated;
	}

	public function onUpdate($type) {
		if($type === Level::BLOCK_UPDATE_NORMAL) {
			$down = $this->getSide(Vector3::SIDE_DOWN);
			if($down instanceof Transparent and $down->getId() != Block::INACTIVE_REDSTONE_LAMP and $down->getId() != Block::ACTIVE_REDSTONE_LAMP) {
				$this->getLevel()->useBreakOn($this);

				return Level::BLOCK_UPDATE_NORMAL;
			}
		}

		return true;
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$down = $this->getSide(Vector3::SIDE_DOWN);
		if($down instanceof Transparent and $down->getId() != Block::INACTIVE_REDSTONE_LAMP and $down->getId() != Block::ACTIVE_REDSTONE_LAMP) return; else {
			$this->getLevel()->setBlock($block, $this, true, false);
		}
	}

	public function getDrops(Item $item) : array {
		return [[Item::REDSTONE, 0, 1]];
	}
}