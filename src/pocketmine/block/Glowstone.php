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

class Glowstone extends Transparent implements SolidLight {

	protected $id = self::GLOWSTONE_BLOCK;

	public function __construct() {

	}

	public function getName() : string {
		return "Glowstone";
	}

	public function getHardness() {
		return 0.3;
	}

	public function getToolType() {
		return Tool::TYPE_PICKAXE;
	}

	public function getLightLevel() {
		return 15;
	}

	public function getDrops(Item $item) : array {
		$num = mt_rand(2, 4);

		return [[Item::GLOWSTONE_DUST, 0, $num],];
	}
}