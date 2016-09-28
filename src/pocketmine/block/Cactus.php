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

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;

class Cactus extends Transparent {

	protected $id = self::CACTUS;

	public function __construct($meta = 0) {
		$this->meta = $meta;
	}

	public function getHardness() {
		return 0.4;
	}

	public function hasEntityCollision() {
		return true;
	}

	public function getName() : string {
		return "Cactus";
	}

	public function onEntityCollide(Entity $entity) {
		$ev = new EntityDamageByBlockEvent($this, $entity, EntityDamageEvent::CAUSE_CONTACT, 1);
		if(!$ev->isCancelled()) $entity->attack($ev->getFinalDamage(), $ev);
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$down = $this->getSide(0);
		if($down->getId() === self::SAND or $down->getId() === self::CACTUS) {
			$block0 = $this->getSide(2);
			$block1 = $this->getSide(3);
			$block2 = $this->getSide(4);
			$block3 = $this->getSide(5);
			if($block0->isTransparent() === true and $block1->isTransparent() === true and $block2->isTransparent() === true and $block3->isTransparent() === true) {
				$this->getLevel()->setBlock($this, $this, true);

				return true;
			}
		}

		return false;
	}

	public function getDrops(Item $item) : array {
		return [[$this->id, 0, 1],];
	}

	protected function recalculateBoundingBox() {

		return new AxisAlignedBB($this->x + 0.0625, $this->y + 0.0625, $this->z + 0.0625, $this->x + 0.9375, $this->y + 0.9375, $this->z + 0.9375);
	}
}