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

namespace pocketmine\event\entity;

use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\event\Cancellable;
use pocketmine\item\Food;

class EntityEatEvent extends EntityEvent implements Cancellable {

	public static $handlerList = null;

	/** @var Food */
	private $foodSource;

	/** @var int */
	private $foodRestore;

	/** @var float */
	private $saturationRestore;

	private $residue;

	/** @var Effect[] */
	private $additionalEffects;

	public function __construct(Entity $entity, Food $foodSource) {
		$this->entity = $entity;
		$this->foodSource = $foodSource;
		$this->foodRestore = $foodSource->getFoodRestoration();
		$this->saturationRestore = $foodSource->getSaturationRestoration();
		$this->residue = $foodSource->getResidue();
		$this->additionalEffects = $foodSource->getAdditionalEffects();
	}

	public function getFoodSourcRestoration() {
		return $this->foodSource;
	}

	public function getFoodRestoration() : int {
		return $this->foodRestore;
	}

	public function setFoodRestoration(int $foodRestore) {
		$this->foodRestore = $foodRestore;
	}

	public function getSaturationRestoration() : float {
		return $this->saturationRestore;
	}

	public function setSaturationRestoration(float $saturationRestore) {
		$this->saturationRestore = $saturationRestore;
	}

	public function getResidue() {
		return $this->residue;
	}

	public function setResidue($residue) {
		$this->residue = $residue;
	}

	/**
	 * @return Effect[]
	 */
	public function getAdditionalEffects() {
		return $this->additionalEffects;
	}

	/**
	 * @param Effect[] $additionalEffects
	 *
	 * @throws \TypeError
	 */
	public function setAdditionalEffects(array $additionalEffects) {
		foreach($additionalEffects as $effect) {
			if(!($effect instanceof Effect)) {
				throw new \TypeError("Argument 1 passed to EntityEatEvent::setAdditionalEffects() must be an Effect array");
			}
		}
		$this->additionalEffects = $additionalEffects;
	}
}
