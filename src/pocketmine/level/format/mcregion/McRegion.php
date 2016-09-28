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

namespace pocketmine\level\format\mcregion;

use pocketmine\level\format\FullChunk;
use pocketmine\level\format\generic\BaseLevelProvider;
use pocketmine\level\Level;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\tile\Sign;
use pocketmine\tile\Spawnable;
use pocketmine\tile\Tile;
use pocketmine\utils\ChunkException;

class McRegion extends BaseLevelProvider {

	/** @var RegionLoader[] */
	protected $regions = [];

	/** @var Chunk[] */
	protected $chunks = [];

	public static function getProviderName() {
		return "mcregion";
	}

	public static function getProviderOrder() {
		return self::ORDER_ZXY;
	}

	public static function usesChunkSection() {
		return false;
	}

	public static function isValid($path) {
		$isValid = (file_exists($path . "/level.dat") and is_dir($path . "/region/"));

		if($isValid) {
			$files = glob($path . "/region/*.mc*");
			foreach($files as $f) {
				if(strpos($f, ".mca") !== false) { //Anvil
					$isValid = false;
					break;
				}
			}
		}

		return $isValid;
	}

	public static function generate($path, $name, $seed, array $options = []) {
		if(!file_exists($path)) {
			mkdir($path, 0777, true);
		}

		if(!file_exists($path . "/region")) {
			mkdir($path . "/region", 0777);
		}
		//TODO, add extra details
		$levelData = new CompoundTag("Data", ["hardcore" => new ByteTag("hardcore", 0), "initialized" => new ByteTag("initialized", 1), "GameType" => new IntTag("GameType", 0), "generatorVersion" => new IntTag("generatorVersion", 1), //2 in MCPE
			"SpawnX" => new IntTag("SpawnX", 0), "SpawnY" => new IntTag("SpawnY", 10), "SpawnZ" => new IntTag("SpawnZ", 0), "version" => new IntTag("version", 19133), "DayTime" => new IntTag("DayTime", 0), "LastPlayed" => new LongTag("LastPlayed", microtime(true) * 1000), "RandomSeed" => new LongTag("RandomSeed", $seed), "SizeOnDisk" => new LongTag("SizeOnDisk", 0), "Time" => new LongTag("Time", 0), "generatorName" => new StringTag("generatorName", "FLAT"), "generatorOptions" => new StringTag("generatorOptions", isset($options["preset"]) ? $options["preset"] : ""), "LevelName" => new StringTag("LevelName", $name), "GameRules" => new CompoundTag("GameRules", [])]);
		$nbt = new NBT(NBT::BIG_ENDIAN);
		$nbt->setData(new CompoundTag("", ["Data" => $levelData]));
		$buffer = $nbt->writeCompressed();
		file_put_contents($path . "level.dat", $buffer);
	}

	public static function createChunkSection($Y) {
		return null;
	}

	public function requestChunkTask($x, $z) {
		$chunk = $this->getChunk($x, $z, false);
		if(!($chunk instanceof Chunk)) {
			throw new ChunkException("Invalid Chunk sent");
		}

		$signTiles = [];
		$translation = $this->getServer()->getSignTranslation();
		foreach($translation as $lang => $data) {
			$signTiles[$lang] = "";
		}

		$tiles = "";
		$nbt = new NBT(NBT::LITTLE_ENDIAN);
		foreach($chunk->getTiles() as $tile) {
			if($tile instanceof Sign) {
				foreach($translation as $lang => $data) {
					$nbt->setData($this->getSignSpawnCompound($tile, $data));
					$signTiles[$lang] .= $nbt->write();
				}

				continue;
			}
			if($tile instanceof Spawnable) {
				$nbt->setData($tile->getSpawnCompound());
				$tiles .= $nbt->write();
			}
		}

		$data = [];
		$data['chunkX'] = $x;
		$data['chunkZ'] = $z;
		$data['tiles'] = $tiles;
		$data['signTiles'] = $signTiles;
		$data['chunk'] = $chunk->toFastBinary();

		$this->getLevel()->chunkMaker->pushMainToThreadPacket(serialize($data));

		return null;
	}

	/**
	 * @param int $chunkX
	 * @param int $chunkZ
	 * @param bool $create
	 *
	 * @return Chunk
	 */
	public function getChunk($chunkX, $chunkZ, $create = false) {
		$index = Level::chunkHash($chunkX, $chunkZ);
		if(isset($this->chunks[$index])) {
			return $this->chunks[$index];
		} else {
			$this->loadChunk($chunkX, $chunkZ, $create);

			return isset($this->chunks[$index]) ? $this->chunks[$index] : null;
		}
	}

	public function loadChunk($chunkX, $chunkZ, $create = false) {
		$index = Level::chunkHash($chunkX, $chunkZ);
		if(isset($this->chunks[$index])) {
			return true;
		}
		$regionX = $regionZ = null;
		self::getRegionIndex($chunkX, $chunkZ, $regionX, $regionZ);
		$this->loadRegion($regionX, $regionZ);
		$this->level->timings->syncChunkLoadDataTimer->startTiming();
		$chunk = $this->getRegion($regionX, $regionZ)->readChunk($chunkX - $regionX * 32, $chunkZ - $regionZ * 32);
		if($chunk === null and $create) {
			$chunk = $this->getEmptyChunk($chunkX, $chunkZ);
		}
		$this->level->timings->syncChunkLoadDataTimer->stopTiming();

		if($chunk !== null) {
			$this->chunks[$index] = $chunk;

			return true;
		} else {
			return false;
		}
	}

	public static function getRegionIndex($chunkX, $chunkZ, &$x, &$z) {
		$x = $chunkX >> 5;
		$z = $chunkZ >> 5;
	}

	protected function loadRegion($x, $z) {
		if(!isset($this->regions[$index = Level::chunkHash($x, $z)])) {
			$this->regions[$index] = new RegionLoader($this, $x, $z);
		}
	}

	/**
	 * @param $x
	 * @param $z
	 *
	 * @return RegionLoader
	 */
	protected function getRegion($x, $z) {
		return isset($this->regions[$index = Level::chunkHash($x, $z)]) ? $this->regions[$index] : null;
	}

	public function getEmptyChunk($chunkX, $chunkZ) {
		return Chunk::getEmptyChunk($chunkX, $chunkZ, $this);
	}

	private function getSignSpawnCompound($sign, $lang) {
		return new CompoundTag("", [new StringTag("id", Tile::SIGN), new StringTag("Text1", $this->updateSignText($sign->namedtag['Text1'], $lang)), new StringTag("Text2", $this->updateSignText($sign->namedtag['Text2'], $lang)), new StringTag("Text3", $this->updateSignText($sign->namedtag['Text3'], $lang)), new StringTag("Text4", $this->updateSignText($sign->namedtag['Text4'], $lang)), new IntTag("x", (int)$sign->x), new IntTag("y", (int)$sign->y), new IntTag("z", (int)$sign->z)]);
	}

	private function updateSignText($text, $lang) {
		if(empty($text)) {
			return "";
		}

		return str_replace($lang['key'], $lang['val'], $text);

	}

	public function getLoadedChunks() {
		return $this->chunks;
	}

	public function saveChunks() {
		foreach($this->chunks as $chunk) {
			$this->saveChunk($chunk->getX(), $chunk->getZ());
		}
	}

	public function saveChunk($x, $z) {
		if($this->isChunkLoaded($x, $z)) {
			$this->getRegion($x >> 5, $z >> 5)->writeChunk($this->getChunk($x, $z));

			return true;
		}

		return false;
	}

	public function isChunkLoaded($x, $z) {
		return isset($this->chunks[Level::chunkHash($x, $z)]);
	}

	public function doGarbageCollection() {
		$limit = time() - 300;
		foreach($this->regions as $index => $region) {
			if($region->lastUsed <= $limit) {
				$region->close();
				unset($this->regions[$index]);
			}
		}
	}

	public function getGenerator() {
		return $this->levelData["generatorName"];
	}

	public function getGeneratorOptions() {
		return ["preset" => $this->levelData["generatorOptions"]];
	}

	public function setChunk($chunkX, $chunkZ, FullChunk $chunk) {
		if(!($chunk instanceof Chunk)) {
			throw new ChunkException("Invalid Chunk class");
		}

		$chunk->setProvider($this);

		self::getRegionIndex($chunkX, $chunkZ, $regionX, $regionZ);
		$this->loadRegion($regionX, $regionZ);

		$chunk->setX($chunkX);
		$chunk->setZ($chunkZ);


		if(isset($this->chunks[$index = Level::chunkHash($chunkX, $chunkZ)]) and $this->chunks[$index] !== $chunk) {
			$this->unloadChunk($chunkX, $chunkZ, false);
		}

		$this->chunks[$index] = $chunk;
	}

	public function unloadChunk($x, $z, $safe = true) {
		$chunk = isset($this->chunks[$index = Level::chunkHash($x, $z)]) ? $this->chunks[$index] : null;
		if($chunk instanceof FullChunk and $chunk->unload(false, $safe)) {
			unset($this->chunks[$index]);

			return true;
		}

		return false;
	}

	public function isChunkGenerated($chunkX, $chunkZ) {
		if(($region = $this->getRegion($chunkX >> 5, $chunkZ >> 5)) !== null) {
			return $region->chunkExists($chunkX - $region->getX() * 32, $chunkZ - $region->getZ() * 32) and $this->getChunk($chunkX - $region->getX() * 32, $chunkZ - $region->getZ() * 32, true)->isGenerated();
		}

		return false;
	}

	public function isChunkPopulated($chunkX, $chunkZ) {
		$chunk = $this->getChunk($chunkX, $chunkZ);
		if($chunk !== null) {
			return $chunk->isPopulated();
		} else {
			return false;
		}
	}

	public function close() {
		$this->unloadChunks();
		foreach($this->regions as $index => $region) {
			$region->close();
			unset($this->regions[$index]);
		}
		$this->level = null;
	}

	public function unloadChunks() {
		foreach($this->chunks as $chunk) {
			$this->unloadChunk($chunk->getX(), $chunk->getZ(), false);
		}
		$this->chunks = [];
	}
}