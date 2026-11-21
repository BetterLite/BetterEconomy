<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\command;

use Mellooh\BetterEconomy\BetterEconomy;
use Mellooh\BetterEconomy\language\LanguageManager;
use Mellooh\BetterEconomy\manager\EconomyManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\Plugin;

abstract class BaseCommand extends Command implements PluginOwned {

    public function __construct(
        protected readonly BetterEconomy $plugin,
        string $name,
        string $description,
        string $usageMessage,
        string $permission,
        array $aliases = []
    ) {
        parent::__construct($name, $description, $usageMessage, $aliases);
        $this->setPermissions([$permission]);
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }

    protected function economy(): EconomyManager {
        return $this->plugin->getEconomyManager();
    }

    protected function lang(): LanguageManager {
        return $this->plugin->getLanguageManager();
    }

    public function testPermissionSilent(CommandSender $target, ?string $permission = null): bool {
        if ($permission !== null && $permission !== "") {
            return $target->hasPermission($permission);
        }

        $permissions = $this->getPermissions();
        if (count($permissions) === 0) {
            return true;
        }

        foreach ($permissions as $perm) {
            if ($target->hasPermission($perm)) {
                return true;
            }
        }

        return false;
    }

    protected function sendNoPermission(CommandSender $sender): void {
        $sender->sendMessage($this->lang()->get("general.no-permission"));
    }
}
