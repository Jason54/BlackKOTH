<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Tasks;

use pocketmine\scheduler\Task;

use BlackTeam\BlackKOTH\Arena;

class Gametimer extends Task{
    private $arena;

    public $secondsLeft;


    public function __construct(Arena $arena){
        $this->arena = $arena;
        $this->secondsLeft = $arena->time;
    }

    public function onRun(int $tick){
        $this->secondsLeft -= 0.5;
        $inBox = $this->arena->playersInBox();
        if($this->arena->king === null){
            $this->arena->checkNewKing();
        } else {
            if (!in_array($this->arena->king, $inBox)) {
                $this->arena->removeKing();
            }
        }

        if($this->secondsLeft <= 0){
            $this->arena->endGame();
        }
    }
}