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

/*
 * This class is the power of all redstone blocks!
 */

class RedstoneSource extends Flowable {

	protected $maxStrength = 15;

	protected $activated = false;

	public function __construct() {
	}

	public function getMaxStrength() {
		return $this->maxStrength;
	}

	public function canCalc() {
		return false;
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$this->getLevel()->setBlock($this, $this, true);
		if($this->isActivated()) {
			$this->activate();
		}
	}

	public function isActivated(Block $from = null) {
		return $this->activated;
	}

	public function activate(array $ignore = []) {
	}

	public function onBreak(Item $item) {
		$this->getLevel()->setBlock($this, new Air(), true);
	}

	public function activateBlockWithoutWire(Block $block) {
	}

	public function activateBlock(Block $block) {
	}

	public function deactivateBlock(Block $block) {
	}

	public function deactivateBlockWithoutWire(Block $block) {
	}

	public function deactivate(array $ignore = []) {
	}

	public function checkPower(Block $block, array $ignore = [], $ignoreWire = false) {
	}


	public function checkTorchOn(Block $pos, array $ignore = []) {
	}

	public function checkTorchOff(Block $pos, array $ignore = []) {
	}

	public function getStrength() {
		return 0;
	}
}