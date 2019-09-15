<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH;

use pocketmine\event\Listener;
use pocketmine\event\block\{BlockBreakEvent, BlockPlaceEvent};;
use pocketmine\event\player\{PlayerDeathEvent, PlayerRespawnEvent, PlayerQuitEvent, PlayerGameModeChangeEvent, PlayerCommandPreprocessEvent};;


class EventHandler implements Listener{

    private $plugin;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        if($this->plugin->inGame($playerName) === true){
            $this->plugin->debug($playerName." a quitté le jeu en informant l'arène...");
            $arena = $this->plugin->getArenaByPlayer($playerName);
            $arena->removePlayer($event->getPlayer(), "Déconnecté du serveur.");
        }
    }

    public function onRespawn(PlayerRespawnEvent $event){
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        if($this->plugin->inGame($playerName) === true){
            $this->plugin->debug($playerName." a été reproduit.");
            $event->setRespawnPosition($this->plugin->getArenaByPlayer($playerName)->getSpawn(true));
        }
    }

    public function onDeath(PlayerDeathEvent $event){
        $player = $event->getPlayer();
        if($this->plugin->inGame($player->getLowerCaseName()) === true and $this->plugin->config["keep_inventory"] === true){
            $this->plugin->debug($player->getLowerCaseName()." l'inventaire n'a pas été réinitialisé (décès)");
            $event->setKeepInventory(true);
        }
    }

    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
        $player = $event->getPlayer();
        if($this->plugin->inGame($player->getLowerCaseName()) === true and $this->plugin->config["block_commands"] === true and substr($event->getMessage(), 0, 5) !== "/koth"){
            $this->plugin->debug($player->getName()." essayé d'utiliser la commande '".$event->getMessage()."' mais a été annulé.");
            $event->setCancelled(true);
        }
    }

    public function onPlayerGameModeChange(PlayerGameModeChangeEvent $event){
        if($this->plugin->inGame($event->getPlayer()->getLowerCaseName()) === true){
            if($event->getPlayer()->isOp() === false and $this->plugin->config["prevent_gamemode_change"] === true){
                $this->plugin->debug($event->getPlayer()->getName()." a tenté de changer de mode de jeu mais a été arrêté.");
                $event->setCancelled(true);
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event){
        if($this->plugin->inGame($event->getPlayer()->getLowerCaseName()) === true and $this->plugin->config["prevent_break"] === true){
            $this->plugin->debug($event->getPlayer()->getName()." a tenté de casser un bloc mais a été arrêté.");
            $event->setCancelled(true);
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event){
        if($this->plugin->inGame($event->getPlayer()->getLowerCaseName()) === true and $this->plugin->config["prevent_place"] === true){
            $this->plugin->debug($event->getPlayer()->getName()." a tenté de placer un bloc mais a été arrêté.");
            $event->setCancelled(true);
        }
    }

}