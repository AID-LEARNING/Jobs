<?php

namespace SenseiTarzan\Jobs;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use ReflectionException;
use SenseiTarzan\Jobs\Class\Exception\JobExistException;
use SenseiTarzan\Jobs\Component\JobManager;
use SenseiTarzan\Path\PathScanner;
use Symfony\Component\Filesystem\Path;

class Main extends PluginBase
{

    use SingletonTrait;

    protected function onLoad(): void
    {
        self::setInstance($this);

        if (!file_exists(Path::join($this->getDataFolder(), "config.yml"))) {
            foreach (PathScanner::scanDirectoryGenerator($search =  Path::join(dirname(__DIR__,3) , "resources")) as $file){
                @$this->saveResource(str_replace($search, "", $file));
            }
        }
    }

    /**
     * @throws ReflectionException
     * @throws JobExistException
     */
    protected function onEnable(): void
    {
        new JobManager($this);
    }

}