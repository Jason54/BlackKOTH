<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Events;

use BlackTeam\BlackKOTH\Main;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;

class ArenaCreateEvent extends KothEvent{

    private $creator;

    private $name;
    private $world;

    private $min_players;
    private $max_players;
    private $game_time;

    private $hill;
    private $spawns;
    private $rewards;

    public function __construct(Main $plugin, $creator, string $name, int $min_players, int $max_players, int $gameTime, array $hill = [], array $spawns = [], array $rewards = [], string $world = "null"){
        $this->creator = $creator;
        $this->name = $name;
        $this->min_players = $min_players;
        $this->max_players = $max_players;
        $this->game_time = $gameTime;
        $this->hill = $hill;
        $this->spawns = $spawns;
        $this->rewards = $rewards;
        $this->world = $world;
        parent::__construct($plugin);
    }

    public function getCreator(){
        return $this->creator;
    }

    public function getName() : string{
        return $this->name;
    }

    public function setName(string $name) : void{
        $this->name = $name;
    }

    public function getMinPlayers() : int{
        return $this->min_players;
    }

    public function setMinPlayers(int $amount) : void{
        $this->min_players = $amount;
    }

    public function getMaxPlayers() : int{
        return $this->max_players;
    }

    public function setMaxPlayers(int $amount) : void{
        $this->max_players = $amount;
    }

    public function getGameTime() : int{
        return $this->game_time;
    }

    public function setGameTime(int $amount) : void{
        $this->game_time = $amount;
    }

    public function getHillPositions() : array{
        return $this->hill;
    }

    public function setHillPositions(array $hill) : void{
        $this->hill = $hill;
    }

    public function getSpawnPositions() : array{
        return $this->spawns;
    }

    public function setSpawnPositions(array $spawns) : void{
        $this->spawns = $spawns;
    }

    public function getRewards() : array{
        return $this->rewards;
    }

    public function setRewards(array $rewards) : void{
        $this->rewards = $rewards;
    }

    public function getWorld() : string{
        return $this->world;
    }

    public function setWorld(string $worldName) : void{
        $this->world = $worldName;
    }
}