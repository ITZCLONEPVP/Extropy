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

/**
 * All the entity classes
 */
namespace pocketmine\entity;

use pocketmine\block\Block;
use pocketmine\block\Fire;
use pocketmine\block\Ladder;
use pocketmine\block\Liquid;
use pocketmine\block\Solid;
use pocketmine\block\Water;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Timings;
use pocketmine\level\format\Chunk;
use pocketmine\level\format\FullChunk;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\metadata\Metadatable;
use pocketmine\metadata\MetadataValue;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\protocol\MobEffectPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\SetEntityDataPacket;
use pocketmine\network\protocol\SetTimePacket;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\ChunkException;

abstract class Entity extends Location implements Metadatable {

	const NETWORK_ID = -1;

	const DATA_TYPE_BYTE = 0;
	const DATA_TYPE_SHORT = 1;
	const DATA_TYPE_INT = 2;
	const DATA_TYPE_FLOAT = 3;
	const DATA_TYPE_STRING = 4;
	const DATA_TYPE_SLOT = 5;
	const DATA_TYPE_POS = 6;
	//	const DATA_TYPE_ROTATION = 7;
	const DATA_TYPE_LONG = 7;

	const DATA_FLAGS = 0;
	const DATA_AIR = 1;
	const DATA_NAMETAG = 2;
	const DATA_SHOW_NAMETAG = 3;
	const DATA_SILENT = 4;
	const DATA_POTION_COLOR = 7;
	const DATA_POTION_AMBIENT = 8;
	const DATA_NO_AI = 15;
	const DATA_LEAD_HOLDER = 23;
	const DATA_LEAD = 24;


	const DATA_FLAG_ONFIRE = 0;
	const DATA_FLAG_SNEAKING = 1;
	const DATA_FLAG_RIDING = 2;
	const DATA_FLAG_SPRINTING = 3;
	const DATA_FLAG_ACTION = 4;
	const DATA_FLAG_INVISIBLE = 5;


	/** @var int */
	public static $entityCount = 1;

	/** @var Entity[] */
	private static $knownEntities = [];

	/** @var array */
	private static $shortNames = [];

	/** @var Entity */
	public $passenger = null;

	/** @var Entity */
	public $vehicle = null;

	/** @var int */
	public $chunkX;

	/** @var int */
	public $chunkZ;

	/** @var Chunk */
	public $chunk;

	/** @var int */
	public $lastX = null;

	/** @var int */
	public $lastY = null;

	/** @var int */
	public $lastZ = null;

	/** @var int */
	public $motionX;

	/** @var int */
	public $motionY;

	/** @var int */
	public $motionZ;

	/** @var int */
	public $lastMotionX;

	/** @var int */
	public $lastMotionY;

	/** @var int */
	public $lastMotionZ;

	/** @var int */
	public $lastYaw;

	/** @var int */
	public $lastPitch;

	/** @var AxisAlignedBB */
	public $boundingBox;

	/** @var bool */
	public $onGround;

	/** @var bool */
	public $inBlock = false;

	/** @var bool */
	public $positionChanged;

	/** @var bool */
	public $motionChanged;

	/** @var bool */
	public $dead;

	/** @var int */
	public $deadTicks = 0;

	/** @var float */
	public $height;

	/** @var float */
	public $eyeHeight = null;

	/** @var float */
	public $width;

	/** @var float */
	public $length;

	/** @var bool */
	public $keepMovement = false;

	/** @var float */
	public $fallDistance = 0;

	/** @var int */
	public $ticksLived = 0;

	/** @var int */
	public $lastUpdate;

	/** @var int */
	public $maxFireTicks;

	/** @var int */
	public $fireTicks;

	/** @var int */
	public $airTicks;

	/** @var CompoundTag */
	public $namedtag;

	/** @var bool */
	public $canCollide = true;

	/** @var bool */
	public $isCollided = false;

	/** @var bool */
	public $isCollidedHorizontally = false;

	/** @var bool */
	public $isCollidedVertically = false;

	/** @var int */
	public $noDamageTicks;

	/** @var bool */
	public $closed = false;

	/** @var int */
	public $lastDamageTime = 0;

	/** @var Player[] */
	protected $hasSpawned = [];

	/** @var Effect[] */
	protected $effects = [];

	/** @var int */
	protected $id;

	/** @var int */
	protected $dataFlags = 0;

	/** @var array */
	protected $dataProperties = [self::DATA_FLAGS => [self::DATA_TYPE_BYTE, 0], self::DATA_AIR => [self::DATA_TYPE_SHORT, 300], self::DATA_NAMETAG => [self::DATA_TYPE_STRING, ""], self::DATA_SHOW_NAMETAG => [self::DATA_TYPE_BYTE, 1], self::DATA_SILENT => [self::DATA_TYPE_BYTE, 0], self::DATA_NO_AI => [self::DATA_TYPE_BYTE, 0], self::DATA_LEAD_HOLDER => [self::DATA_TYPE_LONG, -1], self::DATA_LEAD => [self::DATA_TYPE_BYTE, 0],];

	/** @var EntityDamageEvent */
	protected $lastDamageCause = null;

	/** @var int */
	protected $age = 0;

	/** @var int */
	protected $ySize = 0;

	/** @var float */
	protected $stepHeight = 0;

	/** @var bool */
	protected $isStatic = false;

	/** @var bool */
	protected $justCreated;

	/** @var bool */
	protected $fireProof;

	/** @var AttributeMap */
	protected $attributeMap;

	/** @var float */
	protected $gravity;

	/** @var float */
	protected $drag;

	/** @var Server */
	protected $server;

	/** @var \pocketmine\event\TimingsHandler */
	protected $timings;

	/** @var int */
	protected $checkBlockCollisionTicks = 1;

	/** @var array */
	protected $blocksAround = [];

	/** @var Vector3 */
	protected $temporalVector;

	/** @var int */
	private $health = 20;

	/** @var int */
	private $maxHealth = 20;

	/** @var bool */
	private $invulnerable;

	public function __construct(FullChunk $chunk, CompoundTag $nbt) {
		if($chunk === null or $chunk->getProvider() === null) {
			throw new ChunkException("Invalid garbage Chunk given to Entity");
		}

		$this->timings = Timings::getEntityTimings($this);

		$this->temporalVector = new Vector3();

		if($this->eyeHeight === null) {
			$this->eyeHeight = $this->height / 2 + 0.1;
		}

		$this->id = Entity::$entityCount++;
		$this->justCreated = true;
		$this->namedtag = $nbt;

		$this->chunk = $chunk;
		$this->setLevel($chunk->getProvider()->getLevel());
		$this->server = $chunk->getProvider()->getLevel()->getServer();

		$this->boundingBox = new AxisAlignedBB(0, 0, 0, 0, 0, 0);
		$this->setPositionAndRotation($this->temporalVector->setComponents($this->namedtag["Pos"][0], $this->namedtag["Pos"][1], $this->namedtag["Pos"][2]), $this->namedtag["Rotation"][0], $this->namedtag["Rotation"][1]);
		$this->setMotion($this->temporalVector->setComponents($this->namedtag["Motion"][0], $this->namedtag["Motion"][1], $this->namedtag["Motion"][2]), false);

		if(!isset($this->namedtag->FallDistance)) $this->namedtag->FallDistance = new FloatTag("FallDistance", 0);
		$this->fallDistance = $this->namedtag["FallDistance"];

		if(!isset($this->namedtag->Fire)) $this->namedtag->Fire = new ShortTag("Fire", 0);
		$this->fireTicks = $this->namedtag["Fire"];

		if(!isset($this->namedtag->Air)) $this->namedtag->Air = new ShortTag("Air", 300);
		$this->dataProperties[self::DATA_AIR] = [self::DATA_TYPE_SHORT, $this->namedtag["Air"]];

		if(!isset($this->namedtag->OnGround)) $this->namedtag->OnGround = new ByteTag("OnGround", 0);
		$this->onGround = $this->namedtag["OnGround"] > 0 ? true : false;

		if(!isset($this->namedtag->Invulnerable)) $this->namedtag->Invulnerable = new ByteTag("Invulnerable", 0);
		$this->invulnerable = $this->namedtag["Invulnerable"] > 0 ? true : false;

		$this->attributeMap = new AttributeMap();

		$this->chunk->addEntity($this);
		$this->level->addEntity($this);
		$this->initEntity();
		$this->lastUpdate = $this->server->getTick();
		$this->server->getPluginManager()->callEvent(new EntitySpawnEvent($this));

		$this->checkBlockCollisionTicks = (int)$this->server->getAdvancedProperty("main.check-block-collision", 1);

		$this->scheduleUpdate();

	}

	public function setPositionAndRotation(Vector3 $pos, int $yaw, int $pitch) {
		if($this->setPosition($pos) === true) {
			$this->setRotation($yaw, $pitch);

			return true;
		}

		return false;
	}

	public function setPosition(Vector3 $pos) {
		if($this->closed) return false;

		if($pos instanceof Position and $pos->level !== null and $pos->level !== $this->level) {
			if($this->switchLevel($pos->getLevel()) === false) return false;
		}

		$this->x = $pos->x;
		$this->y = $pos->y;
		$this->z = $pos->z;

		$radius = $this->width / 2;
		$this->boundingBox->setBounds($pos->x - $radius, $pos->y, $pos->z - $radius, $pos->x + $radius, $pos->y + $this->height, $pos->z + $radius);

		if(!$this instanceof Player) $this->checkChunks();

		return true;
	}

	protected function switchLevel(Level $targetLevel) : bool {
		if($this->isValid()) {
			$this->server->getPluginManager()->callEvent($ev = new EntityLevelChangeEvent($this, $this->level, $targetLevel));
			if($ev->isCancelled()) return false;

			$this->level->removeEntity($this);
			if($this->chunk !== null) $this->chunk->removeEntity($this);
			$this->despawnFromAll();
			if($this instanceof Player) {
				foreach($this->usedChunks as $index => $d) {
					if(PHP_INT_SIZE === 8) {
						$X = ($index >> 32) << 32 >> 32;
						$Z = ($index & 0xFFFFFFFF) << 32 >> 32;
					} else {
						list($X, $Z) = explode(":", $index);
						$X = (int)$X;
						$Z = (int)$Z;
					};
					$this->unloadChunk($X, $Z);
				}
			}
		}
		$this->setLevel($targetLevel);
		$this->level->addEntity($this);
		if($this instanceof Player) {
			$this->usedChunks = [];
			$pk = new SetTimePacket();
			$pk->time = $this->level->getTime();
			$pk->started = $this->level->stopTime == false;
			$this->dataPacket($pk);
		}
		$this->chunk = null;

		return true;
	}

	public function despawnFromAll() {
		foreach($this->hasSpawned as $player) $this->despawnFrom($player);
	}

	/**
	 * @param Player $player
	 */
	public function despawnFrom(Player $player) {
		if(isset($this->hasSpawned[$player->getId()])) {
			$pk = new RemoveEntityPacket();
			$pk->eid = $this->getId();
			$player->dataPacket($pk);
			unset($this->hasSpawned[$player->getId()]);
		}
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	public function setRotation(int $yaw, int $pitch) {
		$this->yaw = $yaw;
		$this->pitch = $pitch;
		$this->scheduleUpdate();
	}

	public final function scheduleUpdate() {
		$this->level->updateEntities[$this->id] = $this;
	}

	public function setMotion(Vector3 $motion, bool $forceUpdate = true) : bool {
		if(!$this->justCreated) {
			$this->server->getPluginManager()->callEvent($ev = new EntityMotionEvent($this, $motion));
			if($ev->isCancelled()) {
				return false;
			}
		}

		$this->motionX = $motion->x;
		$this->motionY = $motion->y;
		$this->motionZ = $motion->z;

		if(!$this->justCreated) {
			$this->updateMovement();
		}

		return true;
	}

	protected function updateMovement() {
		$diffPosition = ($this->x - $this->lastX) ** 2 + ($this->y - $this->lastY) ** 2 + ($this->z - $this->lastZ) ** 2;
		$diffRotation = ($this->yaw - $this->lastYaw) ** 2 + ($this->pitch - $this->lastPitch) ** 2;

		$diffMotion = ($this->motionX - $this->lastMotionX) ** 2 + ($this->motionY - $this->lastMotionY) ** 2 + ($this->motionZ - $this->lastMotionZ) ** 2;

		if($diffPosition > 0.04 or $diffRotation > 2.25 and ($diffMotion > 0.0001 and $this->getMotion()->lengthSquared() <= 0.00001)) { //0.2 ** 2, 1.5 ** 2
			$this->lastX = $this->x;
			$this->lastY = $this->y;
			$this->lastZ = $this->z;

			$this->lastYaw = $this->yaw;
			$this->lastPitch = $this->pitch;

			$this->level->addEntityMovement($this->getViewers(), $this->id, $this->x, $this->y + $this->getEyeHeight(), $this->z, $this->yaw, $this->pitch, $this->yaw);
		}

		if($diffMotion > 0.0025 or ($diffMotion > 0.0001 and $this->getMotion()->lengthSquared() <= 0.0001)) { //0.05 ** 2
			$this->lastMotionX = $this->motionX;
			$this->lastMotionY = $this->motionY;
			$this->lastMotionZ = $this->motionZ;

			$this->level->addEntityMotion($this->getViewers(), $this->id, $this->motionX, $this->motionY, $this->motionZ);
		}
	}

	public function getMotion() {
		return $this->temporalVector->setComponents($this->motionX, $this->motionY, $this->motionZ);
	}

	/**
	 * @return Player[]
	 */
	public function getViewers() {
		return $this->hasSpawned;
	}

	public function getEyeHeight() {
		return $this->eyeHeight;
	}

	protected function initEntity() {
		if(isset($this->namedtag->CustomName)) {
			$this->setNameTag($this->namedtag["CustomName"]);
			if(isset($this->namedtag->CustomNameVisible)) $this->setNameTagVisible($this->namedtag["CustomNameVisible"] > 0);
		}

		$this->scheduleUpdate();

		$this->addAttributes();

		if(isset($this->namedtag->ActiveEffects)) {
			foreach($this->namedtag->ActiveEffects->getValue() as $e) {
				$effect = Effect::getEffect($e["Id"]);
				if(!$effect instanceof Effect) continue;

				$effect->setAmplifier($e["Amplifier"])->setDuration($e["Duration"])->setVisible($e["ShowParticles"] > 0);
				$this->addEffect($effect);
			}
		}
	}

	/**
	 * @param string $name
	 */
	public function setNameTag($name) {
		$this->setDataProperty(self::DATA_NAMETAG, self::DATA_TYPE_STRING, $name);
	}

	/**
	 * @param int $id
	 * @param int $type
	 * @param mixed $value
	 */
	public function setDataProperty($id, $type, $value) {
		if($this->getDataProperty($id) !== $value) {
			$this->dataProperties[$id] = [$type, $value];

			$targets = $this->hasSpawned;
			if($this instanceof Player) {
				if(!$this->spawned) {
					return;
				}
				$targets[] = $this;
			}

			$this->sendData($targets, [$id => $this->dataProperties[$id]]);
		}
	}

	/**
	 * @param int $id
	 *
	 * @return mixed
	 */
	public function getDataProperty($id) {
		return isset($this->dataProperties[$id]) ? $this->dataProperties[$id][1] : null;
	}

	/**
	 * @param Player[]|Player $player
	 * @param array $data Properly formatted entity data, defaults to everything
	 */
	public function sendData($player, array $data = null) {
		if(!is_array($player)) {
			$player = [$player];
		}
		$pk = new SetEntityDataPacket();
		$pk->eid = $this->id;
		$pk->metadata = $data === null ? $this->dataProperties : $data;
		Server::broadcastPacket($player, $pk);
	}

	/**
	 * @param bool $value
	 */
	public function setNameTagVisible($value = true) {
		$this->setDataProperty(self::DATA_SHOW_NAMETAG, self::DATA_TYPE_BYTE, $value ? 1 : 0);
	}

	protected function addAttributes() {
	}

	public function addEffect(Effect $effect) {

		if(isset($this->effects[$effect->getId()])) {
			$oldEffect = $this->effects[$effect->getId()];
			if(($effect->getAmplifier() <= ($oldEffect->getAmplifier())) and $effect->getDuration() < $oldEffect->getDuration()) return false;
			$effect->add($this, true, $oldEffect);
		} else {
			$effect->add($this, false);
		}

		$this->effects[$effect->getId()] = $effect;

		$this->recalculateEffectColor();

		return true;
	}

	protected function recalculateEffectColor() {
		$color = [0, 0, 0]; //RGB
		$count = 0;
		$ambient = true;
		foreach($this->effects as $effect) {
			if($effect->isVisible()) {
				$c = $effect->getColor();
				$color[0] += $c[0] * ($effect->getAmplifier() + 1);
				$color[1] += $c[1] * ($effect->getAmplifier() + 1);
				$color[2] += $c[2] * ($effect->getAmplifier() + 1);
				$count += $effect->getAmplifier() + 1;
				if(!$effect->isAmbient()) {
					$ambient = false;
				}
			}
		}

		if($count > 0) {
			$r = ($color[0] / $count) & 0xff;
			$g = ($color[1] / $count) & 0xff;
			$b = ($color[2] / $count) & 0xff;

			$this->setDataProperty(Entity::DATA_POTION_COLOR, Entity::DATA_TYPE_INT, ($r << 16) + ($g << 8) + $b);
			$this->setDataProperty(Entity::DATA_POTION_AMBIENT, Entity::DATA_TYPE_BYTE, $ambient ? 1 : 0);
		} else {
			$this->setDataProperty(Entity::DATA_POTION_COLOR, Entity::DATA_TYPE_INT, 0);
			$this->setDataProperty(Entity::DATA_POTION_AMBIENT, Entity::DATA_TYPE_BYTE, 0);
		}
	}

	/**
	 * @param int|string $type
	 * @param FullChunk $chunk
	 * @param CompoundTag $nbt
	 * @param            $args
	 *
	 * @return Entity
	 */
	public static function createEntity($type, FullChunk $chunk, CompoundTag $nbt, ...$args) {
		if(isset(self::$knownEntities[$type])) {
			$class = self::$knownEntities[$type];

			return new $class($chunk, $nbt, ...$args);
		}

		return null;
	}

	public static function registerEntity($className, $force = false) {
		$class = new \ReflectionClass($className);
		if(is_a($className, Entity::class, true) and !$class->isAbstract()) {
			if($className::NETWORK_ID !== -1) {
				self::$knownEntities[$className::NETWORK_ID] = $className;
			} elseif(!$force) {
				return false;
			}

			self::$knownEntities[$class->getShortName()] = $className;
			self::$shortNames[$className] = $class->getShortName();

			return true;
		}

		return false;
	}

	/**
	 * @return string
	 */
	public function getNameTag() {
		return $this->getDataProperty(self::DATA_NAMETAG);
	}

	public function isSneaking() {
		return $this->getDataFlag(self::DATA_FLAGS, self::DATA_FLAG_SNEAKING);
	}

	/**
	 * @param int $propertyId
	 * @param int $id
	 *
	 * @return bool
	 */
	public function getDataFlag($propertyId, $id) {
		return (((int)$this->getDataProperty($propertyId)) & (1 << $id)) > 0;
	}

	public function setSneaking($value = true) {
		$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_SNEAKING, (bool)$value);
	}

	/**
	 * @param int $propertyId ;
	 * @param int $id
	 * @param bool $value
	 */
	public function setDataFlag($propertyId, $id, $value = true, $type = self::DATA_TYPE_BYTE) {
		if($this->getDataFlag($propertyId, $id) !== $value) {
			$flags = (int)$this->getDataProperty($propertyId);
			$flags ^= 1 << $id;
			$this->setDataProperty($propertyId, $type, $flags);
		}
	}

	public function setSprinting($value = true) {
		if($value !== $this->isSprinting()) {
			$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_SPRINTING, (bool)$value);
			$attr = $this->attributeMap->getAttribute(Attribute::MOVEMENT_SPEED);
			$attr->setValue($value ? ($attr->getValue() * 1.3) : ($attr->getValue() / 1.3));
		}
	}

	public function isSprinting() {
		return $this->getDataFlag(self::DATA_FLAGS, self::DATA_FLAG_SPRINTING);
	}

	/**
	 * @return Effect[]
	 */
	public function getEffects() {
		return $this->effects;
	}

	public function getEffect($effectId) {
		return isset($this->effects[$effectId]) ? $this->effects[$effectId] : null;
	}

	public function saveNBT() {
		if(!($this instanceof Player)) {
			$this->namedtag->id = new StringTag("id", $this->getSaveId());
			if($this->getNameTag() !== "") {
				$this->namedtag->CustomName = new StringTag("CustomName", $this->getNameTag());
				$this->namedtag->CustomNameVisible = new StringTag("CustomNameVisible", $this->isNameTagVisible());
			} else {
				unset($this->namedtag->CustomName);
				unset($this->namedtag->CustomNameVisible);
			}
		}

		$this->namedtag->Pos = new ListTag("Pos", [new DoubleTag(0, $this->x), new DoubleTag(1, $this->y), new DoubleTag(2, $this->z)]);

		$this->namedtag->Motion = new ListTag("Motion", [new DoubleTag(0, $this->motionX), new DoubleTag(1, $this->motionY), new DoubleTag(2, $this->motionZ)]);

		$this->namedtag->Rotation = new ListTag("Rotation", [new FloatTag(0, $this->yaw), new FloatTag(1, $this->pitch)]);

		$this->namedtag->FallDistance = new FloatTag("FallDistance", $this->fallDistance);
		$this->namedtag->Fire = new ShortTag("Fire", $this->fireTicks);
		$this->namedtag->Air = new ShortTag("Air", $this->getDataProperty(self::DATA_AIR));
		$this->namedtag->OnGround = new ByteTag("OnGround", $this->onGround == true ? 1 : 0);
		$this->namedtag->Invulnerable = new ByteTag("Invulnerable", $this->invulnerable == true ? 1 : 0);

		if(count($this->effects) > 0) {
			$effects = [];
			foreach($this->effects as $effect) {
				$effects[$effect->getId()] = new CompoundTag($effect->getId(), ["Id" => new ByteTag("Id", $effect->getId()), "Amplifier" => new ByteTag("Amplifier", $effect->getAmplifier()), "Duration" => new IntTag("Duration", $effect->getDuration()), "Ambient" => new ByteTag("Ambient", 0), "ShowParticles" => new ByteTag("ShowParticles", $effect->isVisible() ? 1 : 0)]);
			}

			$this->namedtag->ActiveEffects = new ListTag("ActiveEffects", $effects);
		} else {
			unset($this->namedtag->ActiveEffects);
		}
	}

	/**
	 * Returns the short save name
	 *
	 * @return string
	 */
	public function getSaveId() {
		return self::$shortNames[static::class];
	}

	/**
	 * @return bool
	 */
	public function isNameTagVisible() {
		return $this->getDataProperty(self::DATA_SHOW_NAMETAG) > 0;
	}

	public function isSpawned(Player $player) {
		if(isset($this->hasSpawned[$player->getId()])) {
			return true;
		}

		return false;
	}

	public function sendPotionEffects(Player $player) {
		foreach($this->effects as $effect) {
			$pk = new MobEffectPacket();
			$pk->eid = 0;
			$pk->effectId = $effect->getId();
			$pk->amplifier = $effect->getAmplifier();
			$pk->particles = $effect->isVisible();
			$pk->duration = $effect->getDuration();
			$pk->eventId = MobEffectPacket::EVENT_ADD;

			$player->dataPacket($pk);
		}
	}

	/**
	 * @deprecated
	 */
	public function sendMetadata($player) {
		$this->sendData($player);
	}

	/**
	 * @param float $amount
	 * @param EntityRegainHealthEvent $source
	 *
	 */
	public function heal($amount, EntityRegainHealthEvent $source) {
		$this->server->getPluginManager()->callEvent($source);
		if($source->isCancelled()) {
			return;
		}

		$this->setHealth($this->getHealth() + $source->getAmount());
	}

	/**
	 * @return int
	 */
	public function getHealth() {
		return $this->health;
	}

	/**
	 * Sets the health of the Entity. This won't send any update to the players
	 *
	 * @param int $amount
	 */
	public function setHealth($amount) {
		if($amount === $this->health) {
			return;
		}

		if($amount <= 0) {
			$this->health = 0;
			if($this->dead !== true) {
				$this->kill();
			}
		} elseif($amount <= $this->getMaxHealth() or $amount < $this->health) {
			$this->health = (int)$amount;
		} else {
			$this->health = $this->getMaxHealth();
		}
	}

	public function isAlive() {
		return $this->health > 0;
	}

	public function getAttributeMap() : AttributeMap {
		return $this->attributeMap;
	}

	/**
	 * @return EntityDamageEvent|null
	 */
	public function getLastDamageCause() {
		return $this->lastDamageCause;
	}

	/**
	 * @param EntityDamageEvent $type
	 */
	public function setLastDamageCause(EntityDamageEvent $type) {
		$this->lastDamageCause = $type;
	}

	/**
	 * @return int
	 */
	public function getMaxHealth() {
		return $this->maxHealth + ($this->hasEffect(Effect::HEALTH_BOOST) ? 4 * ($this->getEffect(Effect::HEALTH_BOOST)->getAmplifier() + 1) : 0);
	}

	/**
	 * @param int $amount
	 */
	public function setMaxHealth($amount) {
		$this->maxHealth = (int)$amount;
	}

	public function canCollideWith(Entity $entity) {
		return !$this->justCreated and $entity !== $this;
	}

	/**
	 * @return Vector3
	 */
	public function getDirectionVector() {
		$y = -sin(deg2rad($this->pitch));
		$xz = cos(deg2rad($this->pitch));
		$x = -$xz * sin(deg2rad($this->yaw));
		$z = $xz * cos(deg2rad($this->yaw));

		return $this->temporalVector->setComponents($x, $y, $z)->normalize();
	}

	public function onUpdate($currentTick) {
		if($this->closed) {
			return false;
		}

		$tickDiff = max(1, $currentTick - $this->lastUpdate);
		$this->lastUpdate = $currentTick;

		$this->timings->startTiming();

		$hasUpdate = $this->entityBaseTick($tickDiff);

		$this->updateMovement();

		$this->timings->stopTiming();

		//if($this->isStatic())
		return $hasUpdate;
		//return !($this instanceof Player);
	}

	public function entityBaseTick($tickDiff = 1) {

		//Timings::$tickEntityTimer->startTiming();
		//TODO: check vehicles

		$this->justCreated = false;
		$isPlayer = $this instanceof Player;

		if($this->dead === true) {
			$this->removeAllEffects();
			$this->despawnFromAll();
			if(!$isPlayer) {
				$this->close();
			}

			//Timings::$tickEntityTimer->stopTiming();
			return false;
		}

		if(count($this->effects) > 0) {
			foreach($this->effects as $effect) {
				if($effect->canTick()) {
					$effect->applyEffect($this);
				}
				$effect->setDuration($effect->getDuration() - $tickDiff);
				if($effect->getDuration() <= 0) {
					$this->removeEffect($effect->getId());
				}
			}
		}

		$hasUpdate = false;
		if($block = $this->isCollideWithLiquid()) {
			$block->onEntityCollide($this);
		}
		if($block = $this->isCollideWithTransparent()) {
			$block->onEntityCollide($this);
		}

		if($this->y <= -16 and $this->dead !== true) {
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_VOID, 10);
			$this->attack($ev->getFinalDamage(), $ev);
			$hasUpdate = true;
		}

		if($this->fireTicks > 0) {
			if($this->fireProof) {
				$this->fireTicks -= 4 * $tickDiff;
				if($this->fireTicks < 0) {
					$this->fireTicks = 0;
				}
			} else {
				if(!$this->hasEffect(Effect::FIRE_RESISTANCE) and ($this->fireTicks % 20) === 0 or $tickDiff > 20) {
					$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FIRE_TICK, 1);
					$this->attack($ev->getFinalDamage(), $ev);
				}
				$this->fireTicks -= $tickDiff;
			}

			if($this->fireTicks <= 0) {
				$this->extinguish();
			} else {
				$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_ONFIRE, true);
				$hasUpdate = true;
			}
		}

		if($this->noDamageTicks > 0) {
			$this->noDamageTicks -= $tickDiff;
			if($this->noDamageTicks < 0) {
				$this->noDamageTicks = 0;
			}
		}

		$this->age += $tickDiff;
		$this->ticksLived += $tickDiff;

		//Timings::$tickEntityTimer->stopTiming();

		return $hasUpdate;
	}

	public function removeAllEffects() {
		foreach($this->effects as $effect) {
			$this->removeEffect($effect->getId());
		}
	}

	public function removeEffect($effectId) {
		if(isset($this->effects[$effectId])) {
			$effect = $this->effects[$effectId];
			unset($this->effects[$effectId]);
			$effect->remove($this);

			$this->recalculateEffectColor();
		}
	}

	public function close() {
		if(!$this->closed) {
			$this->server->getPluginManager()->callEvent(new EntityDespawnEvent($this));
			$this->closed = true;
			$this->despawnFromAll();
			if($this->chunk !== null) $this->chunk->removeEntity($this);
			if($this->level !== null) $this->level->removeEntity($this);
			if($this->attributeMap != null) $this->attributeMap = null;
		}
	}

	public function isCollideWithLiquid() {
		$block = $this->level->getBlock(new Vector3(Math::floorFloat($this->x), Math::floorFloat($y = $this->y), Math::floorFloat($this->z)));
		if(!($block instanceof Liquid)) {
			$block = $this->level->getBlock(new Vector3(Math::floorFloat($this->x), Math::floorFloat($y = ($this->y + $this->getEyeHeight())), Math::floorFloat($this->z)));
		}
		if(!($block instanceof Liquid)) {
			$block = $this->level->getBlock(new Vector3(Math::floorFloat($this->x + $this->width), Math::floorFloat($y), Math::floorFloat($this->z)));
		}
		if(!($block instanceof Liquid)) {
			$block = $this->level->getBlock(new Vector3(Math::floorFloat($this->x - $this->width), Math::floorFloat($y), Math::floorFloat($this->z)));
		}
		if(!($block instanceof Liquid)) {
			$block = $this->level->getBlock(new Vector3(Math::floorFloat($this->x), Math::floorFloat($y), Math::floorFloat($this->z + $this->width)));
		}
		if(!($block instanceof Liquid)) {
			$block = $this->level->getBlock(new Vector3(Math::floorFloat($this->x), Math::floorFloat($y), Math::floorFloat($this->z - $this->width)));
		}
		if($block instanceof Liquid) {
			$f = ($block->y + 1) - ($block->getFluidHeightPercent() - 0.1111111);

			return $y < $f ? $block : false;
		}

		return false;
	}

	public function isCollideWithTransparent() {
		$block = $this->level->getBlock($this->temporalVector->setComponents(Math::floorFloat($this->x), Math::floorFloat($y = $this->y), Math::floorFloat($this->z)));
		if(!($block instanceof Ladder) && !($block instanceof Fire)) {
			$block = $this->level->getBlock($this->temporalVector->setComponents(Math::floorFloat($this->x), Math::floorFloat($y = ($this->y + $this->getEyeHeight())), Math::floorFloat($this->z)));
		}
		if($block instanceof Ladder || $block instanceof Fire) {
			return $block;
		}

		return false;
	}

	/**
	 * @param float $damage
	 * @param EntityDamageEvent $source
	 *
	 * @return bool
	 */
	public function attack($damage, EntityDamageEvent $source) {
		if($this->hasEffect(Effect::FIRE_RESISTANCE) and $source->getCause() === EntityDamageEvent::CAUSE_FIRE and $source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK and $source->getCause() === EntityDamageEvent::CAUSE_LAVA) {
			$source->setCancelled();
		}

		$this->server->getPluginManager()->callEvent($source);
		if($source->isCancelled()) {
			return false;
		}

		$this->lastDamageTime = microtime(true);

		$this->setLastDamageCause($source);
		if($this instanceof Human) {
			$damage = round($source->getFinalDamage());
			if($this->getAbsorption() > 0) {
				$absorption = $this->getAbsorption() - $damage;
				$this->setAbsorption($absorption <= 0 ? 0 : $absorption);
				$this->setHealth($this->getHealth() + $absorption);
			} else {
				$this->setHealth($this->getHealth() - $damage);
			}
		} else {
			$this->setHealth($this->getHealth() - round($source->getFinalDamage()));
		}

		return true;
	}

	public function hasEffect($effectId) {
		return isset($this->effects[$effectId]);
	}

	public function extinguish() {
		$this->fireTicks = 0;
		$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_ONFIRE, false);
	}

	public function isOnFire() {
		return $this->fireTicks > 0;
	}

	public function setOnFire($seconds) {
		$ticks = $seconds * 20;
		if($ticks > $this->fireTicks) {
			$this->fireTicks = $ticks;
		}
	}

	public function getDirection() {
		$rotation = ($this->yaw - 90) % 360;
		if($rotation < 0) {
			$rotation += 360.0;
		}
		if((0 <= $rotation and $rotation < 45) or (315 <= $rotation and $rotation < 360)) {
			return 2; //North
		} elseif(45 <= $rotation and $rotation < 135) {
			return 3; //East
		} elseif(135 <= $rotation and $rotation < 225) {
			return 0; //South
		} elseif(225 <= $rotation and $rotation < 315) {
			return 1; //West
		} else {
			return null;
		}
	}

	public function canTriggerWalking() {
		return true;
	}

	public function handleLavaMovement() { //TODO

	}

	public function moveFlying() { //TODO

	}

	public function onCollideWithPlayer(Human $entityPlayer) {

	}

	public function getPosition() {
		return new Position($this->x, $this->y, $this->z, $this->level);
	}

	public function getLocation() {
		return new Location($this->x, $this->y, $this->z, $this->yaw, $this->pitch, $this->level);
	}

	public function isInsideOfWater() {
		$block = $this->level->getBlock($this->temporalVector->setComponents(Math::floorFloat($this->x), Math::floorFloat($y = ($this->y + $this->getEyeHeight())), Math::floorFloat($this->z)));

		if($block instanceof Water) {
			$f = ($block->y + 1) - ($block->getFluidHeightPercent() - 0.1111111);

			return $y < $f;
		}

		return false;
	}

	public function fastMove($dx, $dy, $dz) {
		if($dx == 0 and $dz == 0 and $dy == 0) {
			return true;
		}

		$newBB = $this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz);

		$list = $this->level->getCollisionCubes($this, $newBB, false);

		if(count($list) === 0) {
			$this->boundingBox = $newBB;
		}

		$this->x = ($this->boundingBox->minX + $this->boundingBox->maxX) / 2;
		$this->y = $this->boundingBox->minY - $this->ySize;
		$this->z = ($this->boundingBox->minZ + $this->boundingBox->maxZ) / 2;

		if(!($this instanceof Player)) {
			$this->checkChunks();
		}

		$bb = clone $this->boundingBox;
		$bb->minY -= 0.75;
		$this->onGround = false;

		if(count($this->level->getCollisionBlocks($bb)) > 0) {
			$this->onGround = true;
		} else {
			$this->onGround = false;
		}

		$this->isCollided = $this->onGround;

		$notInAir = $this->onGround || $this->isCollideWithWater();
		$this->updateFallState($dy, $notInAir);

		return true;
	}

	protected function checkChunks() {
		if($this->chunk === null or ($this->chunk->getX() !== ($this->x >> 4) or $this->chunk->getZ() !== ($this->z >> 4))) {
			if($this->chunk !== null) {
				$this->chunk->removeEntity($this);
			}
			$this->chunk = $this->level->getChunk($this->x >> 4, $this->z >> 4, true);

			if(!$this->justCreated) {
				$newChunk = $this->level->getUsingChunk($this->x >> 4, $this->z >> 4);
				foreach($this->hasSpawned as $player) {
					if(!isset($newChunk[$player->getId()])) {
						$this->despawnFrom($player);
					} else {
						unset($newChunk[$player->getId()]);
					}
				}
				foreach($newChunk as $player) {
					$this->spawnTo($player);
				}
			}

			if($this->chunk === null) {
				return;
			}

			$this->chunk->addEntity($this);
		}
	}

	/**
	 * @param Player $player
	 */
	public function spawnTo(Player $player) {
		if(!isset($this->hasSpawned[$player->getId()]) and isset($player->usedChunks[PHP_INT_SIZE === 8 ? ((($this->chunk->getX()) & 0xFFFFFFFF) << 32) | (($this->chunk->getZ()) & 0xFFFFFFFF) : ($this->chunk->getX()) . ":" . ($this->chunk->getZ())])) {
			$this->hasSpawned[$player->getId()] = $player;
		}
	}

	public function isInsideOfSolid() {
		$block = $this->level->getBlock($this->temporalVector->setComponents(Math::floorFloat($this->x), Math::floorFloat($y = ($this->y + $this->getEyeHeight())), Math::floorFloat($this->z)));

		$bb = $block->getBoundingBox();

		if($bb !== null and $block->isSolid() and !$block->isTransparent() and $bb->intersectsWith($this->getBoundingBox())) {
			return true;
		}

		return false;
	}

	public function getBoundingBox() {
		return $this->boundingBox;
	}

	protected function updateFallState($distanceThisTick, $onGround) {
		if($onGround === true) {
			if($this->fallDistance > 0) {
				if($this instanceof Living) {
					if(!$this->isCollideWithWater()) {
							$this->fall($this->fallDistance);
					}
				}
				$this->resetFallDistance();
			}
		} elseif($distanceThisTick < 0) {
			$this->fallDistance -= $distanceThisTick;
		}
	}

	public function isCollideWithWater() {
		// checking block under feet
		$block = $this->level->getBlock($this->temporalVector->setComponents(Math::floorFloat($this->x), Math::floorFloat($y = $this->y), Math::floorFloat($this->z)));
		if(!($block instanceof Water)) {
			$block = $this->level->getBlock($this->temporalVector->setComponents(Math::floorFloat($this->x), Math::floorFloat($y = ($this->y + $this->getEyeHeight())), Math::floorFloat($this->z)));
		}
		if($block instanceof Water) {
			$f = ($block->y + 1) - ($block->getFluidHeightPercent() - 0.1111111);

			return $y < $f;
		}

		return false;
	}

	public function fall($fallDistance) {
		$damage = floor($fallDistance - 3 - ($this->hasEffect(Effect::JUMP) ? $this->getEffect(Effect::JUMP)->getAmplifier() + 1 : 0));
		if($damage > 0) {
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FALL, $damage);
			$this->attack($ev->getFinalDamage(), $ev);
		}
	}

	public function resetFallDistance() {
		$this->fallDistance = 0;
	}

	public function move($dx, $dy, $dz) {

		if($dx == 0 and $dz == 0 and $dy == 0) {
			return true;
		}

		if($this->keepMovement) {
			$this->boundingBox->offset($dx, $dy, $dz);
			$this->setPosition($this->temporalVector->setComponents(($this->boundingBox->minX + $this->boundingBox->maxX) / 2, $this->boundingBox->minY, ($this->boundingBox->minZ + $this->boundingBox->maxZ) / 2));
			$this->onGround = $this instanceof Player ? true : false;

			return true;
		} else {

			$this->ySize *= 0.4;
			$movX = $dx;
			$movY = $dy;
			$movZ = $dz;

			$axisalignedbb = clone $this->boundingBox;

			$list = $this->level->getCollisionCubes($this, $this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz));


			foreach($list as $bb) {
				$dy = $bb->calculateYOffset($this->boundingBox, $dy);
			}

			$this->boundingBox->offset(0, $dy, 0);

			if($movY != $dy) {
				$dx = 0;
				$dy = 0;
				$dz = 0;
			}

			$fallingFlag = ($this->onGround or ($dy != $movY and $movY < 0));

			foreach($list as $bb) {
				$dx = $bb->calculateXOffset($this->boundingBox, $dx);
			}

			$this->boundingBox->offset($dx, 0, 0);

			if($movX != $dx) {
				$dx = 0;
				$dy = 0;
				$dz = 0;
			}

			foreach($list as $bb) {
				$dz = $bb->calculateZOffset($this->boundingBox, $dz);
			}

			$this->boundingBox->offset(0, 0, $dz);

			if($movZ != $dz) {
				$dx = 0;
				$dy = 0;
				$dz = 0;
			}


			if($this->stepHeight > 0 and $fallingFlag and $this->ySize < 0.05 and ($movX != $dx or $movZ != $dz)) {
				$cx = $dx;
				$cy = $dy;
				$cz = $dz;
				$dx = $movX;
				$dy = $this->stepHeight;
				$dz = $movZ;

				$axisalignedbb1 = clone $this->boundingBox;

				$this->boundingBox->setBB($axisalignedbb);

				$list = $this->level->getCollisionCubes($this, $this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz), false);

				foreach($list as $bb) {
					$dy = $bb->calculateYOffset($this->boundingBox, $dy);
				}

				$this->boundingBox->offset(0, $dy, 0);

				foreach($list as $bb) {
					$dx = $bb->calculateXOffset($this->boundingBox, $dx);
				}

				$this->boundingBox->offset($dx, 0, 0);
				if($movX != $dx) {
					$dx = 0;
					$dy = 0;
					$dz = 0;
				}

				foreach($list as $bb) {
					$dz = $bb->calculateZOffset($this->boundingBox, $dz);
				}

				$this->boundingBox->offset(0, 0, $dz);
				if($movZ != $dz) {
					$dx = 0;
					$dy = 0;
					$dz = 0;
				}

				if($dy == 0) {
					$dx = 0;
					$dy = 0;
					$dz = 0;
				} else {
					$dy = -$this->stepHeight;
					foreach($list as $bb) {
						$dy = $bb->calculateYOffset($this->boundingBox, $dy);
					}
					$this->boundingBox->offset(0, $dy, 0);
				}

				if(($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)) {
					$dx = $cx;
					$dy = $cy;
					$dz = $cz;
					$this->boundingBox->setBB($axisalignedbb1);
				} else {
					$diff = $this->boundingBox->minY - (int)$this->boundingBox->minY;

					if($diff > 0) {
						$this->ySize += $diff + 0.01;
					}
				}

			}

			$pos = $this->temporalVector->setComponents(($this->boundingBox->minX + $this->boundingBox->maxX) / 2, $this->boundingBox->minY + $this->ySize, ($this->boundingBox->minZ + $this->boundingBox->maxZ) / 2);

			$result = true;

			if(!$this->setPosition($pos)) {
				$this->boundingBox->setBB($axisalignedbb);
				$result = false;
			} else {

				if($this instanceof Player) {
					if(!$this->onGround or $movY != 0) {
						$bb = clone $this->boundingBox;
						$bb->maxY = $bb->minY + 0.5;
						$bb->minY -= 1;
						if(count($this->level->getCollisionBlocks($bb)) > 0) {
							$this->onGround = true;
						} else {
							$this->onGround = false;
						}
					}
					$this->isCollided = $this->onGround;
				} else {
					$this->isCollidedVertically = $movY != $dy;
					$this->isCollidedHorizontally = ($movX != $dx or $movZ != $dz);
					$this->isCollided = ($this->isCollidedHorizontally or $this->isCollidedVertically);
					$this->onGround = ($movY != $dy and $movY < 0);
				}
				$notInAir = $this->onGround || $this->isCollideWithWater();
				$this->updateFallState($dy, $notInAir);

				if($movX != $dx) {
					$this->motionX = 0;
				}

				if($movY != $dy) {
					$this->motionY = 0;
				}

				if($movZ != $dz) {
					$this->motionZ = 0;
				}
			}

			return $result;
		}
	}

	public function isOnGround() {
		return $this->onGround;
	}

	public function kill() {
		if($this->dead) {
			return;
		}
		$this->dead = true;
		$this->setHealth(0);
		$this->scheduleUpdate();
	}

	/**
	 * @param Vector3|Position|Location $pos
	 * @param float $yaw
	 * @param float $pitch
	 *
	 * @return bool
	 */
	public function teleport(Vector3 $pos, $yaw = null, $pitch = null) {
		if($pos instanceof Location) {
			$yaw = $yaw === null ? $pos->yaw : $yaw;
			$pitch = $pitch === null ? $pos->pitch : $pitch;
		}
		$from = Position::fromObject($this, $this->level);
		$to = Position::fromObject($pos, $pos instanceof Position ? $pos->getLevel() : $this->level);
		$this->server->getPluginManager()->callEvent($ev = new EntityTeleportEvent($this, $from, $to));
		if($ev->isCancelled()) {
			return false;
		}
		$this->ySize = 0;
		$pos = $ev->getTo();

		$this->setMotion($this->temporalVector->setComponents(0, 0, 0));
		if($this->setPositionAndRotation($pos, $yaw === null ? $this->yaw : $yaw, $pitch === null ? $this->pitch : $pitch) !== false) {
			$this->resetFallDistance();
			$this->onGround = true;

			$this->lastX = $this->x;
			$this->lastY = $this->y;
			$this->lastZ = $this->z;

			$this->lastYaw = $this->yaw;
			$this->lastPitch = $this->pitch;

			$this->updateMovement();

			return true;
		}

		return false;
	}

	public function respawnToAll() {
		foreach($this->hasSpawned as $key => $player) {
			$this->respawnTo($player);
		}
	}

	public function respawnTo(Player $player) {
		$this->despawnFrom($player);
		$this->spawnTo($player);
	}

	public function spawnToAll() {
		if($this->chunk === null or $this->closed) {
			return false;
		}
		foreach($this->level->getUsingChunk($this->chunk->getX(), $this->chunk->getZ()) as $player) {
			if($player->loggedIn === true) {
				$this->spawnTo($player);
			}
		}
	}

	/**
	 * @param int $id
	 *
	 * @return int
	 */
	public function getDataPropertyType($id) {
		return isset($this->dataProperties[$id]) ? $this->dataProperties[$id][0] : null;
	}

	public function __destruct() {
		$this->close();
	}

	public function setMetadata($metadataKey, MetadataValue $metadataValue) {
		$this->server->getEntityMetadata()->setMetadata($this, $metadataKey, $metadataValue);
	}

	public function getMetadata($metadataKey) {
		return $this->server->getEntityMetadata()->getMetadata($this, $metadataKey);
	}

	public function hasMetadata($metadataKey) {
		return $this->server->getEntityMetadata()->hasMetadata($this, $metadataKey);
	}

	public function removeMetadata($metadataKey, Plugin $plugin) {
		$this->server->getEntityMetadata()->removeMetadata($this, $metadataKey, $plugin);
	}

	public function __toString() {
		return (new \ReflectionClass($this))->getShortName() . "(" . $this->getId() . ")";
	}

	public function getBlocksAround() {
		if($this->blocksAround === null) {
			$minX = Math::floorFloat($this->boundingBox->minX);
			$minY = Math::floorFloat($this->boundingBox->minY);
			$minZ = Math::floorFloat($this->boundingBox->minZ);
			$maxX = Math::ceilFloat($this->boundingBox->maxX);
			$maxY = Math::ceilFloat($this->boundingBox->maxY);
			$maxZ = Math::ceilFloat($this->boundingBox->maxZ);

			$this->blocksAround = [];

			for($z = $minZ; $z <= $maxZ; ++$z) {
				for($x = $minX; $x <= $maxX; ++$x) {
					for($y = $minY; $y <= $maxY; ++$y) {
						$block = $this->level->getBlock($this->temporalVector->setComponents($x, $y, $z));
						if($block->hasEntityCollision()) {
							$this->blocksAround[Level::blockHash($block->x, $block->y, $block->z)] = $block;
						}
					}
				}
			}
		}

		return $this->blocksAround;
	}

	protected function checkObstruction($x, $y, $z) {
		$i = Math::floorFloat($x);
		$j = Math::floorFloat($y);
		$k = Math::floorFloat($z);

		$diffX = $x - $i;
		$diffY = $y - $j;
		$diffZ = $z - $k;

		if(Block::$solid[$this->level->getBlockIdAt($i, $j, $k)]) {
			$flag = !Block::$solid[$this->level->getBlockIdAt($i - 1, $j, $k)];
			$flag1 = !Block::$solid[$this->level->getBlockIdAt($i + 1, $j, $k)];
			$flag2 = !Block::$solid[$this->level->getBlockIdAt($i, $j - 1, $k)];
			$flag3 = !Block::$solid[$this->level->getBlockIdAt($i, $j + 1, $k)];
			$flag4 = !Block::$solid[$this->level->getBlockIdAt($i, $j, $k - 1)];
			$flag5 = !Block::$solid[$this->level->getBlockIdAt($i, $j, $k + 1)];

			$direction = -1;
			$limit = 9999;

			if($flag) {
				$limit = $diffX;
				$direction = 0;
			}

			if($flag1 and 1 - $diffX < $limit) {
				$limit = 1 - $diffX;
				$direction = 1;
			}

			if($flag2 and $diffY < $limit) {
				$limit = $diffY;
				$direction = 2;
			}

			if($flag3 and 1 - $diffY < $limit) {
				$limit = 1 - $diffY;
				$direction = 3;
			}

			if($flag4 and $diffZ < $limit) {
				$limit = $diffZ;
				$direction = 4;
			}

			if($flag5 and 1 - $diffZ < $limit) {
				$direction = 5;
			}

			$force = lcg_value() * 0.2 + 0.1;

			if($direction === 0) {
				$this->motionX = -$force;

				return true;
			}

			if($direction === 1) {
				$this->motionX = $force;

				return true;
			}

			//No direction 2

			if($direction === 3) {
				$this->motionY = $force;

				return true;
			}

			if($direction === 4) {
				$this->motionZ = -$force;

				return true;
			}

			if($direction === 5) {
				$this->motionZ = $force;

				return true;
			}
		}

		return false;
	}

}
