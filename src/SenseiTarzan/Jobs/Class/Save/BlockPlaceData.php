<?php

namespace SenseiTarzan\Jobs\Class\Save;

use cosmicpe\blockdata\BlockData;
use pocketmine\nbt\tag\CompoundTag;

class BlockPlaceData implements BlockData { // stores when block was placed and by whom.

    public static function nbtDeserialize(CompoundTag $nbt) : BlockData{
        return new BlockPlaceData($nbt->getLong("data", 0));
    }
    public function __construct(private readonly int $data)
    {
    }

	/**
	 * @return int
	 */
	public function getData(): int
	{
		return $this->data;
	}

    public function nbtSerialize() : CompoundTag{
        return CompoundTag::create()->setLong("data", $this->data);
    }
}