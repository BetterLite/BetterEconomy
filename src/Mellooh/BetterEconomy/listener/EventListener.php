<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\listener;

use Mellooh\BetterEconomy\BetterEconomy;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;

final class EventListener implements Listener {

    public function __construct(
        private readonly BetterEconomy $plugin
    ) {}

    public function onPlayerLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        if (!$this->plugin->getEconomyManager()->hasAccount($name)) {
            $this->plugin->getEconomyManager()->createAccount($name);
        }
    }
}
