<?php

namespace SenseiTarzan\Jobs\Class\Job;

use Generator;
use JsonSerializable;
use pocketmine\player\Player;
use SenseiTarzan\Jobs\Class\Exception\PlayerJobLevelMaxException;
use SenseiTarzan\Jobs\Class\Exception\PlayerJobNoHaveXpException;
use SenseiTarzan\Jobs\Component\JobManager;
use SOFe\AwaitGenerator\Await;
class DataJob implements JsonSerializable
{
    public function __construct(private readonly Player $player, private readonly string $name, private int $level, private float $progression)
    {
    }

    public static function create(Player $player, string $name, int $level, float $progression): DataJob
    {
        return new DataJob($player, $name, $level, $progression);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
     */
    public function setLevel(int $level): void
    {
        $this->level = $level;
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
     */
    public function setProgression(float $progression): void
    {
        $this->progression = round($progression,2);
    }
    public function addProgression(float $progression): void
    {
        Await::f2c(function () use($progression): Generator{
            $this->setProgression(($this->progression + $progression));
            yield from $this->levelUp();
        }, function () {
            $this->player->sendTitle("LevelUp");
        }, function () {});
    }

    public function addLevel(int $level): void{
        $this->setLevel(($this->level + $level));
    }
    public function subLevel(int $level): void{
        $this->setLevel(min(($this->level - $level), 0));
    }

    public function subProgression(float $progression): void{
        $this->setProgression(min(($this->progression - $progression), 0));
    }

    public function setProgressionsAndLevel(?float $progression = null, ?int $level = null): void{
        if ($progression)
            $this->setProgression($progression);
        if ($level)
            $this->setLevel($level);
    }

    public function levelUp(): Generator{
        return Await::promise(function ($resolve, $reject): void{
            $levelNext = $this->getLevel() + 1;
            $xpNext = $this->getJob()->getXpByLevel($levelNext);
            if ($xpNext == null){
                $reject(new PlayerJobLevelMaxException());
                return;
            }
            if ($this->getProgression() < $xpNext) {
                Await::f2c(function () use ($xpNext, $levelNext): void {
                    $this->subProgression($xpNext);
                    $this->setLevel($levelNext);
                }, $resolve, $reject);
                return;
            }
            $reject(new PlayerJobNoHaveXpException());
        });
    }

    public function getJob(): Job
    {
        return JobManager::getInstance()->getJob($this->name);
    }

    public function jsonSerialize(): array
    {
        return ["level" => $this->getLevel(), "xp" => $this->getProgression()];
    }
}