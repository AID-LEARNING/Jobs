<?php

namespace SenseiTarzan\Jobs\Commands\args;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\TargetPlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\player\Player;
use pocketmine\Server;

class PlayerArgument  extends BaseArgument{
    public function __construct(?string $name = null, bool $optional = false){
        $name = is_null($name) ? "player" : $name;

        parent::__construct($name, $optional);
    }

    public function getTypeName(): string{
        return "target";
    }

    public function getNetworkType(): int{
        return AvailableCommandsPacket::ARG_TYPE_TARGET;
    }

    public function canParse(string $testString, CommandSender $sender): bool{
        return (bool) preg_match("/^(?!rcon|console)[a-zA-Z0-9_ ]{1,16}$/i", $testString);
    }

    public function parse(string $argument, CommandSender $sender): string|Player
    {
        return Server::getInstance()->getPlayerExact($argument) ?? $argument;
    }
}