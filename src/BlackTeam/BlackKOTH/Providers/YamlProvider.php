<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Providers;

use BlackTeam\BlackKOTH\{Main,Arena};
use pocketmine\utils\Config;


class YamlProvider implements BaseProvider{

    private $plugin;

    public $dataConfig;

    public $data;

    private $version = 0;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function getName() : string{
        return "Yaml";
    }

    public function open() : void
    {
        $this->dataConfig = new Config($this->plugin->getDataFolder() . "arena.yml", Config::YAML, ["version" => $this->version, "arena_list" => []]);
        $this->data = $this->dataConfig->getAll();
        $this->plugin->debug("Arena data file opened.");
    }

    public function close() : void
    {
        unset($this->data);
        unset($this->dataConfig);
        $this->plugin->debug("Arena data file closed.");
    }

    public function save(): void
    {
        $this->dataConfig->setAll($this->data);
        $this->dataConfig->save();
    }

    public function createArena(Arena $arena) : void{
        $this->data["arena_list"][] = [
            "name" => strtolower($arena->getName()),
            "min_players" => $arena->minPlayers,
            "max_players" => $arena->maxPlayers,
            "play_time" => $arena->time,
            "hill" => $arena->hill,
            "spawns" => $arena->spawns,
            "rewards" => $arena->rewards,
            "world" => $arena->world
        ];
        $this->save();
    }

    public function updateArena(Arena $arena) : void{
        $key = 0;
        if(count($this->data["arena_list"])==0) return;
        while(count($this->data["arena_list"])-1 != $key){
            if($this->data["arena_list"][$key]["name"] == strtolower($arena->getName())){
                $this->data["arena_list"][$key] = [
                    "name" => strtolower($arena->name),
                    "min_players" => $arena->minPlayers,
                    "max_players" => $arena->maxPlayers,
                    "play_time" => $arena->time,
                    "hill" => $arena->hill,
                    "spawns" => $arena->spawns,
                    "rewards" => $arena->rewards,
                    "world" => $arena->world
                ];
            }
            $key++;
        }
        $this->save();
    }

    public function deleteArena(string $arena) : void{
        $key = 0;
        if(count($this->data) === 0) return;
        while(count(array_keys($this->data))-1 !== $key){
            if($this->data["arena_list"][$key]["name"] == strtolower($arena)){
                unset($this->data["arena_list"][$key]);
            }
            $key++;
        }
        $this->save();
    }

    public function getDataVersion(): int
    {
        return $this->data["version"];
    }

    public function getAllData(): array
    {
        return $this->data["arena_list"];
    }

    public function setAllData(array $data): void
    {
        $this->data["arena_list"] = $data;
        $this->save();
    }
}