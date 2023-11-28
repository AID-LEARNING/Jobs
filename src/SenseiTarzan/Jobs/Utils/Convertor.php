<?php

namespace SenseiTarzan\Jobs\Utils;

use Closure;
use Exception;
use Generator;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\item\ItemTypeDeserializeException;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\event\Event;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use ReflectionException;
use ReflectionFunction;
use SenseiTarzan\Jobs\Class\Exception\DataJobLoadException;
use SenseiTarzan\Jobs\Class\Job\DataJob;
use SOFe\AwaitGenerator\Await;

class Convertor
{

    /**
     * @param array $data
     * @return Generator<DataJob[]>
     * @throws DataJobLoadException
     */
    public static function jsonToDataJson(Player $player, array $data): Generator
    {
        return Await::promise(function ($resolve, $reject) use ($player, $data) {
            try {
                array_walk($data, fn(array $dataJson, string $key) => DataJob::create($player, $key, ($dataJson['level'] ?? 0), ($dataJson['progression'] ?? 0)));
                $resolve($data);
            } catch (\Throwable){
                $reject(new DataJobLoadException("Impossible de charger ses jobs"));
            }
        });
    }



    /**
     * @param Item $item
     * @return array
     */
    private static function itemToJson(Item $item): array
    {
        $serialized = GlobalItemDataHandlers::getSerializer()->serializeType($item);
        $data = ['id' => $serialized->getName(), "damage" => $item instanceof Durable ? $item->getDamage() : $serialized->getMeta(), "count" => $item->getCount()];
        if (($nbt = $item->getNamedTag())->count() !== 0){
            $data['nbt_b64'] = base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($nbt)));
        }
        return $data;
    }


    public static function jsonToItem(array $info): Item
    {
        if (!isset($info['id'])){
            return VanillaBlocks::AIR()->asItem();
        }
        if (is_numeric($info['id'])) {
            try {
                $item = Item::legacyJsonDeserialize($info);
                if (isset($info['customName'])) {
                    $item->setCustomName($info['customName']);
                }
            } catch (Exception) {
                $item = clone VanillaBlocks::INFO_UPDATE()->asItem()->setCustomName(TextFormat::DARK_RED . TextFormat::BOLD . "Error Item " . $info['id'] . ":" . ($info["damage"] ?? 0) . TextFormat::RESET . TextFormat::RED . " not found");
            }
        } else {
            try {
                $item = self::upgradeItemJSON($info);
                if (isset($info['customName'])) {
                    $item->setCustomName($info['customName']);
                }
            } catch (Exception) {
                $item = clone VanillaBlocks::INFO_UPDATE()->asItem()->setCustomName(TextFormat::DARK_RED . TextFormat::BOLD . "Error Item " . $info['id'] . ":" . ($info["damage"] ?? 0) . TextFormat::RESET . TextFormat::RED . " not found");
            }
        }

        if (isset($info['enchant'])) {
            $item->removeEnchantments();
            foreach ($info['enchant'] as $id => $lvl) {
                $enchant = EnchantmentIdMap::getInstance()->fromId($id);
                if ($enchant === null) continue;
                $item->addEnchantment(new EnchantmentInstance($enchant, $lvl));
            }
        }

        if (isset($info['lore'])) {
            $item->setLore($info['lore']);
        }

        return $item;
    }

    /**
     * @param array $info
     * @return Item
     * @throws SavedDataLoadingException
     */
    private static function upgradeItemJSON(array $info): Item
    {
        $nbt = "";

        //Backwards compatibility
        if (isset($data["nbt"])) {
            $nbt = $data["nbt"];
        } elseif (isset($data["nbt_hex"])) {
            $nbt = hex2bin($data["nbt_hex"]);
        } elseif (isset($data["nbt_b64"])) {
            $nbt = base64_decode($data["nbt_b64"], true);
        }
        $itemStackData = GlobalItemDataHandlers::getUpgrader()->upgradeItemTypeDataString($info['id'], $info['damage'] ?? 0, $info['count'] ?? 1,
            $nbt !== "" ? (new LittleEndianNbtSerializer())->read($nbt)->mustGetCompoundTag() : null
        );

        try {
            return GlobalItemDataHandlers::getDeserializer()->deserializeStack($itemStackData);
        } catch (ItemTypeDeserializeException $e) {
            throw new SavedDataLoadingException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws ReflectionException
     */
    public static function getEventsHandledBy(Closure $closure): ?string
    {
        $method = new ReflectionFunction($closure);
        if ($method->isStatic()) {
            return null;
        }

        $parameters = $method->getParameters();
        if (count($parameters) !== 1) {
            return null;
        }

        $paramType = $parameters[0]->getType();
        if (!$paramType instanceof \ReflectionNamedType || $paramType->isBuiltin()) {
            return null;
        }

        /** @phpstan-var class-string $paramClass */
        $paramClass = $paramType->getName();
        $eventClass = new \ReflectionClass($paramClass);
        if (!$eventClass->isSubclassOf(Event::class)) {
            return null;
        }

        return $eventClass->getName();
    }
}