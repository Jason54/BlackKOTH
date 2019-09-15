<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Events;

use BlackTeam\BlackKOTH\Arena;
use BlackTeam\BlackKOTH\Main;

class ArenaEndEvent extends KothEvent{

    private $arena;

    private $secondsLeft;

    public function __construct(Main $plugin, Arena $arena){
        $this->arena = $arena;
        $this->secondsLeft = $arena->time;
        parent::__construct($plugin);
    }

    public function getArena(): Arena{
        return $this->arena;
    }

    public function getSecondsLeft(): int
    {
        return $this->secondsLeft;
    }

    public function setSecondsLeft(int $seconds): void{
        $this->secondsLeft = $seconds;
    }
}