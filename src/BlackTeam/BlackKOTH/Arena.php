<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH;

use BlackTeam\BlackKOTH\Events\ArenaAddPlayerEvent;
use BlackTeam\BlackKOTH\Events\ArenaEndEvent;
use BlackTeam\BlackKOTH\Events\ArenaPreStartEvent;
use BlackTeam\BlackKOTH\Events\ArenaRemovePlayerEvent;
use BlackTeam\BlackKOTH\Events\ArenaStartEvent;
use BlackTeam\BlackKOTH\Particles\FloatingText;
use BlackTeam\BlackKOTH\Tasks\Prestart;
use BlackTeam\BlackKOTH\Tasks\Gametimer;

use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;

use ReflectionException;


class Arena{

    public const STATUS_NOT_READY = 0;
    public const STATUS_READY = 1;
    public const STATUS_STARTED = 2;
    public const STATUS_FULL = 3;
    public const STATUS_INVALID = 4;
    public const STATUS_UNKNOWN = 9;

    public $statusList = [
        self::STATUS_NOT_READY => "Pas prêt / Configuration",
        self::STATUS_READY => "Prêt",
        self::STATUS_STARTED => "Commencé",
        self::STATUS_FULL => "Plein",
        self::STATUS_INVALID => "Configuration non valide", #Used when arena was setup correctly but external causes means its no longer compatible.
        self::STATUS_UNKNOWN => "Inconnu"
    ];

    private $plugin;
    public $spawns = [];
    public $spawnCounter;
    public $hill = [];
    private $players = [];
    public $playerOldPositions = [];
    public $playerOldNameTags = [];
    public $minPlayers;
    public $maxPlayers;
    public $name;
    public $started;
    public $time;
    public $countDown;
    public $world;
    public $rewards;

    public $oldKing;
    public $king;
    public $playersInBox = [];

    public $timerTask;

    public $status = 9;

    public $currentKingParticle = null;

    public function __construct(Main $plugin, string $name, int $min, int $max, int $time, array $hill, array $spawns, array $rewards, string $world){
        $this->plugin = $plugin;
        $this->hill = $hill;
        $this->minPlayers = $min;
        $this->maxPlayers = $max;
        $this->name = $name;
        $this->spawns = $spawns;
        $this->spawnCounter = 0;
        $this->started = false;
        $this->time = $time;
        $this->countDown = $plugin->config["countdown"];
        $this->world = $world;
        $this->rewards = $rewards;

        $this->king = null;
        $this->playersInBox = [];
        $this->timerTask = null;

        $this->currentKingParticle = null;

        $this->checkStatus();
        $this->createKingTextParticle();
    }

    public function getFriendlyStatus() : string{
        return isset($this->statusList[$this->status]) ? $this->statusList[$this->status] : $this->statusList[$this::STATUS_UNKNOWN];
    }

    public function getStatus() : int{
        return $this->status;
    }

    public function getName() : string{
        return $this->name;
    }

    public function broadcastMessage(string $msg) : void{
        foreach($this->players as $player){
            $this->plugin->getServer()->getPlayerExact($player)->sendMessage($msg);
        }
    }

    public function broadcastWinner(string $player) : void{
        $this->broadcastMessage(str_replace(["{ARENA}", "{PLAYER}"], [$this->name, $player], $this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["winner"])));
    }

    public function broadcastQuit(Player $player, string $reason) : void{
        $this->broadcastMessage(str_replace(["{REASON}", "{PLAYER}"], [$reason, $player->getLowerCaseName()], $this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["player_quit"])));
    }

    public function broadcastJoin(Player $player) : void{
        $this->broadcastMessage(str_replace("{PLAYER}", $player->getLowerCaseName(), $this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["player_join"])));
    }

    public function checkStatus(bool $save = true) : void{
        if(count($this->hill) === 2 and count($this->spawns) >= 1 and $this->plugin->getServer()->getLevelByName($this->world) !== null){
            $this->status = self::STATUS_READY;
        } else {
            $this->status = self::STATUS_NOT_READY;
            if($this->world === null or $this->plugin->getServer()->getLevelByName($this->world) === null){
                $this->status = self::STATUS_INVALID;
                $this->currentKingParticle = null;
            }
            if($save === true) $this->plugin->updateArena($this);
            return;
        }
        if($this->started === true){
            $this->status = self::STATUS_STARTED;
        }
        if(count($this->players) >= $this->maxPlayers){
            $this->status = self::STATUS_FULL;
            if($save === true) $this->plugin->updateArena($this);
            return;
        }
        if($save === true) $this->plugin->updateArena($this);
    }

    public function createKingTextParticle() : void{
        if($this->plugin->config["KingTextParticles"] === false) return;
        if(($this->status !== $this::STATUS_NOT_READY and $this->status !== $this::STATUS_INVALID) and $this->currentKingParticle === null){
            $pos = new Vector3(($this->hill[0][0]+$this->hill[1][0])/2,($this->hill[0][1]+$this->hill[1][1])/2,($this->hill[0][2]+$this->hill[1][2])/2);
            $this->currentKingParticle = new FloatingText($this->plugin, $this->plugin->getServer()->getLevelByName($this->world), $pos, C::RED."King: ".C::GOLD."-");
        }
    }

    public function updateKingTextParticle() : void{
        if($this->currentKingParticle !== null){
            $this->currentKingParticle->setInvisible(false);
            $this->currentKingParticle->setText(C::RED."King: ".C::GOLD.($this->king === null ? "-" : $this->king));
        } else {
            $this->createKingTextParticle();
        }
        $this->updateNameTags();
    }

    public function removeKingTextParticles() : void{
        if($this->currentKingParticle !== null){
            $this->currentKingParticle->setInvisible();
        }
        $this->updateNameTags();
    }

    public function updateNameTags() : void{
        if($this->plugin->config["nametag_enabled"] === true){
            $format = $this->plugin->utils->colourise($this->plugin->config["nametag_format"]);
            if($this->king !== null){
                $player = $this->plugin->getServer()->getPlayerExact($this->king);
                if(array_key_exists($this->king,$this->playerOldNameTags) !== true){
                    $this->playerOldNameTags[$this->king] = $player->getNameTag();
                }
                $old = $this->playerOldNameTags[$player->getLowerCaseName()];
                $player->setNameTag($format."\n".$old);
                if($this->oldKing !== null and $this->oldKing !== $this->king){
                    $old = $this->playerOldNameTags[$this->oldKing];
                    $p = $this->plugin->getServer()->getPlayerExact($this->oldKing);
                    if($p === null) return;
                    $p->setNameTag($old);
                }
            } else {
                if($this->oldKing !== null){
                    $player = $this->plugin->getServer()->getPlayerExact($this->oldKing);
                    if($player === null) return;
                    $player->setNameTag($this->playerOldNameTags[strtolower($player->getName())]);
                }
            }
        }
    }

    private function spawnPlayer(Player $player, $random = false) : bool{
        if(strtolower($player->getLevel()->getName()) !== strtolower($this->world)){
            if(!$this->plugin->getServer()->isLevelGenerated($this->world)) {
                $player->sendMessage($this->plugin->prefix.C::RED."Monde fixé pour '".$this->name."' n'existe pas.");
                return false;
            }
            if(!$this->plugin->getServer()->isLevelLoaded($this->world)) {
                $this->plugin->getServer()->loadLevel($this->world);
            }

        }
        $player->teleport($this->getSpawn($random));
        return true;
    }

    public function getSpawn(bool $random = false) : Position{
        if($random === false){
            if($this->spawnCounter >= count($this->spawns)){
                $this->spawnCounter = 0;
            }
            $old = $this->spawns[$this->spawnCounter];
            $pos = new Position($old[0], $old[1], $old[2], $this->plugin->getServer()->getLevelByName($this->world));
            $this->spawnCounter++;
            return $pos;
        } else {
            $old = $this->spawns[array_rand($this->spawns)];
            $pos = new Position($old[0], $old[1], $old[2], $this->plugin->getServer()->getLevelByName($this->world));
            return $pos;
        }
    }

    public function freezeAll(bool $freeze) : void{
        $this->plugin->debug("Mise en scène des joueurs '".$this->name."' ".($freeze ? "immobile" : "mobile"));
        foreach($this->players as $name){
            $this->plugin->getServer()->getPlayerExact($name)->setImmobile($freeze);
        }
    }

    public function startTimer(){
        $event = new ArenaPreStartEvent($this->plugin, $this);
        try {
            $event->call();
        } catch (ReflectionException $e) {
            return $this->plugin->prefix.C::RED."Échec de l'événement, Arene '".$this->getName()."' compte à rebours pas commencé.";
        }

        if($event->isCancelled()){
            return $event->getReason();
        }
        $this->timerTask = $this->plugin->getScheduler()->scheduleRepeatingTask(new Prestart($this->plugin, $this, $event->getCountDown()),20);
        $this->plugin->debug("Démarrage de la tâche Prestart pour l'arène '".$this->name."'.");
        return null;
    }

    public function startGame() : void{
        $event = new ArenaStartEvent($this->plugin, $this);
        try {
            $event->call();
        } catch (ReflectionException $e) {
            $this->plugin->getLogger()->warning($this->plugin->prefix.C::RED."Échec de l'événement, Arene '".$this->getName()."' pas commencé.");
            return;
        }

        if($event->isCancelled()){
            $this->plugin->getLogger()->warning($this->plugin->prefix.C::RED."Impossible de commencer le match dans Arene '".$this->getName()."' parce que: ".$event->getReason());
            return;
        }
        $this->plugin->debug("Arène de départ '".$this->name."'...");
        $this->timerTask->cancel();
        $this->started = true;
        $this->checkStatus();
        $msg = str_replace("{ARENA}", $this->name, $this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["start"]));
        if($this->plugin->config["start_bcast_serverwide"] === true){
            $this->plugin->getServer()->broadcastMessage($msg);
        } else {
            $this->broadcastMessage($msg);
        }
        $this->createKingTextParticle()
        $this->updateKingTextParticle();
        $this->timerTask = $this->plugin->getScheduler()->scheduleRepeatingTask(new Gametimer($this),10);
        $this->plugin->debug("Started arena '".$this->name."'.");
    }

    public function reset() : void{
        $this->removeKingTextParticles();

        $this->started = false;
        $this->king = null;
        $this->oldKing = null;
        $this->timerTask = null;

        foreach($this->players as $name){
            $player = $this->plugin->getServer()->getPlayerExact($name);
            $this->removePlayer($player, "Jeu terminé", true);
        }

        $this->players = [];
        $this->playerOldPositions = [];
        $this->playerOldNameTags = [];
        $this->checkStatus();
    }

    public function endGame() : void{
        $event = new ArenaEndEvent($this->plugin, $this);
        try {
            $event->call();
        } catch (ReflectionException $e) {
            $this->plugin->getLogger()->warning($this->plugin->prefix.C::RED."Échec de l'événement, Arene '".$this->getName()."' pas fini.");
            return;
        }

        if($event->isCancelled()){
            $this->timerTask->getTask()->secondsLeft = $event->getSecondsLeft();
            $this->plugin->getLogger()->warning($this->plugin->prefix.C::RED."Arene '".$this->name."'pas fini, raison: ".$event->getReason());
            return;
        }
        $msg = str_replace("{ARENA}", $this->name, $this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["end"]));
        if($this->plugin->config["end_bcast_serverwide"] === true){
            $this->plugin->getServer()->broadcastMessage($msg);
        } else {
            $this->broadcastMessage($msg);
        }
        $this->plugin->debug("Arene '".$this->name."' terminé.");
        $this->freezeAll(true);
        $king = "Null";
        $this->timerTask->cancel();
        if($this->king !== null){
            $king = $this->king;
        } else {
            if($this->oldKing !== null){
                $king = $this->oldKing;
            }
        }
        $this->setWinner($king);
        $this->reset();
        $this->checkStatus();
    }
    public function setWinner(string $king) : void{
        if($king === "Null"){
            $this->broadcastMessage($this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["no_winner"]));
            $this->freezeAll(false);
            return;
        }
        $this->broadcastWinner($king);
        $console = new ConsoleCommandSender();
        foreach($this->rewards as $reward){
            $reward = str_replace("{PLAYER}", $king, $reward);
            if($this->plugin->getServer()->getCommandMap()->dispatch($console, $reward) === false){
                $this->plugin->getLogger()->warning("Reward/command (".$reward.") failed to execute.");
            };

        }
        $this->freezeAll(false);
    }

    public function getPlayers() : array{
        return $this->players;
    }

    public function playersInBox() : array{
        $pos1 = [];
        $pos1["x"] = $this->hill[0][0];
        $pos1["y"] = $this->hill[0][1];
        $pos1["z"] = $this->hill[0][2];
        $pos2 = [];
        $pos2["x"] = $this->hill[1][0];
        $pos2["y"] = $this->hill[1][1];
        $pos2["z"] = $this->hill[1][2];
        $minX = min($pos2["x"],$pos1["x"]);
        $maxX = max($pos2["x"],$pos1["x"]);
        $minY = min($pos2["y"],$pos1["y"]);
        $maxY = max($pos2["y"],$pos1["y"]);
        $minZ = min($pos2["z"],$pos1["z"]);
        $maxZ = max($pos2["z"],$pos1["z"]);
        $list = [];

        if($minY == $maxY){
            $maxY += 1.51;
        } 

        foreach($this->players as $playerName){
            $player = $this->plugin->getServer()->getPlayer($playerName);
            if(($minX <= $player->getX() && $player->getX() <= $maxX && $minY <= $player->getY() && $player->getY() <= $maxY && $minZ <= $player->getZ() && $player->getZ() <= $maxZ)){
                $list[] = $playerName;
            }
        }
        return $list;
    }

    public function removeKing() : void{
        if($this->king === null) return;
        $this->broadcastMessage(str_replace("{PLAYER}", $this->king, $this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["fallen_king"])));
        $this->changeking();
    }

    public function changeKing() : void{
        if($this->king !== null){
            $this->oldKing = $this->king;
            $this->king = null;
        }
        $this->updateKingTextParticle();
    }

    public function checkNewKing() : bool{
        if(count($this->playersInBox()) === 0){
            return false;
        } else {
            $player = $this->playersInBox()[array_rand($this->playersInBox())]; //todo closest to middle, Beta4
            $this->broadcastMessage(str_replace("{PLAYER}", $player, $this->plugin->utils->colourise($this->plugin->messages["broadcasts"]["new_king"])));
            $this->king = $player;
            $this->updateKingTextParticle();
            return true;
        }
    }


    public function removePlayer(Player $player, string $reason, bool $silent = false) : void{
        $event = new ArenaRemovePlayerEvent($this->plugin, $this, $player, $reason, $silent);
        try {
            $event->call();
        } catch (ReflectionException $e) {
            if(!$player->isConnected()){
                //Player is leaving app.
                $this->plugin->getLogger()->warning($this->plugin->prefix . C::RED . "L'événement a échoué, mais le joueur a quitté la partie en supposant le scénario par défaut...");
            } else {
                $this->plugin->getLogger()->warning($this->plugin->prefix . C::RED . "Échec de l'événement, joueur non supprimé.");
                return;
            }
        }
        if($event->isCancelled()){
            if(!$player->isConnected()){
                $this->plugin->getLogger()->warning($this->plugin->prefix . C::RED . "L'événement est annulé, mais le joueur quitte l'application, il sera quand même supprimé..");
            } else {
                $player->sendMessage($this->plugin->prefix.C::RED."Ne peut pas quitter l'arène, raison: ".$event->getReason());
                return;
            }
        }
        unset($this->players[array_search(strtolower($player->getName()), $this->players)]);
        if($this->king === $player->getLowerCaseName()){
            $this->removeKing();
        }
        if($silent === false) $this->broadcastQuit($player, $reason);
        $this->checkStatus();
        if($player->loggedIn !== false and $player->spawned !== false){
            $pos = new Position($this->playerOldPositions[strtolower($player->getName())][1],$this->playerOldPositions[strtolower($player->getName())][2],$this->playerOldPositions[strtolower($player->getName())][3],$this->plugin->getServer()->getLevelByName($this->playerOldPositions[strtolower($player->getName())][0]));
            $player->teleport($pos);
            unset($this->playerOldPositions[strtolower($player->getName())]);
        }
    }

    public function addPlayer(Player $player) : bool{
        if($this->plugin->getArenaByPlayer(strtolower($player->getName())) !== null){
            $player->sendMessage($this->plugin->prefix.C::RED."Vous êtes dans une arène, tapez /koth leave avant de rejoindre un autre.");
            return false;
        }
        switch($this->status){
            case self::STATUS_NOT_READY:
                $player->sendMessage($this->plugin->prefix.C::RED."Cette arène n'a pas été configurée.");
                return false;
            case self::STATUS_FULL:
                $player->sendMessage($this->plugin->prefix.C::RED."Cette arène est pleine.");
                return false;
            case self::STATUS_INVALID:
                $player->sendMessage($this->plugin->prefix.C::RED."Cette arène a été installée dans un endroit qui n'existe plus.");
                return false;
            case self::STATUS_UNKNOWN:
                $player->sendMessage($this->plugin->prefix.C::RED."Ce stade a un statut inconnu.");
                return false;
        }
        $event = new ArenaAddPlayerEvent($this->plugin, $this, $player);
        try {
            $event->call();
        } catch (ReflectionException $e) {
            $this->plugin->getLogger()->warning($this->plugin->prefix.C::RED."Échec de l'événement, joueur non ajouté.");
            $player->sendMessage($this->plugin->prefix.C::RED."Impossible de rejoindre l'arène, raison: ".$event->getReason());
            return false;
        }
        if($event->isCancelled()){
            $player->sendMessage($this->plugin->prefix.C::RED."Impossible de rejoindre l'arène, raison: ".$event->getReason());
            return false;
        }

        $this->playerOldPositions[strtolower($player->getName())] = [$player->getLevel()->getName(),$player->getX(), $player->getY(), $player->getZ()];
        if(!$this->spawnPlayer($player)){
            unset($this->playerOldPositions[strtolower($player->getName())]);
            return false;
        }
        $player->setGamemode(0); //todo Beta4 configurable.
        $this->players[] = strtolower($player->getName());
        $this->broadcastJoin($player);
        if(count($this->players) >= $this->minPlayers && $this->timerTask === null && $this->plugin->config["auto_start"] === true){
            $this->startTimer();
        }
        $this->checkStatus();
        return true;
    }
}