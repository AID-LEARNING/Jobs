<?php

namespace SenseiTarzan\Jobs\Commands\sub;

use CortexPE\Commando\constraint\InGameRequiredConstraint;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use SenseiTarzan\Jobs\Component\JobManager;
use CortexPE\Commando\BaseSubCommand;

class subAdminCommand extends BaseSubCommand
{

    /**
     * @inheritDoc
     */
    protected function prepare(): void
    {
        $this->addConstraint(new InGameRequiredConstraint($this));
        $this->setPermission("jobs.admin.commands");
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        /** @var Player $sender */
        JobManager::getInstance()->UIIndexAdmin($sender);
    }
}