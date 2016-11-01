<?php

namespace pocketmine;

use pocketmine\level\format\mcregion\Chunk;
use pocketmine\nbt\NBT;
use pocketmine\network\protocol\FullChunkDataPacket;
use pocketmine\tile\Spawnable;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;

class ChunkMaker extends Worker {

	/** @var \ClassLoader */
	protected $classLoader;

	/** @var bool */
	protected $shutdown = false;

	/** @var \Threaded */
	protected $externalQueue;

	/** @var \Threaded */
	protected $internalQueue;

	/** @var int */
	protected $compressionLevel;

	public function __construct(\ClassLoader $loader = null, $compressionLevel = 7) {
		$this->classLoader = $loader;
		$this->externalQueue = new \Threaded;
		$this->internalQueue = new \Threaded;
		$this->compressionLevel = $compressionLevel;
		$this->start(PTHREADS_INHERIT_CONSTANTS);
	}

	public function run() {
		$this->registerClassLoader();
		gc_enable();
		ini_set("memory_limit", -1);
		ini_set("display_errors", 1);
		ini_set("display_startup_errors", 1);

		set_error_handler([$this, "errorHandler"], E_ALL);
		$this->tickProcessor();
	}

	public function registerClassLoader() {
		if(!interface_exists("ClassLoader", false)) {
			require(\pocketmine\PATH . "src/spl/ClassLoader.php");
			require(\pocketmine\PATH . "src/spl/BaseClassLoader.php");
			require(\pocketmine\PATH . "src/pocketmine/CompatibleClassLoader.php");
		}
		if($this->classLoader !== null) {
			$this->classLoader->register(true);
		}
	}

	protected function tickProcessor() {
		while(!$this->shutdown) {
			$start = microtime(true);
			$count = count($this->internalQueue);
			$this->tick();
			$time = microtime(true) - $start;
			if($time < 0.025) {
				time_sleep_until(microtime(true) + 0.025 - $time);
			}
		}
	}

	protected function tick() {
		while(count($this->internalQueue) > 0) {
			$data = $this->readMainToThreadPacket();
			$this->doChunk($data);
		}
	}

	public function readMainToThreadPacket() {
		return $this->internalQueue->shift();
	}

	protected function doChunk($data) {
		$chunk = Chunk::fromFastBinary($data);

		$tiles = "";
		if(count($rawTiles = $chunk->getTiles()) > 0) {
			$nbt = new NBT(NBT::LITTLE_ENDIAN);
			$list = [];
			foreach($rawTiles as $tile) {
				if($tile instanceof Spawnable) {
					$list[] = $tile->getSpawnCompound();
				}
			}
			$nbt->setData($list);
			$tiles = $nbt->write(true);
		}

		$extraData = new BinaryStream();
		$extraData->putLInt(count($dataArray = $chunk->getBlockExtraDataArray()));
		foreach($dataArray as $key => $value) {
			$extraData->putLInt($key);
			$extraData->putLShort($value);
		}

		$ordered = $chunk->getBlockIdArray() . $chunk->getBlockDataArray() . $chunk->getBlockSkyLightArray() . $chunk->getBlockLightArray() . pack("C*", ...$chunk->getHeightMapArray()) . pack("N*", ...$chunk->getBiomeColorArray()) . $extraData->getBuffer() . $tiles;

		$result = [];

		$pk = new FullChunkDataPacket();
		$pk->chunkX = $result["chunkX"] = $chunk->getX();
		$pk->chunkZ = $result["chunkZ"] = $chunk->getZ();
		$pk->order = FullChunkDataPacket::ORDER_COLUMNS;
		$pk->data = $ordered;
		$pk->encode();
		if(!empty($pk->buffer)) {
			$result["payload"] = zlib_encode(Binary::writeUnsignedVarInt(strlen($pk->buffer)) . $pk->buffer, ZLIB_ENCODING_DEFLATE, $this->compressionLevel);
		}

		$this->externalQueue[] = serialize($result);
	}

	public function pushMainToThreadPacket($data) {
		$this->internalQueue[] = $data;
	}

	public function readThreadToMainPacket() {
		return $this->externalQueue->shift();
	}

	public function shutdown() {
		$this->shutdown = true;
	}


	public function errorHandler($errno, $errstr, $errfile, $errline, $context, $trace = null) {
		$errorConversion = [E_ERROR => "E_ERROR", E_WARNING => "E_WARNING", E_PARSE => "E_PARSE", E_NOTICE => "E_NOTICE", E_CORE_ERROR => "E_CORE_ERROR", E_CORE_WARNING => "E_CORE_WARNING", E_COMPILE_ERROR => "E_COMPILE_ERROR", E_COMPILE_WARNING => "E_COMPILE_WARNING", E_USER_ERROR => "E_USER_ERROR", E_USER_WARNING => "E_USER_WARNING", E_USER_NOTICE => "E_USER_NOTICE", E_STRICT => "E_STRICT", E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR", E_DEPRECATED => "E_DEPRECATED", E_USER_DEPRECATED => "E_USER_DEPRECATED",];
		$errno = isset($errorConversion[$errno]) ? $errorConversion[$errno] : $errno;
		if(($pos = strpos($errstr, "\n")) !== false) {
			$errstr = substr($errstr, 0, $pos);
		}

		var_dump("An $errno error happened: \"$errstr\" in \"$errfile\" at line $errline");

		foreach(($trace = $this->getTrace($trace === null ? 3 : 0, $trace)) as $i => $line) {
			var_dump($line);
		}

		return true;
	}


	public function getTrace($start = 1, $trace = null) {
		if($trace === null) {
			if(function_exists("xdebug_get_function_stack")) {
				$trace = array_reverse(xdebug_get_function_stack());
			} else {
				$e = new \Exception();
				$trace = $e->getTrace();
			}
		}

		$messages = [];
		$j = 0;
		for($i = (int)$start; isset($trace[$i]); ++$i, ++$j) {
			$params = "";
			if(isset($trace[$i]["args"]) or isset($trace[$i]["params"])) {
				if(isset($trace[$i]["args"])) {
					$args = $trace[$i]["args"];
				} else {
					$args = $trace[$i]["params"];
				}
				foreach($args as $name => $value) {
					$params .= (is_object($value) ? get_class($value) . " " . (method_exists($value, "__toString") ? $value->__toString() : "object") : gettype($value) . " " . @strval($value)) . ", ";
				}
			}
			$messages[] = "#$j " . (isset($trace[$i]["file"]) ? ($trace[$i]["file"]) : "") . "(" . (isset($trace[$i]["line"]) ? $trace[$i]["line"] : "") . "): " . (isset($trace[$i]["class"]) ? $trace[$i]["class"] . (($trace[$i]["type"] === "dynamic" or $trace[$i]["type"] === "->") ? "->" : "::") : "") . $trace[$i]["function"] . "(" . substr($params, 0, -2) . ")";
		}

		return $messages;
	}

}