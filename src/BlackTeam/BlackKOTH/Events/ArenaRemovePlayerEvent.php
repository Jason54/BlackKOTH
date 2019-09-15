<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Events;

use BlackTeam\BlackKOTH\Arena;
use BlackTeam\BlackKOTH\Main;
use pocketmine\Player;

class ArenaRemovePlayerEvent extends KothEvent{

    private $arena;

    private $player;

    private $leaveReason;

    private $silent;

    public function __construct(Main $plugin, Arena $arena, Player $player, string $leaveReason, bool $silent){
        $this->arena = $arena;
        $this->player = $player;
        $this->silent = $silent;
        $this->leaveReason = $leaveReason;
        parent::__construct($plugin);
    }

    public function setSilent(bool $silent): void{
        $this->silent = $silent;
    }

    public function isSilent(): bool{
        return $this->silent;
    }

    public function getLeaveReason(): string{
        return $this->leaveReason;
    }

    public function setLeaveReason(string $leaveReason): void{
        $this->leaveReason = $leaveReason;
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