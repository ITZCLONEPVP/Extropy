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

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

class DumpMemoryCommand extends VanillaCommand {

	public function __construct($name) {
		parent::__construct($name, "Dumps the memory", "/$name [path]");
		$this->setPermission("pocketmine.command.dumpmemory");
	}

	public function execute(CommandSender $sender, $currentAlias, array $args) {
		if(!$this->testPermission($sender)) {
			return true;
		}

		$sender->sendMessage("Dumping server memory...");

		$this->dumpServerMemory(isset($args[0]) ? $args[0] : $sender->getServer()->getDataPath() . "/memory_dumps/memoryDump_" . date("D_M_j-H.i.s-T_Y", time()), 48, 80);

		return true;
	}

	public function dumpServerMemory($outputFolder, $maxNesting, $maxStringSize) {
		gc_disable();
		ini_set("memory_limit", -1);
		if(!file_exists($outputFolder)) {
			mkdir($outputFolder, 0777, true);
		}

		$server = Server::getInstance();

		$server->getLogger()->notice("[Dump] After the memory dump is done, the server will shut down");

		$obData = fopen($outputFolder . "/objects.json", "wb+");

		$staticProperties = [];

		$data = [];

		$objects = [];

		$refCounts = [];

		$this->continueDump($server, $data, $objects, $refCounts, 0, $maxNesting, $maxStringSize);

		do {
			$continue = false;
			foreach($objects as $hash => $object) {
				if(!is_object($object)) {
					continue;
				}
				$continue = true;

				$className = get_class($object);

				$objects[$hash] = true;

				$reflection = new \ReflectionObject($object);

				$info = ["information" => "$hash@$className", "properties" => []];

				if($reflection->getParentClass()) {
					$info["parent"] = $reflection->getParentClass()->getName();
				}

				if(count($reflection->getInterfaceNames()) > 0) {
					$info["implements"] = implode(", ", $reflection->getInterfaceNames());
				}

				foreach($reflection->getProperties() as $property) {
					if($property->isStatic()) {
						continue;
					}

					if(!$property->isPublic()) {
						$property->setAccessible(true);
					}
					$this->continueDump($property->getValue($object), $info["properties"][$property->getName()], $objects, $refCounts, 0, $maxNesting, $maxStringSize);
				}

				fwrite($obData, "$hash@$className: " . json_encode($info, JSON_UNESCAPED_SLASHES) . "\n");

				if(!isset($objects["staticProperties"][$className])) {
					$staticProperties[$className] = [];
					foreach($reflection->getProperties() as $property) {
						if(!$property->isStatic() or $property->getDeclaringClass()->getName() !== $className) {
							continue;
						}

						if(!$property->isPublic()) {
							$property->setAccessible(true);
						}
						$this->continueDump($property->getValue($object), $staticProperties[$className][$property->getName()], $objects, $refCounts, 0, $maxNesting, $maxStringSize);
					}
				}
			}

			$server->getLogger()->notice(TextFormat::GOLD . "[Dump] Wrote " . count($objects) . " objects");
		} while($continue);

		file_put_contents($outputFolder . "/staticProperties.json", json_encode($staticProperties, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		file_put_contents($outputFolder . "/serverEntry.json", json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		file_put_contents($outputFolder . "/referenceCounts.json", json_encode($refCounts, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

		$server->getLogger()->notice(TextFormat::GOLD . "[Dump] Finished!");

		gc_enable();

		$server->forceShutdown();
	}

	private function continueDump($from, &$data, &$objects, &$refCounts, $recursion, $maxNesting, $maxStringSize) {
		if($maxNesting <= 0) {
			$data = "(error) NESTING LIMIT REACHED";

			return;
		}

		--$maxNesting;

		if(is_object($from)) {
			if(!isset($objects[$hash = spl_object_hash($from)])) {
				$objects[$hash] = $from;
				$refCounts[$hash] = 0;
			}

			++$refCounts[$hash];

			$data = "(object) $hash@" . get_class($from);
		} elseif(is_array($from)) {
			if($recursion >= 5) {
				$data = "(error) ARRAY RECURSION LIMIT REACHED";

				return;
			}
			$data = [];
			foreach($from as $key => $value) {
				$this->continueDump($value, $data[$key], $objects, $refCounts, $recursion + 1, $maxNesting, $maxStringSize);
			}
		} elseif(is_string($from)) {
			$data = "(string) len(" . strlen($from) . ") " . substr(Utils::printable($from), 0, $maxStringSize);
		} elseif(is_resource($from)) {
			$data = "(resource) " . print_r($from, true);
		} else {
			$data = $from;
		}
	}

}
