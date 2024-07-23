<?php

namespace SenseiTarzan\Jobs\Class\Save;

use Error;
use Exception;
use Generator;
use PHPUnit\Event\Code\Throwable;
use pocketmine\player\Player;
use SenseiTarzan\DataBase\Class\IDataSave;
use SenseiTarzan\Jobs\Class\Job\DataJob;
use SenseiTarzan\Jobs\Class\Job\JobPlayer;
use SenseiTarzan\Jobs\Component\JobPlayerManager;
use SenseiTarzan\Jobs\Main;
use SOFe\AwaitGenerator\Await;

abstract class DataSaveJob implements IDataSave
{

    public abstract function createPromiseLoadJob(Player $player): Generator;

    public abstract function creationPromiseDataJob(array $data): Generator;

    public abstract function createPromiseSaveJobData(Player $player, DataJob $dataJob): Generator;

    public abstract function createPromiseSaveJobsData(Player $player): Generator;

    final public function loadDataPlayer(Player|string $player): void
    {
        Main::getInstance()->getLogger()->info("Creation de la Promise de recuperation des jobs pour {$player->getName()}");
        Await::f2c(function () use ($player) {
            $data = (yield from $this->createPromiseLoadJob($player));
            $dataFinal = (yield from $this->creationPromiseDataJob($data));
            $JobPlayer = (yield from JobPlayer::create($player, $dataFinal));
            yield from JobPlayerManager::getInstance()->loadPlayer($player, $JobPlayer);
        }, static function () use ($player): void {
            Main::getInstance()->getLogger()->info("La promise de recuperation des jobs de {$player->getName()} a réussi");
        }, static function (Exception|Error $throwable) use ($player): void {
            Main::getInstance()->getLogger()->info("La promise de recuperation des jobs de {$player->getName()} a échoué");
            $player->kick($throwable->getMessage());
        });
    }

	final public function loadDataPlayerByMiddleware(Player|string $player): \Generator
	{
		Main::getInstance()->getLogger()->info("Creation de la Promise de recuperation des jobs pour {$player->getName()}");
		return Await::promise(function ($resolve, $reject) use ($player) {
			Await::f2c(function () use ($player) {
					$data = (yield from $this->createPromiseLoadJob($player));
					$dataFinal = (yield from $this->creationPromiseDataJob($data));
					$JobPlayer = (yield from JobPlayer::create($player, $dataFinal));
					yield from JobPlayerManager::getInstance()->loadPlayer($player, $JobPlayer);
					return null;
			}, function () use ($player){
				Main::getInstance()->getLogger()->info("La promise de recuperation des jobs de {$player->getName()} a réussi");
			}, static function (Throwable $result) use ($player, $reject): void {
				Main::getInstance()->getLogger()->info("La promise de recuperation des jobs de {$player->getName()} a échoué");
				$reject($result);
			});
		});
	}

	final public function onDisconnectPlayer(Player $player): void
    {
        Main::getInstance()->getLogger()->info("Creation de la Promise de sauvergarde pour {$player->getName()}");
        Await::g2c($this->createPromiseSaveJobsData($player), static function () use ($player) {
            Main::getInstance()->getLogger()->info("La promise de sauvegarde de {$player->getName()} a réussi");
        }, static function (Exception|Error $throwable) use ($player): void {
            Main::getInstance()->getLogger()->info("La promise de sauvegarde de {$player->getName()} a échoué");
            $player->kick($throwable->getMessage());
        });
        JobPlayerManager::getInstance()->unload($player);
    }

    /**
     * @inheritDoc
     */
    final public function updateOnline(string $id, string $type, mixed $data): null
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    final public function updateOffline(string $id, string $type, mixed $data): null
    {
        return null;
    }
}