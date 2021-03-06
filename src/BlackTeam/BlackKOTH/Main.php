<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH;

use BlackTeam\BlackKOTH\Providers\BaseProvider;
use BlackTeam\BlackKOTH\Providers\SqliteProvider;
use BlackTeam\BlackKOTH\Providers\YamlProvider;
use BlackTeam\BlackKOTH\Tasks\DownloadFile;
use BlackTeam\BlackKOTH\Tasks\GetUpdateInfo;
use BlackTeam\BlackKOTH\Utils as PluginUtils;

use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

class Main extends PluginBase implements Listener
{

    private static $instance;

    public const ARENA_VER = 2;
    public const CONFIG_VER = 1;
    public const MESSAGE_VER = 0;

    private $arenas = [];
    private $CommandHandler;
    private $EventHandler;
    private $configC;
    private $messagesC;
    /** @var BaseProvider */
    private $db;

    public $config;
    public $messages;
    public $prefix = C::YELLOW . "[" . C::AQUA . "KOTH" . C::YELLOW . "] " . C::RESET;

    /** @var Utils */
    public $utils;

    private function init(): void
    {
        $this->arenas = [];
        $this->loadArenas();
        $this->getServer()->getPluginManager()->registerEvents($this->EventHandler, $this);

        if ($this->config["check_updates"]) {
            $this->debug("Starting update check task...");
            $this->getServer()->getAsyncPool()->submitTask(new GetUpdateInfo($this, $this->config["update_check_url"]));
        }

        if (!$this->isPhar()){
            $this->getLogger()->warning("You are using source code which is heavily suggested NOT TO DO, please consider using production phar's pre built for you.");
        }
    }

    private function initResources(): void
    {
        $this->CommandHandler = new CommandHandler($this);
        $this->EventHandler = new EventHandler($this);
        $this->utils = new PluginUtils($this);

        $this->saveResource("config.yml");
        $this->configC = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->config = $this->configC->getAll();

        $this->saveResource("messages.yml");
        $this->messagesC = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $this->messages = $this->messagesC->getAll();

        if ($this->messages["version"] !== $this::MESSAGE_VER) {
            // Once updated insert here.
            $this->getLogger()->emergency("DO NOT MODIFY DATA THAT IS NOT MEANT TO BE MODIFIED, YOU HAVE BEEN WARNED.");
        }
        if ($this->config["version"] !== $this::CONFIG_VER) {
            if (isset($this->config["start_countdown"])){
                $this->config["countdown"] = $this->config["start_countdown"];
                unset($this->config["start_countdown"]);
            } else {
                if(!isset($this->config["countdown"])){
                    $this->config["countdown"] = 30;
                }
            }
            if (!isset($this->config["countdown_bcast"])) $this->config["countdown_bcast"] = true;
            if (!isset($this->config["countdown_bcast_interval"])) $this->config["countdown_bcast_interval"] = 5;
            if (!isset($this->config["countdown_bcast_serverwide"])) $this->config["countdown_bcast_serverwide"] = false;
            if (!isset($this->config["start_bcast_serverwide"])) $this->config["start_bcast_serverwide"] = false;
            if (!isset($this->config["end_bcast_serverwide"])) $this->config["end_bcast_serverwide"] = false;
            if (isset($this->config["language"])) unset($this->config["language"]);
            if (!isset($this->config["provider"])) $this->config["provider"] = "sqlite3";
            if (!isset($this->config["block_commands"])) $this->config["block_commands"] = true;
            if (!isset($this->config["prevent_place"])) $this->config["prevent_place"] = true;
            if (!isset($this->config["prevent_break"])) $this->config["prevent_break"] = true;
            if (!isset($this->config["prevent_gamemode_change"])) $this->config["prevent_gamemode_change"] = true;
            if (!isset($this->config["keep_inventory"])) $this->config["keep_inventory"] = true;
            if (!isset($this->config["show_updates"])) $this->config["show_updates"] = true;
            if (!isset($this->config["check_updates"])) $this->config["check_updates"] = true;
            if (!isset($this->config["download_updates"])) $this->config["download_updates"] = false;
            if (!isset($this->config["update_check_url"])) $this->config["update_check_url"] = "https://raw.githubusercontent.com/jackthehack21/koth/master/updates.json";
            $this->config["version"] = $this::CONFIG_VER;
            $this->saveConfig();
        }

        if($this->config["download_updates"] === true){
            $this->getLogger()->warning("Important disclaimer, by using the download_updates option you accept full responsibility of any damage done by the feature. Disable the option if you are un-happy with this or do not need it (Note, it has been tested and works on a plain pocketmine-mp v3.7.0-v3.8.5 server)");
        }

    }

    private function downloadUpdate(string $url): void{
        @mkdir($this->getDataFolder()."tmp/");
        $path = $this->getDataFolder()."tmp/KOTH-Update.phar";
        $this->getServer()->getAsyncPool()->submitTask(new DownloadFile($this, $url, $path));
    }

    public function handleDownload(string $path, int $status): void{
        $this->debug("Update download complete, at '".$path."' with status '".$status."'");
        if($status !== 200){
            $this->getLogger()->warning("Received status code '".$status."' when downloading update, update cancelled.");
            $this->utils->rmalldir($this->getDataFolder()."/tmp");
            return;
        }
        @rename($path, $this->getServer()->getPluginPath()."/KOTH-Update.phar");
        if($this->getFileName() === null){
            $this->debug("Deleting previous KOTH version...");
            $this->utils->rmalldir($this->getFile()); //i shouldn't be helping with source but i guess i can...
            $this->getLogger()->warning("Update complete, restart your server to load the new updated version.");
            return;
        }
        @rename($this->getServer()->getPluginPath()."/".$this->getFileName(), $this->getServer()->getPluginPath()."/KOTH.phar.old"); //failsafe i guess.
        $this->getLogger()->warning("Update complete, restart your server to load the new updated version.");
        return;
    }

    public function handleUpdateInfo(Array $data): void
    {
        $this->debug("Handling latest update data.");
        if ($data["Error"] !== '') {
            $this->getLogger()->warning("Failed to get latest update data, Error: " . $data["Error"] . " Code: " . $data["httpCode"]);
            return;
        }
        if (array_key_exists("version", $data["Response"]) && array_key_exists("time", $data["Response"]) && array_key_exists("link", $data["Response"])) {
            $update = $this->utils->compareVersions(strtolower($this->getDescription()->getVersion()), strtolower($data["Response"]["version"]));
            if ($update == 0) {
                $this->getLogger()->debug("Plugin up-to-date !");
                return;
            }
            if ($update > 0 and $this->config["show_updates"] === true) {
                $lines = explode("\n", $data["Response"]["patch_notes"]);
                $this->getLogger()->warning("--- UPDATE AVAILABLE ---");
                $this->getLogger()->warning(C::RED . " Version     :: " . $data["Response"]["version"]);
                $this->getLogger()->warning(C::AQUA . " Released on :: " . date("d-m-Y", intval($data["Response"]["time"])));
                $this->getLogger()->warning(C::GREEN . " Patch Notes :: " . $lines[0]);
                for ($i = 1; $i < sizeof($lines); $i++) {
                    $this->getLogger()->warning("                " . C::GREEN . $lines[$i]);
                }
                $this->getLogger()->warning(C::LIGHT_PURPLE . " Update Link :: " . $data["Response"]["link"]);
                if($this->config["download_updates"] !== true) $this->getLogger()->warning(C::GREEN." Enable the download_updates option in config.yml to automatically download and install updates.");
            } else {
                if ($update < 0) $this->debug("Running a build not yet released, this can cause un intended side effects (including possible data loss)");
                return;
            }
            if ($this->config["download_updates"] === true){
                $this->getLogger()->warning(C::RED." Downloading & Installing Update, please do not abruptly stop server/plugin.");
                $this->debug("Begin download of new update from '".$data["Response"]["download_link"]."'.");
                $this->downloadUpdate($data["Response"]["download_link"]);
            }
        } else {
            $this->getLogger()->warning("Failed to verify update data/incorrect format provided.");
            return;
        }
    }

    private function loadArenas(): void
    {
        switch (strtolower($this->config["provider"])) {
            case 'sqlite':
            case 'sql':
            case 'sqlite3':
                $this->db = new SqliteProvider($this);
                break;
            case 'yaml':
                $this->db = new YamlProvider($this);
                break;
            default:
                $this->db = new SqliteProvider($this);
                $this->config["provider"] = "sqlite3";
                $this->saveConfig();
        }
        $this->debug(str_replace("{NAME}", $this->db->getName(), $this->utils->colourise($this->messages["general"]["provider"])));
        $this->db->open();
        $data = $this->db->getAllData();

        foreach ($data as $arenaC) {
            $arena = new Arena($this, $arenaC["name"], $arenaC["min_players"], $arenaC["max_players"], $arenaC["play_time"], $arenaC["hill"], $arenaC["spawns"], $arenaC["rewards"], $arenaC["world"]);
            $this->arenas[] = $arena;
        }

        $this->debug(str_replace("{AMOUNT}", count($this->arenas), $this->utils->colourise($this->messages["arenas"]["loaded"])));
    }

    public function onDisable()
    {
        //small checks here to stop throwing more errors if crashing on load/enable etc.
        if(!is_null($this->db)) $this->updateAllArenas();
        if(!is_null($this->config)) $this->saveConfig();
        if(!is_null($this->db)) $this->db->close();
    }

    public function onEnable(): void
    {
        @rmdir($this->getDataFolder()."tmp/");
        $this->initResources();
        $this->init();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool
    {
        return $this->CommandHandler->handleCommand($sender, $cmd, $label, $args);
    }

    public function updateArena(Arena $arena): void
    {
        $this->db->updateArena($arena);
    }

    public function updateAllArenas(array $data = null): void
    {
        if ($data !== null) {
            $this->db->setAllData($data);
            return;
        }
        $save = [];
        foreach ($this->arenas as $arena) {
            $save[] = [
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
        if(!is_null($this->db)) $this->db->setAllData($save);
    }

    public function saveConfig(array $data = null): void
    {
        if ($data !== null) {
            if(!is_null($this->configC)) $this->configC->setAll($data);
            return;
        }
        if(is_null($this->configC) or is_null($this->config)) return;
        $this->configC->setAll($this->config);
        $this->configC->save();
    }

    public function debug(string $msg): bool
    {
        if ($this->config["debug"] === true) {
            $this->getServer()->getLogger()->info(str_replace("{MSG}", $msg, $this->utils->colourise($this->messages["general"]["debug_format"])));
            return true;
        }
        return false;
    }

    public function inGame(string $name): bool
    {
        return $this->getArenaByPlayer($name) !== null;
    }

    public function newArena(Arena $arena): void
    {
        $this->arenas[] = $arena;
        $this->db->createArena($arena);
        return;
    }

    public function removeArena(Arena $arena): void
    {
        if (($key = array_search($arena, $this->arenas)) !== false) {
            unset($this->arenas[$key]);
            $this->db->deleteArena(strtolower($arena->getName()));
        }
    }

    public function removeArenaByName(string $name): void
    {
        $this->removeArena($this->getArenaByName($name));
    }

    public function getAllArenas(): array
    {
        return $this->arenas;
    }

    public function getArenaByPlayer(string $name)
    {
        foreach ($this->arenas as $arena) {
            if (in_array(strtolower($name), $arena->getPlayers())) {
                return $arena;
            }
        }
        return null;
    }

    public function getArenaByName(string $name)
    {
        foreach ($this->arenas as $arena) {
            if (strtolower($arena->getName()) == strtolower($name)) {
                return $arena;
            }
        }
        return null;
    }

    private function getFileName(){
        $path = $this->getFile();
        if(substr($path, 0, 7) !== "phar://") return null;
        $tmp = explode("\\", $path);
        $tmp = end($tmp); //requires reference, so cant do all in one
        return str_replace("/","",$tmp);
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }
}