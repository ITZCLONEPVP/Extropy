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

use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\entity\EntityCombustByBlockEvent;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Server;

class Fire extends Flowable {

	protected $id = self::FIRE;

	/** @var Vector3 */
	private $temporalVector = null;

	public function __construct($meta = 0) {
		$this->meta = $meta;
		if($this->temporalVector === null) {
			$this->temporalVector = new Vector3(0, 0, 0);
		}
	}

	public function hasEntityCollision() {
		return true;
	}

	public function getName() : string {
		return "Fire Block";
	}

	public function getLightLevel() {
		return 15;
	}

	public function isBreakable(Item $item) {
		return false;
	}

	public function canBeReplaced() {
		return true;
	}

	public function onEntityCollide(Entity $entity) {
		if(!$entity->hasEffect(Effect::FIRE_RESISTANCE)) {
			$ev = new EntityDamageByBlockEvent($this, $entity, EntityDamageEvent::CAUSE_FIRE, 1);
			$entity->attack($ev->getFinalDamage(), $ev);
		}

		$ev = new EntityCombustByBlockEvent($this, $entity, 8);
		Server::getInstance()->getPluginManager()->callEvent($ev);
		if(!$ev->isCancelled()) {
			$entity->setOnFire($ev->getDuration());
		}
	}

	public function getDrops(Item $item) : array {
		return [];
	}

	public function onUpdate($type) {
		if($type == Level::BLOCK_UPDATE_NORMAL or $type == Level::BLOCK_UPDATE_RANDOM or $type == Level::BLOCK_UPDATE_SCHEDULED) {
			if(!$this->getSide(Vector3::SIDE_DOWN)->isTopFacingSurfaceSolid() and !$this->canNeighborBurn()) {
				$this->getLevel()->setBlock($this, new Air(), true);

				return Level::BLOCK_UPDATE_NORMAL;
			} elseif($type == Level::BLOCK_UPDATE_NORMAL or $type == Level::BLOCK_UPDATE_RANDOM) {
				$this->getLevel()->scheduleUpdate($this, $this->getTickRate() + mt_rand(0, 10));
			}
		}

		return 0;
	}

	public function getTickRate() : int {
		return 30;
	}
}
