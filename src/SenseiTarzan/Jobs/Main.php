<?php

namespace SenseiTarzan\Jobs;

use cosmicpe\blockdata\world\BlockDataWorldManager;
use Generator;
use muqsit\asynciterator\AsyncIterator;
use muqsit\asynciterator\handler\AsyncForeachHandler;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use ReflectionException;
use SenseiTarzan\DataBase\Component\DataManager;
use SenseiTarzan\Jobs\Class\Exception\JobExistException;
use SenseiTarzan\Jobs\Class\Middleware\JobMiddleware;
use SenseiTarzan\Jobs\Class\Save\BlockPlaceData;
use SenseiTarzan\Jobs\Class\Save\JSONDataSave;
use SenseiTarzan\Jobs\Commands\JobCommand;
use SenseiTarzan\Jobs\Component\JobManager;
use SenseiTarzan\Jobs\Component\JobPlayerManager;
use SenseiTarzan\ExtraEvent\Component\EventLoader;
use cosmicpe\blockdata\BlockDataFactory;
use SenseiTarzan\Jobs\libs\SOFe\AwaitGenerator\Await;
use SenseiTarzan\Jobs\Listener\BlockListener;
use SenseiTarzan\Jobs\Listener\PlayerListener;
use SenseiTarzan\LanguageSystem\Component\LanguageManager;
use SenseiTarzan\Middleware\Component\MiddlewareManager;
use SenseiTarzan\Path\PathScanner;
use Symfony\Component\Filesystem\Path;

class Main extends PluginBase
{

    use SingletonTrait;

	private AsyncIterator $iteratorAsync;

    private BlockDataWorldManager $blockData;

    protected function onLoad(): void
    {
        self::setInstance($this);
        if (!file_exists(Path::join($this->getDataFolder(), "config.yml"))) {
            foreach (PathScanner::scanDirectoryGenerator($this->getResourceFolder()) as $file){
                @$this->saveResource(str_replace($this->getResourceFolder(), "", $file));
            }
        }
        DataManager::getInstance()->setDataSystem(match ($this->getConfig()->get("type-save")) {
			"json" => new JSONDataSave($this),
	        default => null
        });
        new JobPlayerManager();
        new LanguageManager($this);
    }

    /**
     * @throws ReflectionException
     * @throws JobExistException
     */
    protected function onEnable(): void
    {
        $this->getScheduler()->scheduleTask(new ClosureTask(function () {
	        new JobManager($this);
	        EventLoader::loadEventWithClass($this, BlockListener::class);
        }));
		$this->iteratorAsync = new AsyncIterator($this->getScheduler());
        BlockDataFactory::register("hevolia:is_placed_by_player", BlockPlaceData::class);
        $this->blockData = BlockDataWorldManager::create($this);
        $this->getServer()->getCommandMap()->register("jobs", new JobCommand($this, "jobs", "Permet d'afficher les job"));
        LanguageManager::getInstance()->loadCommands("jobs");
	    $hasMiddleware = $this->getServer()->getPluginManager()->getPlugin("Middleware") !== null;
	    if ($hasMiddleware)
		    MiddlewareManager::getInstance()->addMiddleware(new JobMiddleware());
	    EventLoader::loadEventWithClass($this, new PlayerListener($hasMiddleware));
    }

    /**
     * @return BlockDataWorldManager
     */
    public function getBlockData(): BlockDataWorldManager
    {
        return $this->blockData;
    }

	/**
	 * @return AsyncIterator
	 */
	public function getIteratorAsync(): AsyncIterator
	{
		return $this->iteratorAsync;
	}

	static public function getIteratorAsyncAwait(AsyncForeachHandler $handler, ?\Throwable $throwable = null): Generator
	{
		return Await::promise(function ($resolve, $reject) use($handler, $throwable) {
			$handler->onCompletion($resolve)->onInterruption(function () use ($reject, $throwable) {
				if($throwable)
					$reject($throwable);
				else
					$reject(new \Exception("the foreach is Interrupted"));
			});
		});
	}

}