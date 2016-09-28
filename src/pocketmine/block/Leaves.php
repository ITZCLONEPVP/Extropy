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
use pocketmine\Player;

class Leaves extends Transparent {

	const OAK = 0;
	const SPRUCE = 1;
	const BIRCH = 2;
	const JUNGLE = 3;
	const ACACIA = 0;
	const DARK_OAK = 1;

	const WOOD_TYPE = self::WOOD;

	protected $id = self::LEAVES;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getHardness() {
		return 0.2;
	}

	public function getToolType() {
		return Tool::TYPE_SHEARS;
	}

	public function getBurnChance() : int {
		return 30;
	}

	public function getBurnAbility() : int {
		return 60;
	}

	public function getName() : string {
		static $names = [self::OAK => "Oak Leaves", self::SPRUCE => "Spruce Leaves", self::BIRCH => "Birch Leaves", self::JUNGLE => "Jungle Leaves",];

		return $names[$this->meta & 0x03];
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$this->meta |= 0x04;
		$this->getLevel()->setBlock($this, $this, true);
	}

	public function getDrops(Item $item) : array {
		$drops = [];
		if(mt_rand(1, 4) === 1) { //Saplings
			$drops[] = [Item::SAPLING, $this->meta & 0x03, 1];
		}
		if(($this->meta & 0x03) === self::OAK and mt_rand(1, 6) === 1) { //Apples
			$drops[] = [Item::APPLE, 0, 1];
		}

		return $drops;
	}
}
