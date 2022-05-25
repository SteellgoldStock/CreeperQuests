<?php

namespace steellgold\creeperquest;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\context\ClosureContext;
use pocketmine\block\BlockLegacyIds;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;

class EventListener implements Listener {

	public array $interacts = [];

	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		if(!isset(CQ::getInstance()->players[$player->getName()])){
			CQ::getInstance()->players[$player->getName()] = [];
		}
	}

	public function onPlaceHead(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		if (!$player->getServer()->isOp($player->getName())) return;

		$worldFolderName = $event->getBlock()->getPosition()->getWorld()->getId();
		$position = $event->getBlock()->getPosition();

		if(!isset(CQ::getInstance()->data[$worldFolderName])){
			CQ::getInstance()->data[$worldFolderName] = [];
			$player->sendMessage("Le monde {$worldFolderName} n'existe pas, création du monde dans le fichier de données.");
		}

		if ($event->getBlock()->getId() === BlockLegacyIds::MOB_HEAD_BLOCK && $event->getBlock()->getMeta() == 1) {
			if (!isset(CQ::getInstance()->data[$worldFolderName]["{$position->getX()},{$position->getY()},{$position->getZ()}"])) {
				CQ::getInstance()->data[$worldFolderName][] = "{$position->getX()},{$position->getY()},{$position->getZ()}";
				$player->sendMessage(CQ::getInstance()->getConfig()->get("messages")["placed"]);
			}
		}
	}

	public function onBreakHead(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		if (!$player->getServer()->isOp($player->getName())) return;

		$worldFolderName = $event->getBlock()->getPosition()->getWorld()->getId();
		$position = $event->getBlock()->getPosition();

		if(!isset(CQ::getInstance()->data[$worldFolderName])){
			CQ::getInstance()->data[$worldFolderName] = [];
			$player->sendMessage("Le monde {$worldFolderName} n'existe pas, création du monde dans le fichier de données.");
		}

		if ($event->getBlock()->getId() === BlockLegacyIds::MOB_HEAD_BLOCK && $event->getBlock()->getMeta() == 1) {
			if (in_array("{$position->getX()},{$position->getY()},{$position->getZ()}",CQ::getInstance()->data[$worldFolderName])) {
				$player->sendMessage(CQ::getInstance()->getConfig()->get("messages")["break"]);
				unset(CQ::getInstance()->data[$worldFolderName][array_search("{$position->getX()},{$position->getY()},{$position->getZ()}",CQ::getInstance()->data[$worldFolderName])]);
			}
		}
	}

	public function onInteract(PlayerInteractEvent $event) {
		$player = $event->getPlayer();
		$worldFolderName = $event->getBlock()->getPosition()->getWorld()->getId();
		$position = $event->getBlock()->getPosition();

		if(!isset(CQ::getInstance()->data[$worldFolderName])){
			CQ::getInstance()->data[$worldFolderName] = [];
			$player->sendMessage("Le monde {$worldFolderName} n'existe pas, création du monde dans le fichier de données.");
		}

		if ($event->getBlock()->getId() === BlockLegacyIds::MOB_HEAD_BLOCK && $event->getBlock()->getMeta() == 1) {
			if (!in_array("{$position->getX()},{$position->getY()},{$position->getZ()}",CQ::getInstance()->data[$worldFolderName])) return;
			if (in_array("{$position->getX()},{$position->getY()},{$position->getZ()}",CQ::getInstance()->players[$player->getName()])) {
				$player->sendMessage(CQ::getInstance()->getConfig()->get("messages")["already_found"]);
				return;
			}

			CQ::getInstance()->players[$player->getName()][] = "{$position->getX()},{$position->getY()},{$position->getZ()}";
			if (count(CQ::getInstance()->players[$player->getName()]) == count(CQ::getInstance()->data[$worldFolderName])) {
				$player->sendMessage(str_replace("{world}", $worldFolderName, CQ::getInstance()->getConfig()->get("messages")["found_all"]));

				if (isset(CQ::getInstance()->getConfig()->get("rewards")[$worldFolderName])) {
					switch (CQ::getInstance()->getConfig()->get("rewards")[$worldFolderName]["type"]) {
						case "item":
							$info = CQ::getInstance()->getConfig()->get("rewards")[$worldFolderName];
							$player->getInventory()->addItem(ItemFactory::getInstance()->get($info["id"], $info["meta"], $info["count"]));
							$player->sendMessage(str_replace([
								"{amount}",
								"{id}",
								"{meta}",
								"{name}"
							], [
								$info["amount"],
								$info["id"],
								$info["meta"],
								ItemFactory::getInstance()->get($info["id"], $info["meta"])->getName()
							], CQ::getInstance()->getConfig()->get("messages")["reward_item"]));
							break;
						case "money":
							$info = CQ::getInstance()->getConfig()->get("rewards")[$worldFolderName];

							BedrockEconomyAPI::legacy()->isAccountExists(
								$player->getName(),
								ClosureContext::create(
									function (bool $hasAccount) use ($player, $info): void {
										if ($hasAccount) {
											BedrockEconomyAPI::legacy()->addToPlayerBalance(
												$player->getName(),
												$info['amount'],
												ClosureContext::create(
													function (bool $wasUpdated) use ($player, $info): void {
														if ($wasUpdated) $player->sendMessage(str_replace("{amount}", $info['amount'], CQ::getInstance()->getConfig()->get("messages")["reward_money"]));
													},
												)
											);
										} else {
											$player->sendMessage("§cAucun compte pour: §f{$player->getName()}");
											$this->interacts[$player->getName()] = time() + 3;
										}
									},
								)
							);
							break;
					}
					$this->interacts[$player->getName()] = time() + 3;
				}
			} else {
				$player->sendMessage(str_replace("{remaining}", (count(CQ::getInstance()->data[$worldFolderName]) - count(CQ::getInstance()->players[$player->getName()])), CQ::getInstance()->getConfig()->get("messages")["found"]));
				$this->interacts[$player->getName()] = time() + 3;
			}
		}
	}
}