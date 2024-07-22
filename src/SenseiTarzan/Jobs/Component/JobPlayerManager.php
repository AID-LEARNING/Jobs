<?php

namespace SenseiTarzan\Jobs\Component;

use Generator;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use SenseiTarzan\DataBase\Component\DataManager;
use SenseiTarzan\Jobs\Class\Exception\DataJobLoadException;
use SenseiTarzan\Jobs\Class\Exception\JobPlayerLoadException;
use SenseiTarzan\Jobs\Class\Job\JobPlayer;
use SenseiTarzan\Jobs\Utils\Convertor;
use SOFe\AwaitGenerator\Await;
use Throwable;
use WeakMap;

class JobPlayerManager
{
    use SingletonTrait;

    /**
     * @var WeakMap<Player, JobPlayer>
     */
    private WeakMap $players;

    public function __construct()
    {
        $this->players = new WeakMap();
    }

    public function getPlayer(Player $player): ?JobPlayer
    {
        return $this->players[$player] ?? null;
    }

    /**
     * @return WeakMap<Player, JobPlayer>
     */
    public function getPlayers(): WeakMap{
        return $this->players;
    }

    public function loadPlayer(Player $player, JobPlayer $jobPlayer): Generator{
        return Await::promise(function ($resolve, $reject) use($player, $jobPlayer){
            try {
                $this->players[$player] = $jobPlayer;
                $resolve();
            }catch (Throwable){
                $reject(new JobPlayerLoadException("Imposible de charger la session de job pour le joueur {$player->getName()}"));
            }
        });
    }

    public function unload(Player $player): void
    {
        $this->players->offsetUnset($player);
    }
}