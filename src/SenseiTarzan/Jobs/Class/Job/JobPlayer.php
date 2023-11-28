<?php

namespace SenseiTarzan\Jobs\Class\Job;

use pocketmine\player\Player;
use SenseiTarzan\Jobs\Class\Exception\JobNotFoundException;

readonly class JobPlayer
{

    public function __construct(private Player $player, array $jobs)
    {
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
     * @return mixed
     * @throws JobNotFoundException
     */
    public function getJob(string $id): DataJob{
        return ($this->jobs[$id] ?? throw new JobNotFoundException());
    }
}