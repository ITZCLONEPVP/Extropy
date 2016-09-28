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
use pocketmine\Player;

class PoweredRepeater extends RedstoneSource {

	const ACTION_ACTIVATE = "Repeater Activate";

	const ACTION_DEACTIVATE = "Repeater Deactivate";

	protected $id = self::POWERED_REPEATER_BLOCK;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getName() : string {
		return "Powered Repeater";
	}

	public function canBeActivated() : bool {
		return true;
	}

	public function getOppositeDirection() : int {
		return $this->getOppositeSide($this->getDirection());
	}

	public function getDirection() : int {
		$direction = 0;
		switch($this->meta % 4) {
			case 0:
				$direction = 3;
				break;
			case 1:
				$direction = 4;
				break;
			case 2:
				$direction = 2;
				break;
			case 3:
				$direction = 5;
				break;
		}

		return $direction;
	}

	public function onActivate(Item $item, Player $player = null) {
		$meta = $this->meta + 4;
		if($meta > 15) $this->meta = $this->meta % 4; else $this->meta = $meta;
		$this->getLevel()->setBlock($this, $this, true, false);

		return true;
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		if($player instanceof Player) {
			$this->meta = ((int)$player->getDirection() + 5) % 4;
		}
		$this->getLevel()->setBlock($block, $this, true, false);
	}

	public function getDrops(Item $item) : array {
		return [[Item::REPEATER, 0, 1]];
	}
}
