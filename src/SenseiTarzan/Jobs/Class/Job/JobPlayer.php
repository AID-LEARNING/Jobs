<?php

namespace SenseiTarzan\Jobs\Class\Job;

use Generator;
use JsonSerializable;
use pocketmine\player\Player;
use SenseiTarzan\Jobs\Class\Exception\DataJobLoadException;
use SenseiTarzan\Jobs\Class\Exception\JobNotFoundException;
use SOFe\AwaitGenerator\Await;
use SenseiTarzan\Jobs\Utils\Convertor;

readonly class JobPlayer implements JsonSerializable
{

    public function __construct(private Player $player, private array $jobs)
    {
    }

    public static function create(Player $player, array $data): Generator
    {
        return Await::promise(function ($resolve, $reject) use($player, $data): void{
            Await::f2c(function () use($player, $data): Generator{
                return new JobPlayer($player, yield from Convertor::jsonToDataJson($player, $data));
            }, $resolve, $reject);
        });
    }

    /**
     * @return Player
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * @param string $id
     * @return DataJob
     * @throws JobNotFoundException
     */
    public function getJob(string $id): DataJob{
        return ($this->jobs[$id] ?? throw new JobNotFoundException());
    }

    /**
     * @return array<string,DataJob>
     */
    public function getJobs(): array{
        return $this->jobs;
    }

    public function jsonSerialize(): array
    {
        return array_map(fn (DataJob $job) => $job->jsonSerialize(), $this->getJobs());
    }
}