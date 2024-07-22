<?php

namespace SenseiTarzan\Jobs\Commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use SenseiTarzan\Jobs\Commands\sub\subAdminCommand;
use SenseiTarzan\Jobs\Component\JobManager;

class JobCommand extends BaseCommand
{

    /**
     * @inheritDoc
     */
    protected function prepare(): void
    {
       $this->addConstraint(new InGameRequiredConstraint($this));
       $this->setPermission(DefaultPermissions::ROOT_USER);
        $this->registerSubCommand(new subAdminCommand($this->getOwningPlugin(), "admin", "UI d'administation"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        /**
         * @var Player $sender
         */
        JobManager::getInstance()->UIIndexSelectJob($sender);
    }
}