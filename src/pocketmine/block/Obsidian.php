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
use pocketmine\math\Vector3;

class Obsidian extends Solid {

	protected $id = self::OBSIDIAN;

	/** @var Vector3 */
	private $temporalVector = null;

	public function __construct() {
		if($this->temporalVector === null) {
			$this->temporalVector = new Vector3(0, 0, 0);
		}
	}

	public function getName() : string {
		return "Obsidian";
	}

	public function getToolType() {
		return Tool::TYPE_PICKAXE;
	}

	public function getHardness() {
		return 50;
	}

	public function getDrops(Item $item) : array {
		if($item->isPickaxe() >= 5) {
			return [[Item::OBSIDIAN, 0, 1],];
		}

		return [];
	}
}