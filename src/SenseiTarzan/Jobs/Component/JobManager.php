<?php

namespace SenseiTarzan\Jobs\Component;

use Closure;
use pocketmine\event\HandlerListManager;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use ReflectionException;
use SenseiTarzan\Jobs\Class\Exception\JobExistException;
use SenseiTarzan\Jobs\Class\Job\Job;
use SenseiTarzan\Jobs\Main;
use SenseiTarzan\Path\PathScanner;
use Symfony\Component\Filesystem\Path;

class JobManager
{
    use SingletonTrait;

    /**
     * @var Job[]
     */
    private array $jobs = [];

    private Config $config;

    /**
     * @throws ReflectionException
     * @throws JobExistException
     */
    public function __construct(private readonly Main $plugin)
    {
        self::setInstance($this);
        $this->config = $this->plugin->getConfig();
        $this->loadDefaultJob();
    }

    /**
     * @throws JobExistException
     * @throws ReflectionException
     */
    public function loadDefaultJob(): void{
        foreach (PathScanner::scanDirectoryToConfig(Path::join($this->plugin->getDataFolder(), "jobs"), ["yml"]) as $config){
            /**
             * @var Closure $event;
             * @phpstan-param Closure(Job $_job) : Closure $event
             */
            $event = eval($config->get('event'));
            $this->addJob(
                new Job(
                    (string) $config->get('name'),
                    (string) $config->get('icon'),
                    (string) $config->get('description'),
                    (array) $config->get('listActionToXp', []),
                    (array) $config->get('listXpByLevel', []),
                    (array) $config->get('listGiveaway', []),
                    $event
                )
            );
        }
    }

    /**
     * @return array
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    public function getJob(string $job): ?Job{
        return $this->jobs[$job] ?? null;
    }

    /**
     * @param array $jobs
     */
    public function setJobs(array $jobs): void
    {
        $this->jobs = $jobs;
    }

    /**
     * @param Job $job
     * @param bool $overwrite
     * @return void
     * @throws JobExistException|ReflectionException
     */
    public function addJob(Job $job, bool $overwrite = false): void
    {
        $existJob = isset($this->jobs[$job->getId()]);
        if (!$overwrite && $existJob){
            throw new JobExistException("this id {$job->getId()} is exist in list Jobs");
        }
        if ($existJob){
            $jobInList = $this->jobs[$job->getId()];
            HandlerListManager::global()->getListFor($jobInList->getEventClass())->unregister($jobInList->getRegisteredListener());
        }
        $this->jobs[$job->getId()] = $job;
    }


    public static function getInstance(): JobManager
    {
        return self::$instance;
    }
}