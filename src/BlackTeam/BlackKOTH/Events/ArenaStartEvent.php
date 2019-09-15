<?php


declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Events;

use BlackTeam\BlackKOTH\Arena;
use BlackTeam\BlackKOTH\Main;

class ArenaStartEvent extends KothEvent{

    private $arena;

    public function __construct(Main $plugin, Arena $arena){
        $this->arena = $arena;
        parent::__construct($plugin);
    }

    public function getArena(): Arena{
        return $this->arena;
    }
}