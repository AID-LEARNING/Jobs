<?php

namespace SenseiTarzan\Jobs\Class\Save;

use Generator;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use SenseiTarzan\Jobs\Class\Exception\JobPlayerNoExistException;
use SenseiTarzan\Jobs\Class\Exception\SaveUpdateException;
use SenseiTarzan\Jobs\Class\Job\DataJob;
use SenseiTarzan\Jobs\Component\JobManager;
use SenseiTarzan\Jobs\Component\JobPlayerManager;
use SenseiTarzan\Jobs\Main;
use SOFe\AwaitGenerator\Await;
use Symfony\Component\Filesystem\Path;

class JSONDataSave extends DataSaveJob
{

    private readonly Config $data;

    public function __construct(private readonly Main $plugin)
    {
        $this->data = new Config(Path::join($this->plugin->getDataFolder(), "data.json"), Config::JSON);
    }

    public function createPromiseLoadJob(Player $player): \Generator
    {
        return Await::promise(function ($resolve) use ($player): void {
            $resolve($this->data->get(mb_strtolower($player->getName()), []));
        });
    }

    public function creationPromiseDataJob(array $data): \Generator
    {
        return Await::promise(function ($resolve) use ($data): void {
            foreach (JobManager::getInstance()->getDefaultData() as $job => $datum) {
                if (isset($data[$job])) continue;
                $data[$job] = $datum;
            }
            $resolve($data);
        });
    }


    public function createPromiseSaveJobsData(Player $player): \Generator
    {
        return Await::promise(function ($resolve, $reject) use ($player): void {
            $jobPlayer = JobPlayerManager::getInstance()->getPlayer($player);
            if ($jobPlayer == null) {
                $reject(new JobPlayerNoExistException("{$player->getName()} no exist in JobPlayerManager"));
                return;
            }
            try {
                $this->data->set(mb_strtolower($player->getName()), $jobPlayer->jsonSerialize());
                $this->data->save();
                $resolve();
            } catch (\JsonException $exception) {
                $reject(new SaveUpdateException($exception->getMessage()));
            }
        });
    }

    public function createPromiseSaveJobData(Player $player, DataJob $dataJob): Generator
    {
        return Await::promise(function ($resolve, $reject) use ($dataJob, $player): void {
            try {
                $this->data->setNested(mb_strtolower($player->getName()) . ".{$dataJob->getId()}", $dataJob->jsonSerialize());
                $this->data->save();
                $resolve();
            } catch (\JsonException $exception) {
                $reject(new SaveUpdateException($exception->getMessage()));
            }
        });
    }

    public function getName(): string
    {
        return "JSON";
    }
}