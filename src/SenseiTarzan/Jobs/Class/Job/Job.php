<?php


namespace SenseiTarzan\Jobs\Class\Job;

use Closure;
use Generator;
use pocketmine\event\EventPriority;
use pocketmine\event\RegisteredListener;
use pocketmine\network\mcpe\handler\SpawnResponsePacketHandler;
use pocketmine\player\Player;
use pocketmine\Server;
use ReflectionException;
use SenseiTarzan\IconUtils\IconForm;
use SenseiTarzan\Jobs\Main;
use SenseiTarzan\Jobs\Utils\Convertor;
use SOFe\AwaitGenerator\Await;
use pocketmine\event\Event;
class Job
{
    /**
     * @var string
     */
    private readonly string $id;
    /**
     * @var Giveaway[]
     */
    private array $listGiveaway;

    private readonly JobCommandSender $commandSenderJob;

    private readonly RegisteredListener $registeredListener;
    private ?string $eventClass;
    private readonly IconForm $iconForm;

    /**
     * @param string $name
     * @param string $pathIcon
     * @param string $descriptionDefault
     * @param array $listActionToXp
     * @param array $listXpByLevel
     * @param array $listGiveaway
     * @param Closure $event
     * @phpstan-param Closure(Job $_job) : Closure $event
     * @throws ReflectionException
     */
    public function __construct(private readonly string $name, string $pathIcon, private readonly string $descriptionDefault, private readonly array $listActionToXp, private readonly array $listXpByLevel, array $listGiveaway, Closure $event)
    {
        $this->id = mb_strtolower($this->name);
        $this->iconForm = IconForm::create($pathIcon);
        $this->listGiveaway = array_map(function (array $giveaway) {return Giveaway::create($giveaway); }, $listGiveaway);
        $this->commandSenderJob = new JobCommandSender($this, Server::getInstance(), Server::getInstance()->getLanguage());
        $eventFinal = ($event)($this);
        $this->eventClass = Convertor::getEventsHandledBy($eventFinal);
        $this->registeredListener = Server::getInstance()->getPluginManager()->registerEvent($this->eventClass, $eventFinal, EventPriority::NORMAL, Main::getInstance());
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return IconForm
     */
    public function getIconForm(): IconForm
    {
        return $this->iconForm;
    }

    /**
     * @return string|null
     */
    public function getEventClass(): ?string
    {
        return $this->eventClass;
    }

    /**
     * @return RegisteredListener
     */
    public function getRegisteredListener(): RegisteredListener
    {
        return $this->registeredListener;
    }

    /**
     * @return string
     */
    public function getDescriptionDefault(): string
    {
        return $this->descriptionDefault;
    }

    /**
     * @return array
     */
    public function getListActionToXp(): array
    {
        return $this->listActionToXp;
    }

    public function getXpByAction(mixed $key): float{
        return $this->listActionToXp[$key];
    }

    public function existAction(mixed $key): bool
    {
        return isset($this->listActionToXp[$key]);
    }

    /**
     * @return array
     */
    public function getListXpByLevel(): array
    {
        return $this->listXpByLevel;
    }


    public function getXpByLevel(int $level): ?float{
        return $this->listXpByLevel[$level] ?? null;
    }
    /**
     * @return array
     */
    public function getListGiveaway(): array
    {
        return $this->listGiveaway;
    }

    /**
     * @return JobCommandSender
     */
    public function getCommandSenderJob(): JobCommandSender
    {
        return $this->commandSenderJob;
    }

    public function getGiveaway(int $level): Giveaway{
        return $this->listGiveaway[$level];
    }

    public function getGiveaways() : array {
        return $this->listGiveaway;
    }

    public function sendGiveawayAfterLeveling(Player $player, int $level): Generator {
        return Await::promise(function ($resolve, $reject) use ($player, $level): void{
            Await::f2c(function () use ($player, $level): Generator{
                $giveaway = yield from $this->getGiveaway($level)->getGiveaway($player);
                $type = $giveaway["type"];
                if ($type === "item"){
                    $player->getInventory()->addItem($giveaway['item']);
                } else if($type === "items") {
                    $player->getInventory()->addItem(...$giveaway['item']);
                } else if ($type === "command") {
                    Server::getInstance()->dispatchCommand($this->getCommandSenderJob(), str_replace("{&player}", "\"{$player->getName()}\"", $giveaway['item']), true);
                } else if ($type === "commands") {
                    $commands = $giveaway['item'];
                    $server = Server::getInstance();
                    while (($command = array_shift($commands)) !== null){
                        $server->dispatchCommand($this->getCommandSenderJob(), str_replace("{&player}", "\"{$player->getName()}\"", $command), true);
                    }
                }
            }, $resolve, $reject);
        });
    }
}