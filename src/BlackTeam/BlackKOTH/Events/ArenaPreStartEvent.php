<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Events;

use BlackTeam\BlackKOTH\Arena;
use BlackTeam\BlackKOTH\Main;

class ArenaPreStartEvent extends KothEvent{

    private $arena;

    public $countdown;

    public function __construct(Main $plugin, Arena $arena){
        $this->arena = $arena;
        $this->countdown = $arena->countDown;
        parent::__construct($plugin);
    }

    public function getCountdown(): int{
        return $this->countdown;
    }

    public function setCountdown(int $countdown): void{
        $this->countdown = $countdown;
    }

    public function getArena(): Arena{
        return $this->arena;
    }
}