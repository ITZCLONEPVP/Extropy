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

namespace pocketmine\entity;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\inventory\InventoryHolder;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item as ItemItem;
use pocketmine\level\Level;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\Player;
use pocketmine\utils\UUID;

class Human extends Creature implements ProjectileSource, InventoryHolder {

	const DATA_PLAYER_FLAG_SLEEP = 1;
	const DATA_PLAYER_FLAG_DEAD = 2;

	const DATA_PLAYER_FLAGS = 27;
	const DATA_PLAYER_BED_POSITION = 29;

	/** @var float */
	public $width = 0.6;

	/** @var float */
	public $length = 0.6;

	/** @var float */
	public $height = 1.8;

	/** @var float */
	public $eyeHeight = 1.62;

	/** @var string */
	protected $nametag = "TESTIFICATE";

	/** @var PlayerInventory */
	protected $inventory;

	/** @var UUID */
	protected $uuid;

	protected $rawUUID;

	protected $skin;

	/** @var bool */
	protected $skinName = false;

	/** @var bool */
	protected $foodEnabled = false;

	/** @var int */
	protected $foodTickTimer = 0;

	public function getSkinName() {
		return $this->skinName;
	}

	/**
	 * @return string
	 */
	public function getRawUniqueId() {
		return $this->rawUUID;
	}

	/**
	 * @param string $str
	 * @param bool $skinName
	 */
	public function setSkin($str, $skinName) {
		$this->skin = $str;
		$this->skinName = $skinName;
	}

	public function setFoodEnabled(bool $enabled = true) {
		$this->foodEnabled = $enabled;
	}

	public function hasFoodEnabled() : bool {
		return $this->foodEnabled;
	}

	public function getMaxFood() : float {
		return $this->attributeMap->getAttribute(Attribute::HUNGER)->getMaxValue();
	}

	public function addFood(float $amount) {
		$attr = $this->attributeMap->getAttribute(Attribute::HUNGER);
		$amount += $attr->getValue();
		$amount = max(min($amount, $attr->getMaxValue()), $attr->getMinValue());
		$this->setFood($amount);
	}

	/**
	 * WARNING: This method does not check if full and may throw an exception if out of bounds.
	 * Use {@link Human::addFood()} for this purpose
	 *
	 * @param float $new
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setFood(float $new) {
		$attr = $this->attributeMap->getAttribute(Attribute::HUNGER);
		$old = $attr->getValue();
		$attr->setValue($new);
		// ranges: 18-20 (regen), 7-17 (none), 1-6 (no sprint), 0 (health depletion)
		foreach([17, 6, 0] as $bound) {
			if(($old > $bound) !== ($new > $bound)) {
				$reset = true;
			}
		}
		if(isset($reset)) {
			$this->foodTickTimer = 0;
		}
	}

	public function addSaturation(float $amount) {
		$attr = $this->attributeMap->getAttribute(Attribute::SATURATION);
		$attr->setValue($attr->getValue() + $amount, true);
	}

	public function getAbsorption() : int {
		return $this->attributeMap->getAttribute(Attribute::ABSORPTION)->getValue();
	}

	public function setAbsorption(int $absorption) {
		$this->attributeMap->getAttribute(Attribute::ABSORPTION)->setValue($absorption);
	}

	public function entityBaseTick($tickDiff = 1, $EnchantL = 0) {
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->foodEnabled) {
			$food = $this->getFood();
			$health = $this->getHealth();
			if($food >= 18) {
				$this->foodTickTimer++;
				if($this->foodTickTimer >= 80 and $health < $this->getMaxHealth()) {
					$this->heal(1, new EntityRegainHealthEvent($this, 1, EntityRegainHealthEvent::CAUSE_SATURATION));
					$this->exhaust(3.0);
					$this->foodTickTimer = 0;
				}
			} elseif($food === 0) {
				$this->foodTickTimer++;
				if($this->foodTickTimer >= 80) {
					$diff = $this->server->getDifficulty();
					$can = false;
					if($diff === 1) {
						$can = $health > 10;
					} elseif($diff === 2) {
						$can = $health > 1;
					} elseif($diff === 3) {
						$can = true;
					}
					if($can) {
						$this->attack(1, new EntityDamageEvent($this, EntityDamageEvent::CAUSE_STARVATION, 1));
					}
				}
			}
			if($food <= 6 and $this->isSprinting()) $this->setSprinting(false);
		}

		return $hasUpdate;
	}

	public function getFood() : float {
		return $this->attributeMap->getAttribute(Attribute::HUNGER)->getValue();
	}

	/**
	 * Increases a human's exhaustion level.
	 *
	 * @param float $amount
	 *
	 * @return float the amount of exhaustion level increased
	 */
	public function exhaust(float $amount) : float {
		$exhaustion = $this->getExhaustion();
		$exhaustion += $amount;

		while($exhaustion >= 4.0) {
			$exhaustion -= 4.0;

			$saturation = $this->getSaturation();
			if($saturation > 0) {
				$saturation = max(0, $saturation - 1.0);
				$this->setSaturation($saturation);
			} else {
				$food = $this->getFood();
				if($food > 0) {
					$food--;
					$this->setFood($food);
				}
			}
		}
		$this->setExhaustion($exhaustion);

		return $amount;
	}

	public function getExhaustion() : float {
		return $this->attributeMap->getAttribute(Attribute::EXHAUSTION)->getValue();
	}

	public function getSaturation() : float {
		return $this->attributeMap->getAttribute(Attribute::SATURATION)->getValue();
	}

	/**
	 * WARNING: This method does not check if saturated and may throw an exception if out of bounds.
	 * Use {@link Human::addSaturation()} for this purpose
	 *
	 * @param float $saturation
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setSaturation(float $saturation) {
		$this->attributeMap->getAttribute(Attribute::SATURATION)->setValue($saturation);
	}

	/**
	 * WARNING: This method does not check if exhausted and does not consume saturation/food.
	 * Use {@link Human::exhaust()} for this purpose.
	 *
	 * @param float $exhaustion
	 */
	public function setExhaustion(float $exhaustion) {
		$this->attributeMap->getAttribute(Attribute::EXHAUSTION)->setValue($exhaustion);
	}

	public function getDrops() {
		$drops = [];
		if($this->inventory !== null) {
			foreach($this->inventory->getContents() as $item) {
				$drops[] = $item;
			}
		}

		return $drops;
	}

	public function saveNBT() {
		parent::saveNBT();
		$this->namedtag->Inventory = new ListTag("Inventory", []);
		$this->namedtag->Inventory->setTagType(NBT::TAG_Compound);
		if($this->inventory !== null) {
			for($slot = 0; $slot < 9; ++$slot) {
				$hotbarSlot = $this->inventory->getHotbarSlotIndex($slot);
				if($hotbarSlot !== -1) {
					$item = $this->inventory->getItem($hotbarSlot);
					if($item->getId() !== 0 and $item->getCount() > 0) {
						$this->namedtag->Inventory[$slot] = new CompoundTag(false, [new ByteTag("Count", $item->getCount()), new ShortTag("Damage", $item->getDamage()), new ByteTag("Slot", $slot), new ByteTag("TrueSlot", $hotbarSlot), new ShortTag("id", $item->getId()),]);
						continue;
					}
				}
				$this->namedtag->Inventory[$slot] = new CompoundTag(false, [new ByteTag("Count", 0), new ShortTag("Damage", 0), new ByteTag("Slot", $slot), new ByteTag("TrueSlot", -1), new ShortTag("id", 0),]);
			}

			//Normal inventory
			$slotCount = Player::SURVIVAL_SLOTS + 9;
			//$slotCount = (($this instanceof Player and ($this->gamemode & 0x01) === 1) ? Player::CREATIVE_SLOTS : Player::SURVIVAL_SLOTS) + 9;
			for($slot = 9; $slot < $slotCount; ++$slot) {
				$item = $this->inventory->getItem($slot - 9);
				$this->namedtag->Inventory[$slot] = new CompoundTag(false, [new ByteTag("Count", $item->getCount()), new ShortTag("Damage", $item->getDamage()), new ByteTag("Slot", $slot), new ShortTag("id", $item->getId()),]);
			}

			//Armor
			for($slot = 100; $slot < 104; ++$slot) {
				$item = $this->inventory->getItem($this->inventory->getSize() + $slot - 100);
				if($item instanceof ItemItem and $item->getId() !== ItemItem::AIR) {
					$this->namedtag->Inventory[$slot] = new CompoundTag(false, [new ByteTag("Count", $item->getCount()), new ShortTag("Damage", $item->getDamage()), new ByteTag("Slot", $slot), new ShortTag("id", $item->getId()),]);
				}
			}
		}

		//Food
		$this->namedtag->foodLevel = new IntTag("foodLevel", $this->getFood());
		$this->namedtag->foodExhaustionLevel = new FloatTag("foodExhaustionLevel", $this->getExhaustion());
		$this->namedtag->foodSaturationLevel = new FloatTag("foodSaturationLevel", $this->getSaturation());
		$this->namedtag->foodTickTimer = new IntTag("foodTickTimer", $this->foodTickTimer);
	}

	public function spawnTo(Player $player) {
		if($player !== $this and !isset($this->hasSpawned[$player->getId()]) and isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])) {
			$this->hasSpawned[$player->getId()] = $player;

			$this->server->updatePlayerListData($this->getUniqueId(), $this->getId(), $this->getName(), $this->skinName, $this->skin, [$player]);

			$pk = new AddPlayerPacket();
			$pk->uuid = $this->getUniqueId();
			$pk->username = $this->getName();
			$pk->eid = $this->getId();
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->speedX = $this->motionX;
			$pk->speedY = $this->motionY;
			$pk->speedZ = $this->motionZ;
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
			$pk->item = $this->getInventory()->getItemInHand();
			$pk->metadata = $this->dataProperties;
			$player->dataPacket($pk);

			$this->inventory->sendArmorContents($player);
			$this->level->addPlayerHandItem($this, $player);

			if(!$this instanceof Player) $this->server->removePlayerListData($this->getUniqueId(), [$player]);
		}
	}

	/**
	 * @return UUID|null
	 */
	public function getUniqueId() {
		return $this->uuid;
	}

	public function getName() {
		return $this->getNameTag();
	}

	public function getInventory() {
		return $this->inventory;
	}

	public function despawnFrom(Player $player) {
		if(isset($this->hasSpawned[$player->getId()])) {
			$pk = new RemoveEntityPacket();
			$pk->eid = $this->getId();
			$player->dataPacket($pk);
			unset($this->hasSpawned[$player->getId()]);
		}
	}

	public function close() {
		if(!$this->closed) {
			if(!($this instanceof Player) or $this->loggedIn) {
				foreach($this->inventory->getViewers() as $viewer) {
					$viewer->removeWindow($this->inventory);
				}
			}
			parent::close();
		}
	}

	protected function addAttributes() {
		parent::addAttributes();

		$this->attributeMap->addAttribute(Attribute::getAttribute(Attribute::SATURATION));
		$this->attributeMap->addAttribute(Attribute::getAttribute(Attribute::EXHAUSTION));
		$this->attributeMap->addAttribute(Attribute::getAttribute(Attribute::HUNGER));
		$this->attributeMap->addAttribute(Attribute::getAttribute(Attribute::EXPERIENCE_LEVEL));
		$this->attributeMap->addAttribute(Attribute::getAttribute(Attribute::EXPERIENCE));
		$this->attributeMap->addAttribute(Attribute::getAttribute(Attribute::HEALTH));
		$this->attributeMap->addAttribute(Attribute::getAttribute(Attribute::MOVEMENT_SPEED));
		$this->attributeMap->addAttribute(Attribute::getAttribute(Attribute::ABSORPTION));
	}

	protected function initEntity() {
		$this->inventory = new PlayerInventory($this);
		if($this instanceof Player) $this->addWindow($this->inventory, 0);

		if(!$this instanceof Player) {
			if(isset($this->namedtag->NameTag)) $this->setNameTag($this->namedtag["NameTag"]);

			if(isset($this->namedtag->Skin) and $this->namedtag->Skin instanceof CompoundTag) $this->setSkin($this->namedtag->Skin["Data"], $this->namedtag->Skin["SkinName"] > 0);

			$this->uuid = UUID::fromData($this->getId(), $this->getSkinData(), $this->getNameTag());
		}

		if(isset($this->namedtag->Inventory) and $this->namedtag->Inventory instanceof ListTag) {
			foreach($this->namedtag->Inventory as $item) {
				if($item["Slot"] >= 0 and $item["Slot"] < 9) { //Hotbar
					$this->inventory->setHotbarSlotIndex($item["Slot"], isset($item["TrueSlot"]) ? $item["TrueSlot"] : -1);
				} elseif($item["Slot"] >= 100 and $item["Slot"] < 104) { //Armor
					$this->inventory->setItem($this->inventory->getSize() + $item["Slot"] - 100, NBT::getItemHelper($item));
				} else {
					$this->inventory->setItem($item["Slot"] - 9, NBT::getItemHelper($item));
				}
			}
		}

		parent::initEntity();

		if(!isset($this->namedtag->foodLevel)) $this->namedtag->foodLevel = new IntTag("foodLevel", $this->getFood());
		$this->setFood($this->namedtag["foodLevel"]);

		if(!isset($this->namedtag->foodExhaustionLevel)) $this->namedtag->foodExhaustionLevel = new FloatTag("foodExhaustionLevel", $this->getExhaustion());
		$this->setExhaustion($this->namedtag["foodExhaustionLevel"]);

		if(!isset($this->namedtag->foodSaturationLevel)) $this->namedtag->foodSaturationLevel = new FloatTag("foodSaturationLevel", $this->getSaturation());
		$this->setSaturation($this->namedtag["foodSaturationLevel"]);

		if(!isset($this->namedtag->foodTickTimer)) $this->namedtag->foodTickTimer = new IntTag("foodTickTimer", $this->foodTickTimer);
		$this->foodTickTimer = $this->namedtag["foodTickTimer"];

		$this->setDataFlag(self::DATA_PLAYER_FLAGS, self::DATA_PLAYER_FLAG_SLEEP, false, self::DATA_TYPE_BYTE);
		$this->setDataProperty(self::DATA_PLAYER_BED_POSITION, self::DATA_TYPE_POS, [0, 0, 0]);
	}

	public function getSkinData() {
		return $this->skin;
	}

}
