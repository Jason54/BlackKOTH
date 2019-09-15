<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Tasks;

use pocketmine\scheduler\AsyncTask;
use BlackTeam\BlackKOTH\Main;
use pocketmine\Server;

class GetUpdateInfo extends AsyncTask
{
    private $url;

    public function __construct(Main $plugin, string $url)
    {
        $this->url = $url;
        $this->storeLocal($plugin);
    }
    public function onRun()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        $curlerror = curl_error($curl);
        $responseJson = json_decode($response, true);
        $error = '';
        if($curlerror != ""){
            $error = "Unknown error occurred, code:".curl_getinfo($curl, CURLINFO_HTTP_CODE);
        }
        elseif (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
            $error = $responseJson['message'];
        }
        $result = ["Response" => $responseJson, "Error" => $error, "httpCode" => curl_getinfo($curl, CURLINFO_HTTP_CODE)];
        $this->setResult($result);
    }
    public function onCompletion(Server $server)
    {
        $plugin = $this->fetchLocal();
        $plugin->handleUpdateInfo($this->getResult());
    }
}