<?php

namespace SenseiTarzan\Jobs\Class\Job;

use Generator;
use JsonSerializable;
use pocketmine\player\Player;
use SenseiTarzan\Jobs\Class\Exception\PlayerJobLevelMaxException;
use SenseiTarzan\Jobs\Class\Exception\PlayerJobNoHaveXpException;
use SenseiTarzan\Jobs\Component\JobManager;
use SenseiTarzan\DataBase\Component\DataManager;
use SOFe\AwaitGenerator\Await;
class DataJob implements JsonSerializable
{
    public function __construct(private readonly string $id, private int $level, private float $progression)
    {
    }

    public static function create(string $name, int $level, float $progression): DataJob
    {
        return new DataJob($name, $level, $progression);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(Player $player): string{
        return $this->getJob()->getName($player);
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @param int $level
     * @return Generator
     */
    public function setLevel(int $level): Generator
    {
        return Await::promise(function ($resolve) use($level): void{
            $this->level = $level;
            $resolve();
        });
    }

    /**
     * @return float
     */
    public function getProgression(): float
    {
        return $this->progression;
    }

    /**
     * @param float $progression
     * @return Generator
     */
    public function setProgression(float $progression): Generator
    {
        return Await::promise(function ($resolve) use($progression): void{
            $this->progression = round($progression,2);
            $resolve();
        });
    }

    /**
     * @param float $progression
     * @return Generator
     */
    public function addProgression(float $progression): Generator
    {
        return Await::promise(function ($resolve) use($progression): void{
            $this->progression += round($progression,2);
            $resolve();
        });
    }

    /**
     * @param float $progression
     * @return Generator
     */
    public function subProgression(float $progression): Generator{
        return Await::promise(function ($resolve) use($progression): void{
            $this->progression -= round($progression,2);
            $resolve();
        });
    }

    public function addLevel(int $level): Generator
    {
        return Await::promise(function ($resolve) use($level): void{
            $this->level += $level;
            $resolve();
        });
    }
    public function subLevel(int $level): Generator
    {
        return Await::promise(function ($resolve) use($level): void{
            $this->level -= $level;
            $resolve();
        });
    }

    public function setProgressionsAndLevel(?float $progression = null, ?int $level = null): Generator{
        return Await::promise(function ($resolve, $reject) use ($progression, $level): void{
            Await::f2c(function () use ($progression, $level): Generator{
                if ($progression)
                   yield from $this->setProgression($progression);
                if ($level)
                    yield from $this->setLevel($level);
            }, $resolve, $reject);
        });
    }

    public function levelUp(): Generator{
        return Await::promise(function ($resolve, $reject): void{
            $levelNext = $this->getLevel() + 1;
	        if ($levelNext > $this->getJob()->getMaxLvl()){
		        $reject(new PlayerJobLevelMaxException());
		        return;
	        }
            $xpNext = $this->getJob()->getXpByLevel($levelNext);
            if ($xpNext < $this->getProgression()) {
                Await::f2c(function () use ($xpNext, $levelNext): Generator{
                    yield from $this->subProgression($xpNext);
                    yield from $this->addLevel(1);
                    return $this->getLevel();
                }, $resolve, $reject);
                return;
            }
            $reject(new PlayerJobNoHaveXpException());
        });
    }

    public function getJob(): Job
    {
        return JobManager::getInstance()->getJob($this->id);
    }

    public function jsonSerialize(): array
    {
        return ["level" => $this->getLevel(), "progression" => $this->getProgression()];
    }
}