<?php

declare(strict_types=1);
namespace BlackTeam\BlackKOTH\Tasks;

use Error;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use BlackTeam\BlackKOTH\Main;

class DownloadFile extends AsyncTask
{
    private $url;
    private $path;

    public function __construct(Main $plugin, string $url, string $path)
    {
        $this->url = $url;
        $this->path = $path;
        $this->storeLocal($plugin); //4.0 compatible.
    }
    public function onRun()
    {
        $file = fopen($this->path, 'w+');
        if($file === false){
            throw new Error('Could not open: ' . $this->path);
        }

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); //give it 1 minute.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        if(curl_errno($ch)){
            throw new Error(curl_error($ch));
        }
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($file);
        $this->setResult($statusCode);
    }
    public function onCompletion(Server $server)
    {
        $plugin = $this->fetchLocal();
        $plugin->handleDownload($this->path, $this->getResult());
    }
}
