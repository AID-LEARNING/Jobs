<?php

namespace SenseiTarzan\Jobs\Listener;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use SenseiTarzan\DataBase\Component\DataManager;
use SenseiTarzan\ExtraEvent\Class\EventAttribute;
use SenseiTarzan\Jobs\Class\Save\BlockPlaceData;
use SenseiTarzan\Jobs\Main;
use pocketmine\block\Block;

class PlayerListener
{
	public function __construct(private readonly bool $hasMiddleware)
	{
	}

	#[EventAttribute(EventPriority::LOWEST)]
    public function onJoin(PlayerJoinEvent $event): void{
		if (!$this->hasMiddleware)
            DataManager::getInstance()->getDataSystem()->loadDataPlayer($event->getPlayer());
    }

    #[EventAttribute(EventPriority::LOWEST)]
    public function onQuit(PlayerQuitEvent $event): void{
        $player = $event->getPlayer();
        DataManager::getInstance()->getDataSystem()->onDisconnectPlayer($player);
    }
}