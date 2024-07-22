<?php

namespace SenseiTarzan\Jobs\Listener;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\EventPriority;
use SenseiTarzan\ExtraEvent\Class\EventAttribute;
use SenseiTarzan\Jobs\Class\Save\BlockPlaceData;
use SenseiTarzan\Jobs\Main;

class BlockListener
{

	#[EventAttribute(EventPriority::MONITOR)]
	public function onPlace(BlockPlaceEvent $event): void{
		if ($event->isCancelled()) return;
		foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
			Main::getInstance()->getBlockData()->get($event->getPlayer()->getWorld())->setBlockDataAt($x, $y, $z, new BlockPlaceData(($block->getStateId() & Block::INTERNAL_STATE_DATA_MASK)));
		}
	}

	#[EventAttribute(EventPriority::MONITOR)]
	public function onBreak(BlockBreakEvent $event): void
	{
		if ($event->isCancelled()) return;
		$position = $event->getBlock()->getPosition();
		Main::getInstance()->getBlockData()->get($event->getPlayer()->getWorld())->setBlockDataAt($position->getFloorX(), $position->getFloorY(), $position->getFloorZ(), null);
	}

}