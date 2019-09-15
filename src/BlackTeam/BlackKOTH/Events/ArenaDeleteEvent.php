<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Events;

use BlackTeam\BlackKOTH\Arena;
use BlackTeam\BlackKOTH\Main;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;

class ArenaDeleteEvent extends KothEvent{

    private $destroyer;

    private $arena;

    public function __construct(Main $plugin, $destroyer, Arena $arena){
        $this->destroyer = $destroyer;
        $this->arena = $arena;
        parent::__construct($plugin);
    }

    public function getDestroyer(){
        return $this->destroyer;
    }

    public function getArena() : Arena{
        return $this->arena;
    }
}