<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Events;

use BlackTeam\BlackKOTH\Arena;
use BlackTeam\BlackKOTH\Main;
use pocketmine\Player;

class ArenaAddPlayerEvent extends KothEvent{

    private $arena;

    private $player;

    public function __construct(Main $plugin, Arena $arena, Player $player){
        $this->arena = $arena;
        $this->player = $player;
        parent::__construct($plugin);
    }

    public function getArena(): Arena{
        return $this->arena;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): void{
        $this->player = $player;
    }
}