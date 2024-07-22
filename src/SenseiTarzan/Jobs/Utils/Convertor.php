<?php

namespace SenseiTarzan\Jobs\Utils;

use Exception;
use Generator;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\convert\BlockObjectToStateSerializer;
use pocketmine\data\bedrock\block\upgrade\LegacyBlockIdToStringIdMap;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\item\ItemTypeDeserializeException;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\event\Event;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use SenseiTarzan\Jobs\Class\Exception\DataJobLoadException;
use SenseiTarzan\Jobs\Class\Job\DataJob;
use SOFe\AwaitGenerator\Await;
use Throwable;

class Convertor
{

    /**
     * @param Player $player
     * @param array $data
     * @return Generator<array<string, DataJob>>
     */
    public static function jsonToDataJson(Player $player, array $data): Generator
    {
        return Await::promise(function ($resolve, $reject) use ($player, $data) {
            try {
                array_walk($data, function(array &$dataJson, string $key) use ($player): void {
                    $dataJson = DataJob::create($key, ($dataJson['level'] ?? 0), ($dataJson['progression'] ?? 0));
                });
                $resolve($data);
            } catch (Throwable){
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

	    $diString = $info['id'];

        //Backwards compatibility
        if (isset($data["nbt"])) {
            $nbt = $data["nbt"];
        } elseif (isset($data["nbt_hex"])) {
            $nbt = hex2bin($data["nbt_hex"]);
        } elseif (isset($data["nbt_b64"])) {
            $nbt = base64_decode($data["nbt_b64"], true);
        }
		$item = null;
	    try {
		    $itemStackData = GlobalItemDataHandlers::getUpgrader()->upgradeItemTypeDataString($info['id'], $info['damage'] ?? 0, $info['count'] ?? 1,
			    $nbt !== "" ? (new LittleEndianNbtSerializer())->read($nbt)->mustGetCompoundTag() : null
		    );
		    $item =  GlobalItemDataHandlers::getDeserializer()->deserializeStack($itemStackData);
	    }catch (\Throwable $e){
		    $item = StringToItemParser::getInstance()->parse($info['id']) ?? LegacyStringToItemParser::getInstance()->parse($info['id']);
		    if (!$item) {
			    throw new SavedDataLoadingException("item : ". $info['id'] . ":" . ($info['damage'] ?? 0), 0, $e);
		    }
		    $item->setCount(($info['count'] ?? 1));
		    $item->setNamedTag((new LittleEndianNbtSerializer())->read($nbt)->mustGetCompoundTag());
	    } finally {
		    if (!$item) {
			    throw new SavedDataLoadingException("item : ". $info['id'] . ":" . ($info['damage'] ?? 0), 0, null);
		    }
		    return $item;
	    }
    }

    /**
     * @throws ReflectionException
     */
    public static function getEventsHandledBy(object $closure): ?string
    {
        $method = new ReflectionFunction($closure);

        $parameters = $method->getParameters();
        if (count($parameters) !== 1) {
            return null;
        }

        $paramType = $parameters[0]->getType();
        if (!$paramType instanceof ReflectionNamedType || $paramType->isBuiltin()) {
            return null;
        }

        /** @phpstan-var class-string $paramClass */
        $paramClass = $paramType->getName();
        $eventClass = new ReflectionClass($paramClass);
        if (!$eventClass->isSubclassOf(Event::class)) {
            return null;
        }

        return $eventClass->getName();
    }
}