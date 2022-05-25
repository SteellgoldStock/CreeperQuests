<?php

namespace steellgold\creeperquest;

use JsonException;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;

class CQ extends PluginBase {

	public static CQ $instance;

	public array $players;

	public array $data;

	protected function onEnable(): void {
		self::$instance = $this;
		$this->saveResource("config.yml");

		$this->players = [];
		foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
			$this->data[$world->getFolderName()] = [];
			Server::getInstance()->getLogger()->info("Ajout du monde {$world->getFolderName()} [{$world->getId()}] dans la liste des mondes");
		}

		$data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
		foreach ($data->getAll() as $key => $value) {
			$this->data[$key] = $value;
		}

		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
	}

	/**
	 * @throws JsonException
	 */
	protected function onDisable(): void {
		$data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
		$player = new Config($this->getDataFolder() . "players.yml", Config::YAML);
		foreach ($this->data as $worldFolderName => $positions) {
			$data->set($worldFolderName, $positions);
			$data->save();
		}

		foreach ($this->players as $playerName => $positions) {
			$player->set($playerName, $positions);
			$player->save();
		}
	}

	public static function getInstance(): CQ {
		return self::$instance;
	}
}