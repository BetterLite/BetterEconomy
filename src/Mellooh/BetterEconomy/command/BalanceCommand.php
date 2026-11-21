<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\command;

use Mellooh\BetterEconomy\BetterEconomy;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class BalanceCommand extends BaseCommand {

    public function __construct(BetterEconomy $plugin) {
        parent::__construct(
            $plugin,
            "balance",
            "View your balance or another player's balance",
            "/balance [player]",
            "bettereconomy.command.balance",
            ["bal", "money"]
        );
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$this->testPermissionSilent($sender)) {
            $this->sendNoPermission($sender);
            return;
        }

        if (isset($args[0]) && $args[0] !== "") {
            if (!$sender->hasPermission("bettereconomy.command.balance.other")) {
                $this->sendNoPermission($sender);
                return;
            }

            $target = $args[0];
            $balance = $this->economy()->getBalance($target);
            if ($balance === null) {
                $sender->sendMessage($this->lang()->get("general.account-not-found", ["player" => $target]));
                return;
            }

            $sender->sendMessage($this->lang()->get("balance.other", [
                "player" => $target,
                "balance" => $this->economy()->format($balance)
            ]));
            return;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage($this->lang()->get("general.player-only"));
            return;
        }

        $balance = $this->economy()->getBalance($sender->getName());
        if ($balance === null) {
            $sender->sendMessage($this->lang()->get("general.account-not-found", ["player" => $sender->getName()]));
            return;
        }

        $sender->sendMessage($this->lang()->get("balance.self", [
            "balance" => $this->economy()->format($balance)
        ]));
    }
}
