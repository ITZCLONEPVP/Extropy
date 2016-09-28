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

namespace pocketmine\command;

use pocketmine\permission\PermissibleBase;
use pocketmine\permission\PermissionAttachment;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\MainLogger;

class ConsoleCommandSender implements CommandSender {

	private $perm;

	public function __construct() {
		$this->perm = new PermissibleBase($this);
	}

	/**
	 * @param \pocketmine\permission\Permission|string $name
	 *
	 * @return bool
	 */
	public function isPermissionSet($name) : bool {
		return $this->perm->isPermissionSet($name);
	}

	/**
	 * @param \pocketmine\permission\Permission|string $name
	 *
	 * @return bool
	 */
	public function hasPermission($name) {
		return $this->perm->hasPermission($name);
	}

	public function addAttachment(Plugin $plugin, string $name = null, bool $value = null) : PermissionAttachment {
		return $this->perm->addAttachment($plugin, $name, $value);
	}

	/**
	 * @param PermissionAttachment $attachment
	 *
	 * @return void
	 */
	public function removeAttachment(PermissionAttachment $attachment) {
		$this->perm->removeAttachment($attachment);
	}

	public function recalculatePermissions() {
		$this->perm->recalculatePermissions();
	}

	/**
	 * @return \pocketmine\permission\PermissionAttachmentInfo[]
	 */
	public function getEffectivePermissions() {
		return $this->perm->getEffectivePermissions();
	}

	/**
	 * @return bool
	 */
	public function isPlayer() {
		return false;
	}

	public function getServer() : Server {
		return Server::getInstance();
	}

	public function sendMessage(string $message) {
		foreach(explode("\n", trim($message)) as $line) {
			MainLogger::getLogger()->info($line);
		}
	}

	public function getName() : string {
		return "CONSOLE";
	}

	public function isOp() : bool {
		return true;
	}

	public function setOp(bool $value) {
	}

}