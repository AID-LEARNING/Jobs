<?php

namespace SenseiTarzan\Jobs\Component;

use Closure;
use pocketmine\event\HandlerListManager;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use ReflectionException;
use SenseiTarzan\Jobs\Class\Exception\JobAddException;
use SenseiTarzan\Jobs\Class\Exception\JobExistException;
use SenseiTarzan\Jobs\Class\Exception\JobHasGiveawayException;
use SenseiTarzan\Jobs\Class\Exception\PLayerFullInventoryExecution;
use SenseiTarzan\Jobs\Class\Job\DataJob;
use SenseiTarzan\Jobs\Class\Job\Job;
use SenseiTarzan\Jobs\Class\Job\JobPlayer;
use SenseiTarzan\LanguageSystem\Component\LanguageManager;
use SOFe\AwaitGenerator\Await;
use SenseiTarzan\Jobs\Main;
use SenseiTarzan\Jobs\Utils\CustomKnownTranslationFactory;
use SenseiTarzan\Path\PathScanner;
use jojoe77777\FormAPI\SimpleForm;
use Symfony\Component\Filesystem\Path;

class JobManager
{
    use SingletonTrait;

    /**
     * @var Job[]
     */
    private array $jobs = [];

    /**
     * @var array
     */
    private array $defaultData = [];

    private Config $config;

    /**
     * @throws ReflectionException
     * @throws JobExistException
     */
    public function __construct(private readonly Main $plugin)
    {
        self::setInstance($this);
        $this->config = $this->plugin->getConfig();
        $this->loadDefaultJob();
    }

    /**
     * @throws JobExistException
     * @throws ReflectionException|JobAddException
     */
    public function loadDefaultJob(): void{
        foreach (PathScanner::scanDirectoryToConfig(Path::join($this->plugin->getDataFolder(), "jobs"), ["yml"]) as $config){
            /**
             * @var Closure $event;
             * @phpstan-param Closure(Job $_job) : object $event
             */
            $event = eval($config->get('event'));
            $this->addJob(
                new Job(
                    (string) $config->get('name'),
                    (string) $config->get('icon'),
                    (string) $config->get('description'),
                    (array) $config->get('listActionToXp', []),
                    (array) $config->get('listXpByLevel', []),
                    (array) $config->get('listGiveaway', []),
                    $event
                )
            );
        }
    }

    /**
     * @return array
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    public function getJob(string $job): ?Job{
        return $this->jobs[$job] ?? null;
    }

    /**
     * @param array $jobs
     */
    public function setJobs(array $jobs): void
    {
        $this->jobs = $jobs;
    }

    /**
     * @param Job $job
     * @param bool $overwrite
     * @return void
     * @throws JobExistException|ReflectionException|JobAddException
     */
    private function addJob(Job $job, bool $overwrite = false): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        if (count($trace) > 1) {
            $caller = $trace[1];
            if (!isset($caller['file']) || (isset($caller['object']) && !($caller['object'] instanceof $this)) || (isset( $caller['function']) && str_contains($caller['function'], "{closure}"))){
                throw new JobAddException('Vous ne pouvez pas appele cette method hors de JobManager pour eviter l\'injection de code');
            }
        }
        $existJob = isset($this->jobs[$job->getId()]);
        if (!$overwrite && $existJob){
            throw new JobExistException("this id {$job->getId()} is exist in list Jobs");
        }
        if ($existJob){
            $jobInList = $this->jobs[$job->getId()];
            if ($jobInList->hasEventClosure())
                HandlerListManager::global()->getListFor($jobInList->getEventClass())->unregister($jobInList->getRegisteredListener());
        }
        $this->jobs[$job->getId()] = $job;
        $this->defaultData[$job->getId()] = ['level' => 0, 'progression' => 0];
    }

    /**
     * @throws ReflectionException
     * @throws JobExistException
     */
    public function reloadAll(): void{
        foreach ($this->jobs as $job){
            if ($job->hasEventClosure())
                HandlerListManager::global()->getListFor($job->getEventClass())->unregister($job->getRegisteredListener());
        }
        $this->jobs = [];
        $this->loadDefaultJob();
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    public function getDefaultData(): array
    {
        return $this->defaultData;
    }

    public function UIIndexSelectJob(Player $player): void{
        $jobPlayer = JobPlayerManager::getInstance()->getPlayer($player);
        if ($jobPlayer === null) return;
        $ui = new SimpleForm(function (Player $player, ?string $job) use ($jobPlayer): void{
            if($job === null) return;
            $this->UIJob($player, $jobPlayer->getJob($job));
        });

        $ui->setTitle(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::title_job(), default: "Jobs"));
        foreach ($jobPlayer->getJobs() as $jobData){
            $jobClass = $jobData->getJob();
            $ui->addButton(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::principal_button($jobClass->getName($player), $this->getJobXpBar($jobData))), $jobClass->getIconForm()->getType(), $jobClass->getIconForm()->getPath(), $jobData->getId());
        }
        $player->sendForm($ui);
    }

    private function UIJob(Player $player, DataJob $job): void{

        $ui = new SimpleForm(function (Player $player, ?int $data) use($job): void{
            if ($data === 0){
                $this->UIHowToXp($player,$job->getJob());
            }else if($data === 3){
                $this->UIGiveaway($player, $job);
            }
        });

        $ui->setTitle($job->getJob()->getName($player));
        $ui->addButton(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::description_button()));
        $ui->addButton(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::level_button($job->getLevel())));
        $ui->addButton(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::xp_bar_button($job->getProgression(), ($job->getJob()->getXpByLevel($job->getLevel() + 1) ?? $job->getProgression()))));
        $ui->addButton(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::awards_button()));
        $player->sendForm($ui);
    }

    private  function UIHowToXp(Player $player, Job $job): void{
        $ui = new SimpleForm(function (Player $player, ?int $data){

        });
        $ui->setTitle($job->getName($player));
        $ui->setContent($job->getDescription($player));
        $player->sendForm($ui);
    }

    private function UIGiveaway(Player $player, DataJob $dataJob): void{
        $job = $dataJob->getJob();
        $ui = new SimpleForm(function (Player $player, ?string $data) use($job){
            if ($data == null) return ;
            Await::g2c($job->sendAwardAfterLeveling($player, intval($data, 10)), function (string $name) use($player){
                $player->sendMessage(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::award_job_message($name)));
            }, [
	            PLayerFullInventoryExecution::class => function () use ($player, $job, $data) {
					$player->sendMessage(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::error_get_award($player, $job->getAward($data), CustomKnownTranslationFactory::error_inventory_full()), "Vous n'avez pas pu recuperer la recompence {&name}: {&errno}"));
	            },
	            JobHasGiveawayException::class  => function () use ($player, $job, $data){
		            $player->sendMessage(LanguageManager::getInstance()->getTranslateWithTranslatable($player, CustomKnownTranslationFactory::error_get_award($player, $job->getAward($data), CustomKnownTranslationFactory::error_double_request()), "Vous n'avez pas pu recuperer la recompence {&name}: {&errno}"));
	            },
	            \Exception::class => function () {

	            }
            ]);
        });
        $ui->setTitle($job->getName($player));
        foreach ($job->getAwards() as $level => $giveaway){
            if ($player->hasPermission("{$job->getId()}.giveaway.$level") || $level > $dataJob->getLevel()) continue;
            $ui->addButton($giveaway->getName($player) , $giveaway->getIcon()->getType(), $giveaway->getIcon()->getPath(), $level);
        }
        $player->sendForm($ui);

    }


    public function UIIndexAdmin(Player $player): void{
        $ui = new SimpleForm(function (Player $player, ?int $data){
            if ($data === 0){
                $this->UISelectPlayerAdmin($player);
            }
        });

        $ui->setTitle("AdminUI");
        $ui->addButton("View history awards");
        $player->sendForm($ui);
    }

    public function UISelectPlayerAdmin(Player $player): void{
        $ui = new SimpleForm(function (Player $player, ?string $name){
            if ($name === null) return;
            $target = Server::getInstance()->getPlayerByRawUUID($name);
            if ($target === null){
                $this->UISelectPlayerAdmin($player);
                return ;
            }
            $jobPlayer = JobPlayerManager::getInstance()->getPlayer($target);
            $this->UIViewJobAwardAdmin($player, $jobPlayer);
        });
        $ui->setTitle("Select Player");
        foreach (JobPlayerManager::getInstance()->getPlayers() as $jobPlayer) {
            $ui->addButton($jobPlayer->getPlayer()->getName(), label: $jobPlayer->getPlayer()->getUniqueId()->getBytes());
        }
        $player->sendForm($ui);
    }

    public function UIViewJobAwardAdmin(Player $player, JobPlayer $target): void{
        $ui = new SimpleForm(
            function (Player $player, ?string $job) use ($target){
                if ($job === null) return ;
                $this->UIViewAwardAdmin($player, $target, $target->getJob($job));
            }
        );
        $ui->setTitle($target->getPlayer()->getName());
        foreach ($target->getJobs() as $dataJob){
            $ui->addButton($dataJob->getName($player), $dataJob->getJob()->getIconForm()->getType(), $dataJob->getJob()->getIconForm()->getPath(), $dataJob->getId());
        }
        $player->sendForm($ui);
    }

    public function UIViewAwardAdmin(Player $player, JobPlayer $target, DataJob $dataJob): void
    {
        $ui = new SimpleForm(function (Player $player) use($target){
            $this->UIViewJobAwardAdmin($player, $target);
        });
        $ui->setTitle($target->getPlayer()->getName() . " " . $dataJob->getName($player));
        foreach ($dataJob->getJob()->getListAward() as $level => $award){
            if (!$target->getPlayer()->hasPermission("{$dataJob->getId()}.giveaway.$level")) continue;
            $ui->addButton($award->getName($player), $award->getIcon()->getType(), $award->getIcon()->getPath());
        }
        $player->sendForm($ui);
    }
    /**
     * Donne la bar de progression du Job donner
     * @param DataJob $job
     * @return string
     */
    public function getJobXpBar(DataJob $job): string
    {
        $xp = $job->getProgression();
        $nextLvlXp = $job->getJob()->getXpByLevel($job->getLevel() + 1) ?? $xp;

        $greenBar = round($xp * 100 / $nextLvlXp, -1);
        $greenBar = $greenBar / 10;
        $blankBar = 10 - $greenBar;
        $xpBar = str_repeat('§c▬', $greenBar);

        if ($blankBar > 0) {
            $xpBar .= str_repeat('§0▬', $blankBar);
        }
        return $xpBar;
    }


    public static function getInstance(): JobManager
    {
        return self::$instance;
    }
}