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
 * PocketMine-MP is the Minecraft: PE multiplayer server software
 * Homepage: http://www.pocketmine.net/
 */
namespace pocketmine;

use pocketmine\block\Block;
use pocketmine\command\CommandReader;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\SimpleCommandMap;
use pocketmine\entity\animal\walking\Chicken;
use pocketmine\entity\animal\walking\Cow;
use pocketmine\entity\animal\walking\Mooshroom;
use pocketmine\entity\animal\walking\Ocelot;
use pocketmine\entity\animal\walking\Pig;
use pocketmine\entity\animal\walking\Rabbit;
use pocketmine\entity\animal\walking\Sheep;
use pocketmine\entity\Arrow;
use pocketmine\entity\Attribute;
use pocketmine\entity\Effect;
use pocketmine\entity\Egg;
use pocketmine\entity\Entity;
use pocketmine\entity\FallingSand;
use pocketmine\entity\Human;
use pocketmine\entity\Item as DroppedItem;
use pocketmine\entity\monster\flying\Blaze;
use pocketmine\entity\monster\flying\Ghast;
use pocketmine\entity\monster\walking\CaveSpider;
use pocketmine\entity\monster\walking\Creeper;
use pocketmine\entity\monster\walking\Enderman;
use pocketmine\entity\monster\walking\IronGolem;
use pocketmine\entity\monster\walking\PigZombie;
use pocketmine\entity\monster\walking\Silverfish;
use pocketmine\entity\monster\walking\Skeleton;
use pocketmine\entity\monster\walking\SnowGolem;
use pocketmine\entity\monster\walking\Spider;
use pocketmine\entity\monster\walking\Wolf;
use pocketmine\entity\monster\walking\Zombie;
use pocketmine\entity\monster\walking\ZombieVillager;
use pocketmine\entity\PrimedTNT;
use pocketmine\entity\projectile\FireBall;
use pocketmine\entity\Snowball;
use pocketmine\entity\Squid;
use pocketmine\entity\ThrownPotion;
use pocketmine\entity\Villager;
use pocketmine\event\HandlerList;
use pocketmine\event\level\LevelInitEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\server\ServerShutdownEvent;
use pocketmine\event\Timings;
use pocketmine\event\TimingsHandler;
use pocketmine\inventory\CraftingManager;
use pocketmine\inventory\InventoryType;
use pocketmine\inventory\Recipe;
use pocketmine\item\Item;
use pocketmine\level\format\anvil\Anvil;
use pocketmine\level\format\LevelProviderManager;
use pocketmine\level\format\mcregion\McRegion;
use pocketmine\level\generator\biome\Biome;
use pocketmine\level\Level;
use pocketmine\metadata\EntityMetadataStore;
use pocketmine\metadata\LevelMetadataStore;
use pocketmine\metadata\PlayerMetadataStore;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\Network;
use pocketmine\network\protocol\DataPacket;
use pocketmine\network\protocol\PlayerListPacket;
use pocketmine\network\query\QueryHandler;
use pocketmine\network\RakLibInterface;
use pocketmine\network\rcon\RCON;
use pocketmine\network\SourceInterface;
use pocketmine\network\upnp\UPnP;
use pocketmine\permission\BanList;
use pocketmine\permission\DefaultPermissions;
use pocketmine\plugin\PharPluginLoader;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginLoadOrder;
use pocketmine\plugin\PluginManager;
use pocketmine\scheduler\CallbackTask;
use pocketmine\scheduler\GarbageCollectionTask;
use pocketmine\scheduler\ServerScheduler;
use pocketmine\tile\Chest;
use pocketmine\tile\EnchantTable;
use pocketmine\tile\Furnace;
use pocketmine\tile\Sign;
use pocketmine\tile\Tile;
use pocketmine\utils\Cache;
use pocketmine\utils\Config;
use pocketmine\utils\LevelException;
use pocketmine\utils\MainLogger;
use pocketmine\utils\ServerException;
use pocketmine\utils\TextFormat;
use pocketmine\utils\TextWrapper;
use pocketmine\utils\Utils;
use pocketmine\utils\UUID;
use pocketmine\utils\VersionString;

/**
 * The class that manages everything
 */
class Server {

	const BROADCAST_CHANNEL_ADMINISTRATIVE = "pocketmine.broadcast.admin";
	const BROADCAST_CHANNEL_USERS = "pocketmine.broadcast.user";

	/** @var Server */
	private static $instance = null;

	/** @var int */
	public $networkCompressionLevel = 7;

	/** @var PacketMaker */
	public $packetMaker = null;

	/** @var BanList */
	private $banByName = null;

	/** @var BanList */
	private $banByIP = null;

	/** @var Config */
	private $operators = null;

	/** @var Config */
	private $whitelist = null;

	/** @var bool */
	private $isRunning = true;

	/** @var bool */
	private $hasStopped = false;

	/** @var PluginManager */
	private $pluginManager = null;

	/** @var ServerScheduler */
	private $scheduler = null;

	/**
	 * The tick count relative to startup
	 *
	 * @var int
	 */
	private $tickCounter;

	/** @var int */
	private $nextTick = 0;

	/** @var array */
	private $tickAverage = [20, 20, 20, 20, 20];

	/** @var array */
	private $useAverage = [20, 20, 20, 20, 20];

	/** @var \AttachableThreadedLogger */
	private $logger;

	/** @var CommandReader */
	private $console = null;

	/** @var SimpleCommandMap */
	private $commandMap = null;

	/** @var CraftingManager */
	private $craftingManager;

	/** @var ConsoleCommandSender */
	private $consoleSender;

	/** @var int */
	private $maxPlayers;

	/** @var bool */
	private $autoSave;

	/** @var bool */
	private $autoGenerate;

	/** @var RCON */
	private $rcon;

	/** @var EntityMetadataStore */
	private $entityMetadata;

	/** @var PlayerMetadataStore */
	private $playerMetadata;

	/** @var LevelMetadataStore */
	private $levelMetadata;

	/** @var Network */
	private $network;

	/** @var UUID */
	private $serverID;

	/** @var \ClassLoader */
	private $autoLoader;

	/** @var string */
	private $filePath;

	/** @var string */
	private $dataPath;

	/** @var string */
	private $pluginPath;

	/** @var QueryHandler */
	private $queryHandler;

	/** @var Config */
	private $properties;

	/** @var Config */
	private $config;

	/** @var Config */
	private $softConfig;

	/** @var Player[] */
	private $players = [];

	/** @var Player[] */
	private $playerList = [];

	/** @var array */
	private $identifiers = [];

	/** @var Level[] */
	private $levels = [];

	/** @var Level */
	private $levelDefault = null;

	/** @var bool */
	private $useAnimal;

	/** @var int */
	private $animalLimit;

	/** @var bool */
	private $useMonster;

	/** @var int */
	private $monsterLimit;

	/**
	 * @param \ClassLoader $autoLoader
	 * @param \ThreadedLogger $logger
	 * @param string $filePath
	 * @param string $dataPath
	 * @param string $pluginPath
	 */
	public function __construct(\ClassLoader $autoLoader, \ThreadedLogger $logger, $filePath, $dataPath, $pluginPath) {
		self::$instance = $this;

		$this->autoLoader = $autoLoader;
		$this->logger = $logger;
		$this->filePath = $filePath;
		if(!file_exists($dataPath . "worlds/")) mkdir($dataPath . "worlds/", 0777);
		if(!file_exists($dataPath . "players/")) mkdir($dataPath . "players/", 0777);
		if(!file_exists($pluginPath)) mkdir($pluginPath, 0777);

		$this->dataPath = realpath($dataPath) . DIRECTORY_SEPARATOR;
		$this->pluginPath = realpath($pluginPath) . DIRECTORY_SEPARATOR;

		$this->console = new CommandReader();

		$version = new VersionString($this->getPocketMineVersion());
		$this->logger->info("Starting Minecraft: Pocket Edition server for " . TextFormat::AQUA . $this->getVersion());

		$this->logger->info("Loading configuration files...");
		if(!file_exists($this->dataPath . "pocketmine-soft.yml")) {
			$content = file_get_contents($this->filePath . "src/pocketmine/resources/pocketmine-soft.yml");
			@file_put_contents($this->dataPath . "pocketmine-soft.yml", $content);
		}
		$this->softConfig = new Config($this->dataPath . "pocketmine-soft.yml", Config::YAML, []);
		if(!file_exists($this->dataPath . "pocketmine.yml")) {
			$content = file_get_contents($this->filePath . "src/pocketmine/resources/pocketmine.yml");
			@file_put_contents($this->dataPath . "pocketmine.yml", $content);
		}
		$this->config = new Config($this->dataPath . "pocketmine.yml", Config::YAML, []);
		$this->properties = new Config($this->dataPath . "server.properties", Config::PROPERTIES, ["motd" => "Extropy Server", "server-port" => 19132, "memory-limit" => "256M", "white-list" => false, "max-players" => 20, "allow-flight" => false, "spawn-animals" => true, "animals-limit" => 0, "spawn-mobs" => true, "mobs-limit" => 0, "gamemode" => 0, "force-gamemode" => false, "hardcore" => false, "pvp" => true, "difficulty" => 1, "generator-settings" => "", "level-name" => "world", "level-seed" => "", "level-type" => "DEFAULT", "enable-query" => true, "enable-rcon" => false, "rcon.password" => substr(base64_encode(@Utils::getRandomBytes(20, false)), 3, 10), "auto-save" => true, "auto-generate" => false]);

		ServerScheduler::$WORKERS = $this->getProperty("settings.async-workers", 8);

		$this->scheduler = new ServerScheduler();

		if($this->getConfigBoolean("enable-rcon", false) === true) {
			$this->rcon = new RCON($this, $this->getConfigString("rcon.password", ""), $this->getConfigInt("rcon.port", $this->getPort()), ($ip = $this->getIp()) != "" ? $ip : "0.0.0.0", $this->getConfigInt("rcon.threads", 1), $this->getConfigInt("rcon.clients-per-thread", 50));
		}

		$this->entityMetadata = new EntityMetadataStore();
		$this->playerMetadata = new PlayerMetadataStore();
		$this->levelMetadata = new LevelMetadataStore();

		$this->operators = new Config($this->dataPath . "ops.txt", Config::ENUM);
		$this->whitelist = new Config($this->dataPath . "white-list.txt", Config::ENUM);
		if(file_exists($this->dataPath . "banned.txt") and !file_exists($this->dataPath . "banned-players.txt")) {
			@rename($this->dataPath . "banned.txt", $this->dataPath . "banned-players.txt");
		}
		@touch($this->dataPath . "banned-players.txt");
		$this->banByName = new BanList($this->dataPath . "banned-players.txt");
		$this->banByName->load();
		@touch($this->dataPath . "banned-ips.txt");
		$this->banByIP = new BanList($this->dataPath . "banned-ips.txt");
		$this->banByIP->load();

		$this->maxPlayers = $this->getConfigInt("max-players", 20);
		$this->setAutoSave($this->getConfigBoolean("auto-save", true));
		$this->setAutoGenerate($this->getConfigBoolean("auto-generate", false));

		$this->useAnimal = $this->getConfigBoolean("spawn-animals", false);
		$this->animalLimit = $this->getConfigInt("animals-limit", 0);
		$this->useMonster = $this->getConfigBoolean("spawn-mobs", false);
		$this->monsterLimit = $this->getConfigInt("mobs-limit", 0);

		if(($memory = str_replace("B", "", strtoupper($this->getConfigString("memory-limit", "256M")))) !== false) {
			$value = ["M" => 1, "G" => 1024];
			$real = ((int)substr($memory, 0, -1)) * $value[substr($memory, -1)];
			if($real < 128) {
				$this->logger->warning($this->getName() . " may not work right with less than 128MB of RAM");
			}
			@ini_set("memory_limit", $memory);
		} else {
			$this->setConfigString("memory-limit", "256M");
		}
		$this->network = new Network($this);

		if($this->getConfigBoolean("hardcore", false) and $this->getDifficulty() != 3) {
			$this->setConfigInt("difficulty", 3);
		}

		define("pocketmine\\DEBUG", (int)$this->getProperty("debug.level", 1));
		if($this->logger instanceof MainLogger) {
			$this->logger->setLogDebug(\pocketmine\DEBUG > 1);
		}
		define("ADVANCED_CACHE", $this->getProperty("settings.advanced-cache", false));
		if(ADVANCED_CACHE == true) {
			$this->logger->info("Advanced cache enabled");
		}

		Level::$COMPRESSION_LEVEL = $this->getProperty("chunk-sending.compression-level", 7);

		if(defined("pocketmine\\DEBUG") and \pocketmine\DEBUG >= 0) {
			@\cli_set_process_title($this->getName() . " " . $this->getPocketMineVersion());
		}

		$this->logger->info("Starting Minecraft: PE server on " . ($this->getIp() === "" ? "*" : $this->getIp()) . ":" . $this->getPort());
		define("BOOTUP_RANDOM", @Utils::getRandomBytes(16));
		$this->serverID = Utils::getMachineUniqueId($this->getIp() . $this->getPort());

		$this->addInterface($this->mainInterface = new RakLibInterface($this));

		$this->logger->info(TextFormat::YELLOW . "This server is running " . $this->getName() . " version " . $version->get(true) . " '" . $this->getCodename() . "' (API " . $this->getApiVersion() . ")");
		$this->logger->info($this->getName() . " is distributed under the LGPL License");

		PluginManager::$pluginParentTimer = new TimingsHandler("** Plugins");
		Timings::init();

		$this->consoleSender = new ConsoleCommandSender();
		$this->commandMap = new SimpleCommandMap($this);

		$this->registerEntities();
		$this->registerTiles();

		InventoryType::init();
		Effect::init();
		Block::init();
		Item::init();
		Biome::init();
		Attribute::init();
		TextWrapper::init();
		$this->craftingManager = new CraftingManager();

		$this->pluginManager = new PluginManager($this, $this->commandMap);
		$this->pluginManager->subscribeToPermission(Server::BROADCAST_CHANNEL_ADMINISTRATIVE, $this->consoleSender);
		$this->pluginManager->setUseTimings(false);
		$this->pluginManager->registerInterface(PharPluginLoader::class);

		\set_exception_handler([$this, "exceptionHandler"]);
		register_shutdown_function([$this, "crashDump"]);

		$configPlugins = $this->getAdvancedProperty("plugins", []);
		if(count($configPlugins) > 0) {
			$this->getLogger()->info("Checking extra plugins");
			foreach($configPlugins as $plugin => $download) {
				if(!isset($plugins[$plugin])) {
					$path = $this->pluginPath . "/" . $plugin . ".phar";
					if(substr($download, 0, 4) === "http") {
						$this->getLogger()->info("Downloading " . $plugin);
						file_put_contents($path, Utils::getURL($download));
					} else {
						file_put_contents($path, file_get_contents($download));
					}
				}
			}
		}

		$this->pluginManager->loadPlugins($this->pluginPath);

		$this->enablePlugins(PluginLoadOrder::STARTUP);

		LevelProviderManager::addProvider($this, Anvil::class);
		LevelProviderManager::addProvider($this, McRegion::class);

		foreach((array)$this->getProperty("worlds", []) as $name => $worldSetting) {
			if($this->loadLevel($name) === false) {
				$this->generateLevel($name);
			}
		}

		if($this->getDefaultLevel() === null) {
			$default = $this->getConfigString("level-name", "world");
			if(trim($default) == "") {
				$this->getLogger()->warning("level-name cannot be null, using default");
				$default = "world";
				$this->setConfigString("level-name", "world");
			}
			if($this->loadLevel($default) === false) {
				$seed = $this->getConfigInt("level-seed", time());
				$this->generateLevel($default, $seed === 0 ? time() : $seed);
			}

			$this->setDefaultLevel($this->getLevelByName($default));
		}


		$this->properties->save();

		if(!($this->getDefaultLevel() instanceof Level)) {
			$this->getLogger()->emergency("No default level has been loaded");
			$this->forceShutdown();
			return;
		}

		$this->scheduler->scheduleDelayedRepeatingTask(new CallbackTask([Cache::class, "cleanup"]), $this->getProperty("ticks-per.cache-cleanup", 900), $this->getProperty("ticks-per.cache-cleanup", 900));
		if($this->getAutoSave() and $this->getProperty("ticks-per.autosave", 6000) > 0) {
			$this->scheduler->scheduleDelayedRepeatingTask(new CallbackTask([$this, "doAutoSave"]), $this->getProperty("ticks-per.autosave", 6000), $this->getProperty("ticks-per.autosave", 6000));
		}

		if($this->getProperty("chunk-gc.period-in-ticks", 600) > 0) {
			$this->scheduler->scheduleDelayedRepeatingTask(new CallbackTask([$this, "doLevelGC"]), $this->getProperty("chunk-gc.period-in-ticks", 600), $this->getProperty("chunk-gc.period-in-ticks", 600));
		}

		$this->scheduler->scheduleRepeatingTask(new GarbageCollectionTask(), 900);

		$this->enablePlugins(PluginLoadOrder::POSTWORLD);

		if($this->getAdvancedProperty("main.player-shuffle", 0) > 0) {
			$this->scheduler->scheduleDelayedRepeatingTask(new CallbackTask([$this, "shufflePlayers"]), $this->getAdvancedProperty("main.player-shuffle", 0), $this->getAdvancedProperty("main.player-shuffle", 0));
		}

		$this->start();
	}

	/**
	 * @return string
	 */
	public function getPocketMineVersion() {
		return \pocketmine\VERSION;
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return \pocketmine\MINECRAFT_VERSION;
	}

	/**
	 * @param string $variable
	 * @param mixed $defaultValue
	 *
	 * @return mixed
	 */
	public function getProperty($variable, $defaultValue = null) {
		$value = $this->config->getNested($variable);
		return $value === null ? $defaultValue : $value;
	}

	/**
	 * @param string $variable
	 * @param boolean $defaultValue
	 *
	 * @return boolean
	 */
	public function getConfigBoolean($variable, $defaultValue = false) {
		$v = getopt("", ["$variable::"]);
		if(isset($v[$variable])) {
			$value = $v[$variable];
		} else {
			$value = $this->properties->exists($variable) ? $this->properties->get($variable) : $defaultValue;
		}

		if(is_bool($value)) {
			return $value;
		}
		switch(strtolower($value)) {
			case "on":
			case "true":
			case "1":
			case "yes":
				return true;
		}

		return false;
	}

	/**
	 * @param string $variable
	 * @param string $defaultValue
	 *
	 * @return string
	 */
	public function getConfigString($variable, $defaultValue = "") {
		$v = getopt("", ["$variable::"]);
		if(isset($v[$variable])) {
			return (string)$v[$variable];
		}

		return $this->properties->exists($variable) ? $this->properties->get($variable) : $defaultValue;
	}

	/**
	 * @param string $variable
	 * @param int $defaultValue
	 *
	 * @return int
	 */
	public function getConfigInt($variable, $defaultValue = 0) {
		$v = getopt("", ["$variable::"]);
		if(isset($v[$variable])) {
			return (int)$v[$variable];
		}

		return $this->properties->exists($variable) ? (int)$this->properties->get($variable) : (int)$defaultValue;
	}

	/**
	 * @return int
	 */
	public function getPort() {
		return $this->getConfigInt("server-port", 19132);
	}

	/**
	 * @return string
	 */
	public function getIp() {
		return $this->getConfigString("server-ip", "0.0.0.0");
	}

	/**
	 * @return string
	 */
	public function getName() {
		return "Extropy";
	}

	/**
	 * @param string $variable
	 * @param string $value
	 */
	public function setConfigString($variable, $value) {
		$this->properties->set($variable, $value);
	}

	/**
	 * @return int
	 */
	public function getDifficulty() {
		return $this->getConfigInt("difficulty", 1);
	}

	/**
	 * @param string $variable
	 * @param int $value
	 */
	public function setConfigInt($variable, $value) {
		$this->properties->set($variable, (int)$value);
	}

	/**
	 * @deprecated
	 *
	 * @param SourceInterface $interface
	 */
	public function addInterface(SourceInterface $interface) {
		$this->network->registerInterface($interface);
	}

	/**
	 * @return string
	 */
	public function getCodename() {
		return \pocketmine\CODENAME;
	}

	/**
	 * @return string
	 */
	public function getApiVersion() {
		return \pocketmine\API_VERSION;
	}

	private function registerEntities() {
		Entity::registerEntity(Arrow::class);
		Entity::registerEntity(DroppedItem::class);
		Entity::registerEntity(FallingSand::class);
		Entity::registerEntity(PrimedTNT::class);
		Entity::registerEntity(Snowball::class);
		Entity::registerEntity(Egg::class);
		Entity::registerEntity(ThrownPotion::class);
		Entity::registerEntity(Villager::class);
		Entity::registerEntity(Squid::class);
		Entity::registerEntity(Human::class, true);

		Entity::registerEntity(Blaze::class);
		Entity::registerEntity(CaveSpider::class);
		Entity::registerEntity(Chicken::class);
		Entity::registerEntity(Cow::class);
		Entity::registerEntity(Creeper::class);
		Entity::registerEntity(Enderman::class);
		Entity::registerEntity(Ghast::class);
		Entity::registerEntity(IronGolem::class);
		Entity::registerEntity(Mooshroom::class);
		Entity::registerEntity(Ocelot::class);
		Entity::registerEntity(Pig::class);
		Entity::registerEntity(PigZombie::class);
		Entity::registerEntity(Rabbit::class);
		Entity::registerEntity(Sheep::class);
		Entity::registerEntity(Silverfish::class);
		Entity::registerEntity(Skeleton::class);
		Entity::registerEntity(SnowGolem::class);
		Entity::registerEntity(Spider::class);
		Entity::registerEntity(Wolf::class);
		Entity::registerEntity(Zombie::class);
		Entity::registerEntity(ZombieVillager::class);
		Entity::registerEntity(FireBall::class);
	}

	private function registerTiles() {
		Tile::registerTile(Chest::class);
		Tile::registerTile(Furnace::class);
		Tile::registerTile(Sign::class);
		Tile::registerTile(EnchantTable::class);
	}

	/**
	 * @param string $variable
	 * @param mixed $defaultValue
	 *
	 * @return mixed
	 */
	public function getAdvancedProperty($variable, $defaultValue = null) {
		$vars = explode(".", $variable);
		$base = array_shift($vars);
		if($this->softConfig->exists($base)) {
			$base = $this->softConfig->get($base);
		} else {
			return $defaultValue;
		}

		while(count($vars) > 0) {
			$baseKey = array_shift($vars);
			if(is_array($base) and isset($base[$baseKey])) {
				$base = $base[$baseKey];
			} else {
				return $defaultValue;
			}
		}

		return $base;
	}

	/**
	 * @return \AttachableThreadedLogger
	 */
	public function getLogger() {
		return $this->logger;
	}

	/**
	 * @param int $type
	 */
	public function enablePlugins($type) {
		foreach($this->pluginManager->getPlugins() as $plugin) {
			if(!$plugin->isEnabled() and $plugin->getDescription()->getOrder() === $type) {
				$this->enablePlugin($plugin);
			}
		}

		if($type === PluginLoadOrder::POSTWORLD) {
			$this->commandMap->registerServerAliases();
			DefaultPermissions::registerCorePermissions();
		}
	}

	/**
	 * @param Plugin $plugin
	 */
	public function enablePlugin(Plugin $plugin) {
		$this->pluginManager->enablePlugin($plugin);
	}

	/**
	 * Loads a level from the data directory
	 *
	 * @param string $name
	 *
	 * @return bool
	 *
	 * @throws LevelException
	 */
	public function loadLevel($name) {
		if(trim($name) === "") {
			throw new LevelException("Invalid empty level name");
		}
		if($this->isLevelLoaded($name)) {
			return true;
		} elseif(!$this->isLevelGenerated($name)) {
			$this->logger->notice("Level \"" . $name . "\" not found");

			return false;
		}

		$path = $this->getDataPath() . "worlds/" . $name . "/";

		$provider = LevelProviderManager::getProvider($path);

		if($provider === null) {
			$this->logger->error("Could not load level \"" . $name . "\": Unknown provider");

			return false;
		}
		//$entities = new Config($path."entities.yml", Config::YAML);
		//if(file_exists($path . "tileEntities.yml")){
		//	@rename($path . "tileEntities.yml", $path . "tiles.yml");
		//}

		try {
			$level = new Level($this, $name, $path, $provider);
		} catch(\Exception $e) {

			$this->logger->error("Could not load level \"" . $name . "\": " . $e->getMessage());
			if($this->logger instanceof MainLogger) {
				$this->logger->logException($e);
			}

			return false;
		}

		$this->levels[$level->getId()] = $level;

		$level->initLevel();

		$this->getPluginManager()->callEvent(new LevelLoadEvent($level));

		return true;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isLevelLoaded($name) {
		return $this->getLevelByName($name) instanceof Level;
	}

	/**
	 * @param $name
	 *
	 * @return Level
	 */
	public function getLevelByName($name) {
		foreach($this->getLevels() as $level) {
			if($level->getFolderName() === $name) {
				return $level;
			}
		}

		return null;
	}

	/**
	 * @return Level[]
	 */
	public function getLevels() {
		return $this->levels;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isLevelGenerated($name) {
		if(trim($name) === "") {
			return false;
		}
		$path = $this->getDataPath() . "worlds/" . $name . "/";
		if(!($this->getLevelByName($name) instanceof Level)) {

			if(LevelProviderManager::getProvider($path) === null) {
				return false;
			}
			/*if(file_exists($path)){
				$level = new LevelImport($path);
				if($level->import() === false){ //Try importing a world
					return false;
				}
			}else{
				return false;
			}*/
		}

		return true;
	}

	/**
	 * @return string
	 */
	public function getDataPath() {
		return $this->dataPath;
	}

	/**
	 * @return PluginManager
	 */
	public function getPluginManager() {
		return $this->pluginManager;
	}

	/**
	 * Generates a new level if it does not exists
	 *
	 * @param string $name
	 * @param int $seed
	 * @param array $options
	 *
	 * @return bool
	 */
	public function generateLevel($name, $seed = null, $options = []) {
		if(trim($name) === "" or $this->isLevelGenerated($name)) {
			return false;
		}

		$seed = $seed === null ? (PHP_INT_SIZE === 8 ? unpack("N", @Utils::getRandomBytes(4, false))[1] << 32 >> 32 : unpack("N", @Utils::getRandomBytes(4, false))[1]) : (int)$seed;

		if(($provider = LevelProviderManager::getProviderByName($providerName = $this->getProperty("level-settings.default-format", "mcregion"))) === null) {
			$provider = LevelProviderManager::getProviderByName($providerName = "mcregion");
		}

		try {
			$path = $this->getDataPath() . "worlds/" . $name . "/";
			/** @var \pocketmine\level\format\LevelProvider $provider */
			$provider::generate($path, $name, $seed, $options);

			$level = new Level($this, $name, $path, $provider);
			$this->levels[$level->getId()] = $level;

			$level->initLevel();
		} catch(\Exception $e) {
			$this->logger->error("Could not generate level \"" . $name . "\": " . $e->getMessage());
			if($this->logger instanceof MainLogger) {
				$this->logger->logException($e);
			}

			return false;
		}

		$this->getPluginManager()->callEvent(new LevelInitEvent($level));

		$this->getPluginManager()->callEvent(new LevelLoadEvent($level));

		$centerX = $level->getSpawnLocation()->getX() >> 4;
		$centerZ = $level->getSpawnLocation()->getZ() >> 4;

		$order = [];

		for($X = -3; $X <= 3; ++$X) {
			for($Z = -3; $Z <= 3; ++$Z) {
				$distance = $X ** 2 + $Z ** 2;
				$chunkX = $X + $centerX;
				$chunkZ = $Z + $centerZ;
				$index = Level::chunkHash($chunkX, $chunkZ);
				$order[$index] = $distance;
			}
		}

		asort($order);

		foreach($order as $index => $distance) {
			Level::getXZ($index, $chunkX, $chunkZ);
			$level->generateChunk($chunkX, $chunkZ, true);
		}

		return true;
	}

	/**
	 * @return Level
	 */
	public function getDefaultLevel() {
		return $this->levelDefault;
	}

	/**
	 * Sets the default level to a different level
	 * This won't change the level-name property,
	 * it only affects the server on runtime
	 *
	 * @param Level $level
	 */
	public function setDefaultLevel($level) {
		if($level === null or ($this->isLevelLoaded($level->getFolderName()) and $level !== $this->levelDefault)) {
			$this->levelDefault = $level;
		}
	}

	public function forceShutdown() {
		if($this->hasStopped) {
			return;
		}

		try {
			$this->hasStopped = true;

			$this->shutdown();
			if($this->rcon instanceof RCON) {
				$this->rcon->stop();
			}

			if($this->getProperty("settings.upnp-forwarding", false) === true) {
				$this->logger->info("[UPnP] Removing port forward...");
				UPnP::RemovePortForward($this->getPort());
			}

			$this->pluginManager->disablePlugins();

			foreach($this->players as $player) {
				$player->close(TextFormat::YELLOW . $player->getName() . " has left the game", $this->getProperty("settings.shutdown-message", "Server closed"));
			}

			foreach($this->getLevels() as $level) {
				$this->unloadLevel($level, true);
			}

			HandlerList::unregisterAll();

			$this->scheduler->cancelAllTasks();
			$this->scheduler->mainThreadHeartbeat(PHP_INT_MAX);

			$this->properties->save();

			$this->packetMaker->shutdown();

			$this->console->shutdown();
			$this->console->notify();

			foreach($this->network->getInterfaces() as $interface) {
				$interface->shutdown();
				$this->network->unregisterInterface($interface);
			}
		} catch(\Exception $e) {
			$this->logger->emergency("Crashed while crashing, killing process");
			@kill(getmypid());
		}

	}

	/**
	 * Shutdowns the server correctly
	 */
	public function shutdown() {
		$this->pluginManager->callEvent(new ServerShutdownEvent(time()));
		$this->isRunning = false;
		gc_collect_cycles();
	}

	/**
	 * @param Level $level
	 * @param bool $forceUnload
	 *
	 * @return bool
	 */
	public function unloadLevel(Level $level, $forceUnload = false) {
		if($level->unload($forceUnload) === true) {
			unset($this->levels[$level->getId()]);

			return true;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function getAutoSave() {
		return $this->autoSave;
	}

	/**
	 * @param bool $value
	 */
	public function setAutoSave($value) {
		$this->autoSave = (bool)$value;
		foreach($this->getLevels() as $level) {
			$level->setAutoSave($this->autoSave);
		}
	}

	/**
	 * Starts the PocketMine-MP server and starts processing ticks and packets
	 */
	public function start() {
		if($this->getConfigBoolean("enable-query", true) === true) {
			$this->queryHandler = new QueryHandler();
		}

		$this->network->setName($this->getMotd());

		foreach($this->getIPBans()->getEntries() as $entry) $this->network->blockAddress($entry->getName(), -1);

		$this->tickCounter = 0;

		if(function_exists("pcntl_signal")) {
			pcntl_signal(SIGTERM, [$this, "handleSignal"]);
			pcntl_signal(SIGINT, [$this, "handleSignal"]);
			pcntl_signal(SIGHUP, [$this, "handleSignal"]);
			$this->getScheduler()->scheduleRepeatingTask(new CallbackTask("pcntl_signal_dispatch"), 5);
		}


		$this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "checkTicks"]), 20 * 5);

		$this->logger->info("Default game type: " . self::getGamemodeString($this->getGamemode()));

		$this->logger->info("Done (" . round(microtime(true) - \pocketmine\START_TIME, 3) . 's)! For help, type "help" or "?"');

		$this->packetMaker = new PacketMaker($this->getLoader());

		$this->tickAverage = [];
		$this->useAverage = [];
		for($i = 0; $i < 1200; $i++) {
			$this->tickAverage[] = 20;
			$this->useAverage[] = 0;
		}

		$this->tickProcessor();
		$this->forceShutdown();
	}

	/**
	 * @return BanList
	 */
	public function getIPBans() {
		return $this->banByIP;
	}

	/**
	 * @return ServerScheduler
	 */
	public function getScheduler() {
		return $this->scheduler;
	}

	/**
	 * Returns the gamemode text name
	 *
	 * @param int $mode
	 *
	 * @return string
	 */
	public static function getGamemodeString($mode) {
		switch((int)$mode) {
			case Player::SURVIVAL:
				return "SURVIVAL";
			case Player::CREATIVE:
				return "CREATIVE";
			case Player::ADVENTURE:
				return "ADVENTURE";
			case Player::SPECTATOR:
				return "SPECTATOR";
		}

		return "UNKNOWN";
	}

	/**
	 * @return int
	 */
	public function getGamemode() {
		return $this->getConfigInt("gamemode", 0) & 0b11;
	}

	/**
	 * @return \ClassLoader
	 */
	public function getLoader() {
		return $this->autoLoader;
	}

	private function tickProcessor() {
		$this->nextTick = microtime(true);
		while($this->isRunning) {
			$this->tick();
			$next = $this->nextTick - 0.0001;
			if($next > microtime(true)) {
				@time_sleep_until($next);
			}
		}
	}

	/**
	 * Tries to execute a server tick
	 */
	private function tick() {
		$tickTime = microtime(true);
		if($tickTime < $this->nextTick) return false;

		++$this->tickCounter;

		$this->checkConsole();


		while(strlen($str = $this->packetMaker->readThreadToMainPacket()) > 0) $this->mainInterface->putReadyPacket($str);

		$this->network->processInterfaces();

		$this->scheduler->mainThreadHeartbeat($this->tickCounter);

		$this->checkTickUpdates($this->tickCounter);

		if(($this->tickCounter & 0b1111) === 0) {
			$this->titleTick();
			if($this->queryHandler !== null and ($this->tickCounter & 0b111111111) === 0) {
				try {
					$this->queryHandler->regenerateInfo();
				} catch(\Exception $e) {
					if($this->logger instanceof MainLogger) {
						$this->logger->logException($e);
					}
				}
			}
		}

		if(($this->tickCounter % 100) === 0) {
			foreach($this->levels as $level) {
				$level->clearCache();
			}
		}

		if($this->tickCounter % 200 === 0 && ($this->isUseAnimal() || $this->isUseMonster())) {
			SpawnerCreature::generateEntity($this, $this->isUseAnimal(), $this->isUseMonster());
		}

		$now = microtime(true);
		array_shift($this->tickAverage);
		$this->tickAverage[] = min(20, 1 / max(0.001, $now - $tickTime));
		array_shift($this->useAverage);
		$this->useAverage[] = min(1, ($now - $tickTime) / 0.05);

		if(($this->nextTick - $tickTime) < -1) $this->nextTick = $tickTime;
		$this->nextTick += 0.05;

		return true;
	}

	public function checkConsole() {
		if(($line = $this->console->getLine()) !== null) {
			$this->pluginManager->callEvent($ev = new ServerCommandEvent($this->consoleSender, $line));
			if(!$ev->isCancelled()) $this->dispatchCommand($ev->getSender(), $ev->getCommand());
		}
	}

	/**
	 * Executes a command from a CommandSender
	 *
	 * @param CommandSender $sender
	 * @param string $commandLine
	 *
	 * @return bool
	 *
	 * @throws \Exception
	 */
	public function dispatchCommand(CommandSender $sender, $commandLine) {
		if($this->commandMap->dispatch($sender, $commandLine)) return true;

		if(!is_string($message = $this->getAdvancedProperty("messages.unknown-command", "Unknown command. Type \"/help\" for help."))) $message = "Unknown command. Type \"/help\" for help.";
		if($sender instanceof Player) {
			$sender->sendMessage(TextFormat::RED . $message);
		} else {
			$sender->sendMessage($message);
		}

		return false;
	}

	private function checkTickUpdates($currentTick) {
		foreach($this->getLevels() as $level) {
			try {
				$level->doTick($currentTick);
			} catch(\Throwable $e) {
				$this->logger->critical("Could not tick level " . $level->getName() . ": " . $e->getMessage());
				if(\pocketmine\DEBUG > 1 and $this->logger instanceof MainLogger) {
					$this->logger->logException($e);
				}
			}
		}
	}

	private function titleTick() {
		if(defined("pocketmine\\DEBUG") and \pocketmine\DEBUG >= 0 and \pocketmine\ANSI) {
			echo "\x1b]0;" . $this->getName() . " " . $this->getPocketMineVersion() . " | Online " . count($this->getOnlinePlayers()) . "/" . $this->getMaxPlayers() . " | RAM " . round((memory_get_usage() / 1024) / 1024, 2) . "/" . round((memory_get_usage(true) / 1024) / 1024, 2) . " MB | U " . round($this->mainInterface->getUploadUsage() / 1024, 2) . " D " . round($this->mainInterface->getDownloadUsage() / 1024, 2) . " kB/s | TPS " . $this->getTicksPerSecond() . " | Load " . $this->getTickUsage() . "%\x07";
		}
	}

	/**
	 * @return Player[]
	 */
	public function getOnlinePlayers() {
		return $this->playerList;
	}

	/**
	 * @return int
	 */
	public function getMaxPlayers() {
		return $this->maxPlayers;
	}

	/**
	 * Returns the last server TPS measure
	 *
	 * @return float
	 */
	public function getTicksPerSecond() {
		return round(array_sum($this->tickAverage) / count($this->tickAverage), 2);
	}

	/**
	 * Returns the TPS usage/load in %
	 *
	 * @return float
	 */
	public function getTickUsage() {
		return round((array_sum($this->useAverage) / count($this->useAverage)) * 100, 2);
	}

	public function isUseAnimal() {
		return $this->useAnimal;
	}

	public function isUseMonster() {
		return $this->useMonster;
	}

	/**
	 * Parses a string and returns a gamemode integer, -1 if not found
	 *
	 * @param string $str
	 *
	 * @return int
	 */
	public static function getGamemodeFromString($str) {
		switch(strtolower(trim($str))) {
			case (string)Player::SURVIVAL:
			case "survival":
			case "s":
				return Player::SURVIVAL;

			case (string)Player::CREATIVE:
			case "creative":
			case "c":
				return Player::CREATIVE;

			case (string)Player::ADVENTURE:
			case "adventure":
			case "a":
				return Player::ADVENTURE;

			case (string)Player::SPECTATOR:
			case "spectator":
			case "view":
			case "v":
				return Player::SPECTATOR;
		}

		return -1;
	}

	/**
	 * @param string $str
	 *
	 * @return int
	 */
	public static function getDifficultyFromString($str) {
		switch(strtolower(trim($str))) {
			case "0":
			case "peaceful":
			case "p":
				return 0;

			case "1":
			case "easy":
			case "e":
				return 1;

			case "2":
			case "normal":
			case "n":
				return 2;

			case "3":
			case "hard":
			case "h":
				return 3;
		}

		return -1;
	}

	/**
	 * @return Server
	 */
	public static function getInstance() {
		return self::$instance;
	}

	public function getAnimalLimit() {
		return $this->animalLimit;
	}

	public function getMonsterLimit() {
		return $this->monsterLimit;
	}

	/**
	 * @return bool
	 */
	public function isRunning() {
		return $this->isRunning === true;
	}

	/**
	 * @return string
	 */
	public function getFilePath() {
		return $this->filePath;
	}

	/**
	 * @return string
	 */
	public function getPluginPath() {
		return $this->pluginPath;
	}

	/**
	 * @return int
	 */
	public function getViewDistance() {
		return 96;
	}

	/**
	 * @return string
	 */
	public function getServerName() {
		return $this->getConfigString("motd", "Extropy Server");
	}

	/**
	 * @return bool
	 */
	public function getAutoGenerate() {
		return $this->autoGenerate;
	}

	/**
	 * @param bool $value
	 */
	public function setAutoGenerate($value) {
		$this->autoGenerate = (bool)$value;
	}

	/**
	 * @return string
	 */
	public function getLevelType() {
		return $this->getConfigString("level-type", "DEFAULT");
	}

	/**
	 * @return bool
	 */
	public function getGenerateStructures() {
		return $this->getConfigBoolean("generate-structures", true);
	}

	/**
	 * @return bool
	 */
	public function getForceGamemode() {
		return $this->getConfigBoolean("force-gamemode", false);
	}

	/**
	 * @return int
	 *
	 * @deprecated true
	 */
	public function getSpawnRadius() {
		return 0;
	}

	/**
	 * @return bool
	 */
	public function getAllowFlight() {
		return $this->getConfigBoolean("allow-flight", false);
	}

	/**
	 * @return mixed
	 */
	public function shouldCheckMovement() {
		return $this->getAdvancedProperty("main.check-movement", false);
	}

	/**
	 * @return bool
	 */
	public function isHardcore() {
		return $this->getConfigBoolean("hardcore", false);
	}

	/**
	 * @return int
	 */
	public function getDefaultGamemode() {
		return $this->getConfigInt("gamemode", 0) & 0b11;
	}

	/**
	 * @return string
	 */
	public function getMotd() {
		return $this->getConfigString("motd", "Minecraft: PE Server");
	}

	/**
	 * @return EntityMetadataStore
	 */
	public function getEntityMetadata() {
		return $this->entityMetadata;
	}

	/**
	 * @return PlayerMetadataStore
	 */
	public function getPlayerMetadata() {
		return $this->playerMetadata;
	}

	/**
	 * @return LevelMetadataStore
	 */
	public function getLevelMetadata() {
		return $this->levelMetadata;
	}

	/**
	 * @return int
	 */
	public function getTick() {
		return $this->tickCounter;
	}

	/**
	 * @deprecated
	 *
	 * @param $address
	 * @param $port
	 * @param $payload
	 */
	public function sendPacket($address, $port, $payload) {
		$this->network->sendPacket($address, $port, $payload);
	}

	/**
	 * @deprecated
	 *
	 * @return SourceInterface[]
	 */
	public function getInterfaces() {
		return $this->network->getInterfaces();
	}

	/**
	 * @deprecated
	 *
	 * @param SourceInterface $interface
	 */
	public function removeInterface(SourceInterface $interface) {
		$interface->shutdown();
		$this->network->unregisterInterface($interface);
	}

	/**
	 * @return SimpleCommandMap
	 */
	public function getCommandMap() {
		return $this->commandMap;
	}

	public function addRecipe(Recipe $recipe) {
		$this->craftingManager->registerRecipe($recipe);
	}

	/**
	 * @param string $name
	 *
	 * @return OfflinePlayer|Player
	 */
	public function getOfflinePlayer($name) {
		$name = strtolower($name);
		$result = $this->getPlayerExact($name);

		if($result === null) {
			$result = new OfflinePlayer($this, $name);
		}

		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return Player
	 */
	public function getPlayerExact($name) {
		$name = strtolower($name);
		foreach($this->getOnlinePlayers() as $player) {
			if(strtolower($player->getName()) === $name) {
				return $player;
			}
		}

		return null;
	}

	/**
	 * @param string $name
	 *
	 * @return CompoundTag
	 */
	public function getOfflinePlayerData($name) {
		$name = strtolower($name);
		//		$path = $this->getDataPath() . "players/";
		//		if(file_exists($path . "$name.dat")){
		//			try{
		//				$nbt = new NBT(NBT::BIG_ENDIAN);
		//				$nbt->readCompressed(file_get_contents($path . "$name.dat"));
		//
		//				return $nbt->getData();
		//			}catch(\Exception $e){ //zlib decode error / corrupt data
		//				rename($path . "$name.dat", $path . "$name.dat.bak");
		//				$this->logger->warning("Corrupted data found for \"" . $name . "\", creating new profile");
		//			}
		//		}else{
		//			$this->logger->notice("Player data not found for \"" . $name . "\", creating new profile");
		//		}
		$spawn = $this->getDefaultLevel()->getSafeSpawn();
		$nbt = new CompoundTag("", [new LongTag("firstPlayed", floor(microtime(true) * 1000)), new LongTag("lastPlayed", floor(microtime(true) * 1000)), new ListTag("Pos", [new DoubleTag(0, $spawn->x), new DoubleTag(1, $spawn->y), new DoubleTag(2, $spawn->z)]), new StringTag("Level", $this->getDefaultLevel()->getName()), //new StringTag("SpawnLevel", $this->getDefaultLevel()->getName()),
			//new IntTag("SpawnX", (int) $spawn->x),
			//new IntTag("SpawnY", (int) $spawn->y),
			//new IntTag("SpawnZ", (int) $spawn->z),
			//new ByteTag("SpawnForced", 1), //TODO
			new ListTag("Inventory", []), new CompoundTag("Achievements", []), new IntTag("playerGameType", $this->getGamemode()), new ListTag("Motion", [new DoubleTag(0, 0.0), new DoubleTag(1, 0.0), new DoubleTag(2, 0.0)]), new ListTag("Rotation", [new FloatTag(0, 0.0), new FloatTag(1, 0.0)]), new FloatTag("FallDistance", 0.0), new ShortTag("Fire", 0), new ShortTag("Air", 300), new ByteTag("OnGround", 1), new ByteTag("Invulnerable", 0), new StringTag("NameTag", $name),]);
		$nbt->Pos->setTagType(NBT::TAG_Double);
		$nbt->Inventory->setTagType(NBT::TAG_Compound);
		$nbt->Motion->setTagType(NBT::TAG_Double);
		$nbt->Rotation->setTagType(NBT::TAG_Float);

		// $this->saveOfflinePlayerData($name, $nbt);

		return $nbt;

	}

	/**
	 * @param string $name
	 * @param CompoundTag $nbtTag
	 */
	public function saveOfflinePlayerData($name, CompoundTag $nbtTag, $async = false) {
		//			$nbt = new NBT(NBT::BIG_ENDIAN);
		//		try{
		//			$nbt->setData($nbtTag);
		//			if($async){
		//				$this->getScheduler()->scheduleAsyncTask(new FileWriteTask($this->getDataPath() . "players/" . strtolower($name) . ".dat", $nbt->writeCompressed()));
		//			}else{
		//				file_put_contents($this->getDataPath() . "players/" . strtolower($name) . ".dat", $nbt->writeCompressed());
		//			}
		//		}catch(\Exception $e){
		//			$this->logger->critical($this->getLanguage()->translateString("pocketmine.data.saveError", [$name, $e->getMessage()]));
		//			if(\pocketmine\DEBUG > 1 and $this->logger instanceof MainLogger){
		//				$this->logger->logException($e);
		//			}
		//		}
		return false;
	}

	/**
	 * @param string $name
	 *
	 * @return Player
	 */
	public function getPlayer($name) {
		$found = null;
		$name = strtolower($name);
		$delta = PHP_INT_MAX;
		foreach($this->getOnlinePlayers() as $player) {
			if(stripos($player->getName(), $name) === 0) {
				$curDelta = strlen($player->getName()) - strlen($name);
				if($curDelta < $delta) {
					$found = $player;
					$delta = $curDelta;
				}
				if($curDelta === 0) {
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * @param string $partialName
	 *
	 * @return Player[]
	 */
	public function matchPlayer($partialName) {
		$partialName = strtolower($partialName);
		$matchedPlayers = [];
		foreach($this->getOnlinePlayers() as $player) {
			if(strtolower($player->getName()) === $partialName) {
				$matchedPlayers = [$player];
				break;
			} elseif(stripos($player->getName(), $partialName) !== false) {
				$matchedPlayers[] = $player;
			}
		}

		return $matchedPlayers;
	}

	/**
	 * @param int $levelId
	 *
	 * @return Level
	 */
	public function getLevel($levelId) {
		if(isset($this->levels[$levelId])) {
			return $this->levels[$levelId];
		}

		return null;
	}

	/**
	 * @param string $variable
	 * @param bool $value
	 */
	public function setConfigBool($variable, $value) {
		$this->properties->set($variable, $value == true ? "1" : "0");
	}

	/**
	 * @param string $name
	 *
	 * @return PluginIdentifiableCommand
	 */
	public function getPluginCommand($name) {
		if(($command = $this->commandMap->getCommand($name)) instanceof PluginIdentifiableCommand) {
			return $command;
		} else {
			return null;
		}
	}

	/**
	 * @return BanList
	 */
	public function getNameBans() {
		return $this->banByName;
	}

	/**
	 * @param string $name
	 */
	public function addOp($name) {
		$this->operators->set(strtolower($name), true);

		if(($player = $this->getPlayerExact($name)) instanceof Player) {
			$player->recalculatePermissions();
		}
		$this->operators->save();
	}

	/**
	 * @param string $name
	 */
	public function removeOp($name) {
		$this->operators->remove(strtolower($name));

		if(($player = $this->getPlayerExact($name)) instanceof Player) {
			$player->recalculatePermissions();
		}
		$this->operators->save();
	}

	/**
	 * @param string $name
	 */
	public function addWhitelist($name) {
		$this->whitelist->set(strtolower($name), true);
		$this->whitelist->save();
	}

	/**
	 * @param string $name
	 */
	public function removeWhitelist($name) {
		$this->whitelist->remove(strtolower($name));
		$this->whitelist->save();
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isWhitelisted($name) {
		return !$this->hasWhitelist() or $this->operators->exists($name, true) or $this->whitelist->exists($name, true);
	}

	//	public function broadcastPacketsCallback($data, array $identifiers){
	//		$pk = new BatchPacket();
	//		$pk->payload = $data;
	//		$pk->encode();
	//		$pk->isEncoded = true;
	//
	//		foreach($identifiers as $i){
	//			if(isset($this->players[$i])){
	//				$this->players[$i]->dataPacket($pk);
	//			}
	//		}
	//	}

	/**
	 * @return bool
	 */
	public function hasWhitelist() {
		return $this->getConfigBoolean("white-list", false);
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isOp($name) {
		return $this->operators->exists($name, true);
	}

	/**
	 * @return Config
	 */
	public function getWhitelisted() {
		return $this->whitelist;
	}

	/**
	 * @return Config
	 */
	public function getOps() {
		return $this->operators;
	}

	/**
	 * @return string[]
	 */
	public function getCommandAliases() {
		$section = $this->getProperty("aliases");
		$result = [];
		if(is_array($section)) {
			foreach($section as $key => $value) {
				$commands = [];
				if(is_array($value)) {
					$commands = $value;
				} else {
					$commands[] = $value;
				}

				$result[$key] = $commands;
			}
		}

		return $result;
	}

	/**
	 * @param string $message
	 * @param Player[]|null $recipients
	 *
	 * @return int
	 */
	public function broadcastMessage($message, $recipients = null) {
		if(!is_array($recipients)) {
			return $this->broadcast($message, self::BROADCAST_CHANNEL_USERS);
		}

		/** @var Player[] $recipients */
		foreach($recipients as $recipient) {
			$recipient->sendMessage($message);
		}

		return count($recipients);
	}

	/**
	 * @param string $message
	 * @param string $permissions
	 *
	 * @return int
	 */
	public function broadcast($message, $permissions) {
		/** @var CommandSender[] $recipients */
		$recipients = [];
		foreach(explode(";", $permissions) as $permission) {
			foreach($this->pluginManager->getPermissionSubscriptions($permission) as $permissible) {
				if($permissible instanceof CommandSender and $permissible->hasPermission($permission)) {
					$recipients[spl_object_hash($permissible)] = $permissible; // do not send messages directly, or some might be repeated
				}
			}
		}

		foreach($recipients as $recipient) {
			$recipient->sendMessage($message);
		}

		return count($recipients);
	}

	/**
	 * @param string $tip
	 * @param Player[]|null $recipients
	 *
	 * @return int
	 */
	public function broadcastTip($tip, $recipients = null) {
		if(!is_array($recipients)) {
			/** @var Player[] $recipients */
			$recipients = [];

			foreach($this->pluginManager->getPermissionSubscriptions(self::BROADCAST_CHANNEL_USERS) as $permissible) {
				if($permissible instanceof Player and $permissible->hasPermission(self::BROADCAST_CHANNEL_USERS)) {
					$recipients[spl_object_hash($permissible)] = $permissible; // do not send messages directly, or some might be repeated
				}
			}
		}

		/** @var Player[] $recipients */
		foreach($recipients as $recipient) {
			$recipient->sendTip($tip);
		}

		return count($recipients);
	}

	/**
	 * @param string $popup
	 * @param Player[]|null $recipients
	 *
	 * @return int
	 */
	public function broadcastPopup($popup, $recipients = null) {
		if(!is_array($recipients)) {
			/** @var Player[] $recipients */
			$recipients = [];

			foreach($this->pluginManager->getPermissionSubscriptions(self::BROADCAST_CHANNEL_USERS) as $permissible) {
				if($permissible instanceof Player and $permissible->hasPermission(self::BROADCAST_CHANNEL_USERS)) {
					$recipients[spl_object_hash($permissible)] = $permissible; // do not send messages directly, or some might be repeated
				}
			}
		}

		/** @var Player[] $recipients */
		foreach($recipients as $recipient) {
			$recipient->sendPopup($popup);
		}

		return count($recipients);
	}

	/**
	 * @param Plugin $plugin
	 *
	 * @deprecated
	 */
	public function loadPlugin(Plugin $plugin) {
		$this->enablePlugin($plugin);
	}

	public function disablePlugins() {
		$this->pluginManager->disablePlugins();
	}

	public function reload() {
		$this->logger->info("Saving levels...");

		foreach($this->levels as $level) {
			$level->save();
		}

		$this->pluginManager->disablePlugins();
		$this->pluginManager->clearPlugins();
		$this->commandMap->clearCommands();

		$this->logger->info("Reloading properties...");
		$this->properties->reload();
		$this->maxPlayers = $this->getConfigInt("max-players", 20);

		if(($memory = str_replace("B", "", strtoupper($this->getConfigString("memory-limit", "256M")))) !== false) {
			$value = ["M" => 1, "G" => 1024];
			$real = ((int)substr($memory, 0, -1)) * $value[substr($memory, -1)];
			if($real < 256) {
				$this->logger->warning($this->getName() . " may not work right with less than 256MB of RAM");
			}
			@ini_set("memory_limit", $memory);
		} else {
			$this->setConfigString("memory-limit", "256M");
		}

		if($this->getConfigBoolean("hardcore", false) === true and $this->getDifficulty() < 3) {
			$this->setConfigInt("difficulty", 3);
		}

		$this->banByIP->load();
		$this->banByName->load();
		$this->reloadWhitelist();
		$this->operators->reload();

		foreach($this->getIPBans()->getEntries() as $entry) {
			$this->blockAddress($entry->getName(), -1);
		}

		$this->pluginManager->registerInterface(PharPluginLoader::class);
		$this->pluginManager->loadPlugins($this->pluginPath);
		$this->enablePlugins(PluginLoadOrder::STARTUP);
		$this->enablePlugins(PluginLoadOrder::POSTWORLD);
		TimingsHandler::reload();
	}

	public function reloadWhitelist() {
		$this->whitelist->reload();
	}

	/**
	 * @deprecated
	 *
	 * @param     $address
	 * @param int $timeout
	 */
	public function blockAddress($address, $timeout = 300) {
		$this->network->blockAddress($address, $timeout);
	}

	public function handleSignal($signo) {
		if($signo === SIGTERM or $signo === SIGINT or $signo === SIGHUP) {
			$this->shutdown();
		}
	}

	public function checkTicks() {
		if($this->getTicksPerSecond() < 12) {
			$this->logger->warning("Can't keep up! Is the server overloaded?");
		}
	}

	public function exceptionHandler(\Throwable $e, $trace = null) {
		if($e === null) {
			return;
		}

		global $lastError;

		if($trace === null) {
			$trace = $e->getTrace();
		}

		$errstr = $e->getMessage();
		$errfile = $e->getFile();
		$errno = $e->getCode();
		$errline = $e->getLine();

		$type = ($errno === E_ERROR or $errno === E_USER_ERROR) ? \LogLevel::ERROR : (($errno === E_USER_WARNING or $errno === E_WARNING) ? \LogLevel::WARNING : \LogLevel::NOTICE);
		if(($pos = strpos($errstr, "\n")) !== false) {
			$errstr = substr($errstr, 0, $pos);
		}

		$errfile = cleanPath($errfile);

		if($this->logger instanceof MainLogger) {
			$this->logger->logException($e, $trace);
		}

		$lastError = ["type" => $type, "message" => $errstr, "fullFile" => $e->getFile(), "file" => $errfile, "line" => $errline, "trace" => @getTrace(1, $trace)];

		global $lastExceptionError, $lastError;
		$lastExceptionError = $lastError;
		$this->crashDump();
	}

	public function crashDump() {
		if(!$this->isRunning) return;
		$this->isRunning = false;
		$this->hasStopped = false;

		ini_set("error_reporting", 0);
		ini_set("memory_limit", -1); //Fix error dump not dumped on memory problems
		$this->logger->emergency("An unrecoverable error has occurred and the server has crashed. Creating a crash dump");
		try {
			$dump = new CrashDump($this);
		} catch(\Exception $e) {
			$this->logger->critical("Could not create Crash Dump: " . $e->getMessage());
			return;
		}

		$this->logger->emergency("Please submit the \"" . $dump->getPath() . "\" file to the Bug Reporting page. Give as much info as you can.");

		//$this->checkMemory();
		//$dump .= "Memory Usage Tracking: \r\n" . chunk_split(base64_encode(gzdeflate(implode(";", $this->memoryStats), 9))) . "\r\n";

		$this->forceShutdown();
		@kill(getmypid());
		exit(1);
	}

	public function __debugInfo() {
		return [];
	}

	public function addOnlinePlayer(Player $player) {
		$this->updatePlayerListData($player->getUniqueId(), $player->getId(), $player->getDisplayName(), $player->getSkinName(), $player->getSkinData());
		$this->playerList[$player->getRawUniqueId()] = $player;
	}

	public function updatePlayerListData(UUID $uuid, $entityId, $name, $skinName, $skinData, array $players = null) {
		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_ADD;
		$pk->entries[] = [$uuid, $entityId, $name, $skinName, $skinData];
		foreach($players === null ? $this->playerList : $players as $p) {
			$p->dataPacket($pk);
		}
	}

	public function removeOnlinePlayer(Player $player) {
		if(isset($this->playerList[$player->getRawUniqueId()])) {
			unset($this->playerList[$player->getRawUniqueId()]);

			$pk = new PlayerListPacket();
			$pk->type = PlayerListPacket::TYPE_REMOVE;
			$pk->entries[] = [$player->getUniqueId()];
			Server::broadcastPacket($this->playerList, $pk);
		}
	}

	/**
	 * Broadcasts a Minecraft packet to a list of players
	 *
	 * @param Player[] $players
	 * @param DataPacket $packet
	 */
	public static function broadcastPacket(array $players, DataPacket $packet) {
		foreach($players as $player) {
			$player->dataPacket($packet);
		}
		if(isset($packet->__encapsulatedPacket)) {
			unset($packet->__encapsulatedPacket);
		}
	}

	public function removePlayerListData(UUID $uuid, array $players = null) {
		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_REMOVE;
		$pk->entries[] = [$uuid];
		foreach($players === null ? $this->playerList : $players as $p) {
			$p->dataPacket($pk);
		}
	}

	public function sendFullPlayerListData(Player $p) {
		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_ADD;
		foreach($this->playerList as $player) {
			if($player !== $p) {
				$pk->entries[] = [$player->getUniqueId(), $player->getId(), $player->getDisplayName(), $player->getSkinName(), $player->getSkinData()];
			}
		}

		$p->dataPacket($pk);
	}

	/**
	 * @return CraftingManager
	 */
	public function getCraftingManager() {
		return $this->craftingManager;
	}

	/**
	 * Broadcasts a list of packets in a batch to a list of players
	 *
	 * @param Player[] $players
	 * @param DataPacket[]|string $packets
	 * @param bool $forceSync
	 */
	public function batchPackets(array $players, array $packets, $forceSync = true) {
		$targets = [];
		foreach($players as $p) {
			$targets[] = [$p->getIdentifier()];
		}
		$newPackets = [];
		foreach($packets as $p) {
			if($p instanceof DataPacket) {
				if(!$p->isEncoded) {
					$p->encode();
				}
				$newPackets[] = $p->buffer;
			} else {
				$newPackets[] = $p;
			}
		}
		$data = [];
		$data['packets'] = $newPackets;
		$data['targets'] = $targets;
		$data['networkCompressionLevel'] = $this->networkCompressionLevel;
		$data['isBatch'] = true;
		$this->packetMaker->pushMainToThreadPacket(serialize($data));
	}

	public function addPlayer($identifier, Player $player) {
		$this->players[$identifier] = $player;
		$this->identifiers[spl_object_hash($player)] = $identifier;
	}

	public function doAutoSave() {
		if($this->getAutoSave()) {
			foreach($this->getOnlinePlayers() as $index => $player) {
				if($player->isOnline()) {
					$player->save();
				} elseif(!$player->isConnected()) {
					$this->removePlayer($player);
				}
			}

			foreach($this->getLevels() as $level) {
				$level->save(false);
			}
		}
	}

	/**
	 * @param Player $player
	 */
	public function removePlayer(Player $player) {
		if(isset($this->identifiers[$hash = spl_object_hash($player)])) {
			$identifier = $this->identifiers[$hash];
			unset($this->players[$identifier]);
			unset($this->identifiers[$hash]);

			return;
		}

		foreach($this->players as $identifier => $p) {
			if($player === $p) {
				unset($this->players[$identifier]);
				unset($this->identifiers[spl_object_hash($player)]);
				break;
			}
		}
	}

	public function doLevelGC() {
		foreach($this->getLevels() as $level) {
			$level->doChunkGarbageCollection();
		}
	}

	/**
	 * @param string $address
	 * @param int $port
	 * @param string $payload
	 *
	 * TODO: move this to Network
	 */
	public function handlePacket($address, $port, $payload) {
		try {
			if(strlen($payload) > 2 and substr($payload, 0, 2) === "\xfe\xfd" and $this->queryHandler instanceof QueryHandler) {
				$this->queryHandler->handle($address, $port, $payload);
			}
		} catch(\Exception $e) {
			if(\pocketmine\DEBUG > 1) {
				if($this->logger instanceof MainLogger) {
					$this->logger->logException($e);
				}
			}

			$this->getNetwork()->blockAddress($address, 600);
		}
		//TODO: add raw packet events
	}

	/**
	 * @return Network
	 */
	public function getNetwork() {
		return $this->network;
	}

	public function shufflePlayers() {
		if(count($this->players) <= 1) return;

		$keys = array_keys($this->players);
		shuffle($keys);
		$random = [];
		foreach($keys as $key) $random[$key] = $this->players[$key];

		$this->players = $random;
	}
}
