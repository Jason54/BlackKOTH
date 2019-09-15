<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Providers;

use BlackTeam\BlackKOTH\{Main,Arena};

interface BaseProvider
{
    public function __construct(Main $plugin);

    public function getName() : string;

    public function open() : void;

    public function close() : void;

    public function save() : void;


    public function createArena(Arena $arena) : void;

    public function updateArena(Arena $arena) : void;

    public function deleteArena(string $name) : void;


    public function getDataVersion() : int;

    public function getAllData() : array;

    public function setAllData(array $data) : void;
}