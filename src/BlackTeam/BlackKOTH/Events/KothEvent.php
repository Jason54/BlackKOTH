<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Events;

use BlackTeam\BlackKOTH\Main;
use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;

abstract class KothEvent extends PluginEvent implements Cancellable{

    private $reason = "événement annulé";

    public function __construct(Main $plugin)
    {
        $plugin->debug("événement '".$this->getEventName()."' est en construction...");
        parent::__construct($plugin);
    }

    public function getReason(): string{
        return $this->reason;
    }

    public function setReason(string $reason): void{
        $this->reason = $reason;
    }
}