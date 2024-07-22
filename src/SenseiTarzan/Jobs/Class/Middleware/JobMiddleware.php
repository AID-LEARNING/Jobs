<?php

namespace SenseiTarzan\Jobs\Class\Middleware;

use Generator;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use SenseiTarzan\DataBase\Component\DataManager;
use SenseiTarzan\Middleware\Class\IMiddleWare;

class JobMiddleware implements IMiddleWare
{

	public function getName(): string
	{
		return "Job Middleware";
	}

	/**
	 * @inheritDoc
	 */
	public function onDetectPacket(): string
	{
		return SetLocalPlayerAsInitializedPacket::class;
	}

	public function getPromise(DataPacketReceiveEvent $event): Generator
	{
		return DataManager::getInstance()->getDataSystem()->loadDataPlayerByMiddleware($event->getOrigin()->getPlayer());
	}
}