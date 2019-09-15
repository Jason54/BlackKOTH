<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Tasks;

use pocketmine\scheduler\Task;

use BlackTeam\BlackKOTH\Main;
use BlackTeam\BlackKOTH\Arena;

class Prestart extends Task{

    private $plugin;

    private $arena;

    private $countDown;
    private $serverBcast;

    public function __construct(Main $plugin, Arena $arena, int $count){
        $this->plugin = $plugin;
        $this->arena = $arena;
        $this->countDown = $count;
        $this->serverBcast = $plugin->config["countdown_bcast_serverwide"] === true;
    }

    public function onRun(int $tick){
        if($this->countDown === 0){
            $this->arena->startGame();
            return;
        }
        if($this->plugin->config["countdown_bcast"] === true) {
            $msg = str_replace(["{COUNT}","{ARENA}"],[$this->countDown, $this->arena->getName()], $this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["countdown"]));
            if ($this->countDown <= 5) {
                if(!$this->serverBcast){
                    $this->arena->broadcastMessage($msg);
                } else{
                    $this->plugin->getServer()->broadcastMessage($msg);
                }
            } else {
                if (($this->countDown % $this->plugin->config["countdown_bcast_interval"]) === 0) {
                    if(!$this->serverBcast){
                        $this->arena->broadcastMessage($msg);
                    } else {
                        $this->plugin->getServer()->broadcastMessage($msg);
                    }
                }
            }
        }
        $this->countDown--;
    }
}