<?php

namespace SenseiTarzan\Jobs\Class\Job;

use Generator;
use muqsit\asynciterator\handler\AsyncForeachResult;
use pocketmine\item\Item;
use pocketmine\player\Player;
use SenseiTarzan\IconUtils\IconForm;
use SenseiTarzan\Jobs\Class\Exception\PLayerFullInventoryExecution;
use SenseiTarzan\Jobs\Main;
use SenseiTarzan\Jobs\Utils\Convertor;
use SenseiTarzan\Jobs\Utils\CustomKnownTranslationFactory;
use SenseiTarzan\LanguageSystem\Component\LanguageManager;
use SOFe\AwaitGenerator\Await;

class Award
{
    /** @var string */
    private readonly string $id;
    /** @var string */
    private string $type;
    /** @var string */
    private string $descriptionDefault;
    /** @var IconForm */
    private IconForm $icon;
    /** @var Item|string|Item[] */
    private Item|string|array $item;

    private Job $parent;

    public function __construct(private readonly string $name, string $type, string $descriptionDefault, string $icon, Item|string|array $item)
    {
        $this->id = mb_strtolower($this->name);
        $this->type = mb_strtolower($type);
        $this->descriptionDefault = $descriptionDefault;
        $this->icon = IconForm::create($icon);
        $this->item = $item;
    }

    public static function create(array $data): self
    {
        return new self($data["name"], $typeGiveaway = mb_strtolower($data["type"]), $data["description"], $data["icon"], match ($typeGiveaway) {
            "item" => Convertor::jsonToItem($data["item"]),
            "items" => array_map(fn (array $info) => Convertor::jsonToItem($info), $data['item']),
            default => (string)$data["item"]
        });
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param Player|null $player
     * @return string
     */
    public function getName(?Player $player = null): string
    {
        return $player === null ? $this->name : LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::name_award($this->getId()), default: $this->name);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getDescriptionDefault(): string
    {
        return $this->descriptionDefault;
    }

    public function getDescription(?Player $player = null): string
    {
        return $player === null ? $this->getDescriptionDefault() : LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::description_award($this->getId()), default:  $this->getDescriptionDefault());
    }

    /**
     * @return Job
     */
    public function getParent(): Job
    {
        return $this->parent;
    }

    /**
     * @param Job $parent
     */
    public function setParent(Job $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return IconForm
     */
    public function getIcon(): IconForm
    {
        return $this->icon;
    }

    /**
     * @return float|int|string|Item|Item[]
     */
    public function getItem(): array|float|int|string|Item
    {
        return $this->item;
    }

    public function isItem(): bool
    {
        return $this->type === "item";
    }
    public function isItems(): bool
    {
        return $this->type === "items";
    }
    public function preGive(Player $player): Generator
    {
        return Await::promise(function ($resolve, $reject) use ($player): void {
            Await::f2c(function () use($player): Generator{
                yield from $this->canReceiveGiveaway($player);
                return ["type" => $this->getType(), "item" => $this->getItem()];
            }, $resolve, $reject);
        });
    }

    /**
     * @param Player $player
     * @return Generator
     */
    public function canReceiveGiveaway(Player $player): Generator
    {
        return Await::promise(function ($resolve, $reject) use ($player): void {
            if ($this->isItem()) {
                $player->getInventory()->canAddItem($this->getItem()) ? $resolve() : $reject(new PLayerFullInventoryExecution());
            } else if ($this->isItems()) {
	            $handler_async = Main::getInstance()->getIteratorAsync()->forEach(new \ArrayIterator($this->getItem()));
	            $handler_async->as(function (int $key, Item $item) use ($player){
		            if (!$player->getInventory()->canAddItem($item))
			            return AsyncForeachResult::INTERRUPT();
		            return AsyncForeachResult::CONTINUE();
	            });
	            Await::g2c(Main::getIteratorAsyncAwait($handler_async, new PLayerFullInventoryExecution()), $resolve, $reject);
			}else {
	            $resolve();
            }
        });
    }
}