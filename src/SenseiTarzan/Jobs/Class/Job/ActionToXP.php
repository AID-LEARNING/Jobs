<?php

namespace SenseiTarzan\Jobs\Class\Job;

use SenseiTarzan\Jobs\Class\Exception\JobNotFoundException;

readonly class ActionToXP
{

	/**
	 * @param Job $job
	 * @param array<string, int|float|string> $actionToXp
	 */
	public function __construct(
		private Job   $job,
		private array $actionToXp
	)
	{
	}

	/**
	 * @param Job $job
	 * @param float|array<string, float> $actionToXp
	 * @return ActionToXP
	 */
	public static function create(Job   $job, float|array $actionToXp): ActionToXP {
		return new self($job,is_array($actionToXp) ? $actionToXp : ['level' => 'none', 'xp' => $actionToXp]);
	}

	public function canRun(JobPlayer $player): bool
	{
		$levelRequire = $this->actionToXp['level'];
		$level = $player->getJob($this->job->getId())->getLevel();
		return $levelRequire === 'none' || intval($levelRequire, 10) <= $level;
	}
	public function getXp(JobPlayer $player): ?float
	{
		$xp = null;
		if ($this->canRun($player))
			$xp = $this->actionToXp['xp'];
		return $xp;
	}
}