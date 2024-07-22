<?php


namespace SenseiTarzan\Jobs\Class\Job;

use Closure;
use Generator;
use muqsit\asynciterator\handler\AsyncForeachResult;
use pocketmine\event\EventPriority;
use pocketmine\event\RegisteredListener;
use pocketmine\network\mcpe\handler\SpawnResponsePacketHandler;
use pocketmine\player\Player;
use pocketmine\Server;
use ReflectionException;
use SenseiTarzan\IconUtils\IconForm;
use SenseiTarzan\Jobs\Class\Exception\JobHasGiveawayException;
use SenseiTarzan\Jobs\Class\Exception\JobNotFoundException;
use SenseiTarzan\Jobs\Class\Exception\JobPlayerNoExistException;
use SenseiTarzan\Jobs\Component\JobManager;
use SenseiTarzan\Jobs\Component\JobPlayerManager;
use SenseiTarzan\ExtraEvent\Component\EventLoader;
use SenseiTarzan\LanguageSystem\Component\LanguageManager;
use SenseiTarzan\Jobs\Main;
use SenseiTarzan\Jobs\Utils\Convertor;
use SenseiTarzan\Jobs\Utils\CustomKnownTranslationFactory;
use SenseiTarzan\RoleManager\Component\RoleManager;
use SenseiTarzan\RoleManager\Component\RolePlayerManager;
use SOFe\AwaitGenerator\Await;
class Job
{
    /**
     * @var string
     */
    private readonly string $id;
    /**
     * @var Award[]
     */
    private array $listAward;

	private readonly int $maxLvl;

    private readonly JobCommandSender $commandSenderJob;

    private readonly RegisteredListener $registeredListener;
    private ?string $eventClass;
    private readonly IconForm $iconForm;
	/**
	 * @var array<string, ActionToXP>
	 */
	private array $listActionToXp;

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
    public function __construct(private readonly string $name, string $pathIcon, private readonly string $descriptionDefault, array $listActionToXp, private array $listXpByLevel, array $listGiveaway, Closure $event)
    {
        $this->id = mb_strtolower($this->name);
        $this->iconForm = IconForm::create($pathIcon);
	    $this->listAward = array_map(function (array $giveaway) {return Award::create($giveaway); }, $listGiveaway);
	    $this->listActionToXp = array_map(function (array|float $actionToXp) {return ActionToXP::create($this, $actionToXp); }, $listActionToXp);
        $this->commandSenderJob = new JobCommandSender($this, Server::getInstance(), Server::getInstance()->getLanguage());
	    ksort($this->listXpByLevel);
		$this->maxLvl = $this->listXpByLevel[array_key_last($this->listXpByLevel)];
        $eventFinal = ($event)($this);
        if ($eventFinal instanceof Closure) {
            $this->eventClass = Convertor::getEventsHandledBy($eventFinal);
            $this->registeredListener = Server::getInstance()->getPluginManager()->registerEvent($this->eventClass, $eventFinal, EventPriority::MONITOR, Main::getInstance());
        }else {
            Main::getInstance()->getLogger()->alert("Tu ne peux pas utilise le /jobs reload");
            EventLoader::loadEventWithClass(Main::getInstance(), $eventFinal);
        }
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
        return $player ? LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::name_job($this->getId()), default: $this->name) : $this->name;
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

    public function hasEventClosure(): bool{
        return isset($this->registeredListener);
    }

    /**
     * @return string
     */
    public function getDescriptionDefault(): string
    {
        return $this->descriptionDefault;
    }

    public function getDescription(?Player $player = null): string{
        return $player ? LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::description_job($this->getId()), default: $this->getDescriptionDefault()) : $this->getDescriptionDefault();
    }

	/**
	 * @return int
	 */
	public function getMaxLvl(): int
	{
		return $this->maxLvl;
	}

    /**
     * @return array<string, ActionToXP>
     */
    public function getListActionToXp(): array
    {
        return $this->listActionToXp;
    }

    public function getXpByAction(mixed $key, JobPlayer|Player $player): ?float{
		return $this->listActionToXp[$key]->getXp(($player instanceof Player ? JobPlayerManager::getInstance()->getPlayer($player) : $player));
    }

    public function existAction(mixed $key, JobPlayer|Player $player): bool
    {
        return isset($this->listActionToXp[$key]) && $this->listActionToXp[$key]->canRun(($player instanceof Player ? JobPlayerManager::getInstance()->getPlayer($player) : $player));
    }

    /**
     * @return array
     */
    public function getListXpByLevel(): array
    {
        return $this->listXpByLevel;
    }

	public function isMaxLevel(int $level): bool
	{
		return $this->maxLvl >= $level;
	}


    public function getXpByLevel(int $level): ?float{
        return $this->listXpByLevel[$level] ?? null;
    }
    /**
     * @return array
     */
    public function getListAward(): array
    {
        return $this->listAward;
    }

    /**
     * @return JobCommandSender
     */
    public function getCommandSenderJob(): JobCommandSender
    {
        return $this->commandSenderJob;
    }

    public function getAward(int $level): Award{
        return $this->listAward[$level];
    }

    public function getAwards() : array {
        return $this->listAward;
    }

    public function sendAwardAfterLeveling(Player $player, int $level): Generator {
        return Await::promise(function ($resolve, $reject) use ($player, $level): void{
            if ($player->hasPermission("{$this->getId()}.giveaway.$level")){
                $reject(new JobHasGiveawayException());
                return;
            }
			$jobId = $this->getId();
            Await::f2c(function () use ($player, $level, $jobId): Generator{
                $award = $this->getAward($level);
                $giveaway = yield from $award->preGive($player);
                RolePlayerManager::getInstance()->getPlayer($player)->getAttachment()?->setPermission("$jobId.giveaway.$level", true);
                yield from RoleManager::getInstance()->addPermissionPlayer($player, "$jobId.giveaway.$level");
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
					$handler_async = Main::getInstance()->getIteratorAsync()->forEach(new \ArrayIterator($commands));
					$handler_async->as(function (int $key, string $command) use ($server, $player){
						$server->dispatchCommand($this->getCommandSenderJob(), str_replace("{&player}", "\"{$player->getName()}\"", $command), true);
						return AsyncForeachResult::CONTINUE();
					});
					yield from Main::getIteratorAsyncAwait($handler_async);
                } else if ($type === "permission"){
                    yield from RoleManager::getInstance()->addPermissionPlayer($player, $giveaway['item']);
                }
                return $award->getName($player);
            }, $resolve, static function(\Throwable $throwable) use ($jobId, $player, $level, $reject){
                RolePlayerManager::getInstance()->getPlayer($player)->getAttachment()?->unsetPermission("{$jobId}.giveaway.$level");
                $reject($throwable);
            });
        });
    }

    /**
     * @param Player $player
     * @param float $progression
     * @return Generator
     * @throws JobNotFoundException
     */
    public function addPlayerProgression(Player $player, float $progression): Generator{
        $player->sendActionBarMessage(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::add_xp_job_message($this->getName($player), $progression)));
        return Await::promise(function ($resolve, $reject) use ($player, $progression) {
            $jobPlayer = JobPlayerManager::getInstance()->getPlayer($player);
            if ($jobPlayer == null) {
                $reject(new JobPlayerNoExistException("{$player->getName()} no exist in JobPlayerManager"));
                return;
            }
            $jobData = $jobPlayer->getJob($this->getId());
           Await::f2c(function () use($jobData, $player, $progression){
               yield from $jobData->addProgression($progression);
               return yield from $jobData->levelUp();
           }, function(int $level) use ($resolve, $player) {
               $player->sendTitle(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::level_up_job_message($this->getName($player), $level)));
               $resolve();
           }, $reject);
        });
    }
}