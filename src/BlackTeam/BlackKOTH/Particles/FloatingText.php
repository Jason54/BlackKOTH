<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Particles;

use BlackTeam\BlackKOTH\Main;

use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\level\particle\FloatingTextParticle;

class FloatingText extends FloatingTextParticle {

    private $plugin;
    private $level;
    private $position;

    public function __construct(Main $plugin, Level $level, Vector3 $position, string $text, string $title = "")
    {
        parent::__construct($position, $text, $title);
        $this->plugin = $plugin;
        $this->level = $level;
        $this->position = $position;
    }

    public function setText(string $text) : void{
        $this->text = $text;
        $this->update();
    }

    public function setTitle(string $title) : void{
        $this->title = $title;
        $this->update();
    }

    public function setInvisible(bool $value = true) : void{
        $this->invisible = $value;
        $this->update();
    }

    public function update() : void{
        $this->level->addParticle($this);
    }

}