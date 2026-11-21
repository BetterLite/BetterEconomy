<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\command;

use Mellooh\BetterEconomy\BetterEconomy;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function is_numeric;
use function strtolower;

final class PayCommand extends BaseCommand {

    public function __construct(BetterEconomy $plugin) {
        parent::__construct(
            $plugin,
            "pay",
            "Pay another player",
            "/pay <player> <amount>",
            "bettereconomy.command.pay"
        );
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$this->testPermissionSilent($sender)) {
            $this->sendNoPermission($sender);
            return;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage($this->lang()->get("general.player-only"));
            return;
        }

        if (!isset($args[0], $args[1])) {
            $sender->sendMessage($this->lang()->get("pay.usage"));
            return;
        }

        $target = $args[0];
        if (strtolower($target) === strtolower($sender->getName())) {
            $sender->sendMessage($this->lang()->get("pay.self"));
            return;
        }

        if (!is_numeric($args[1])) {
            $sender->sendMessage($this->lang()->get("general.invalid-amount"));
            return;
        }

        $amount = $this->economy()->round((float) $args[1]);
        if ($amount <= 0.0) {
            $sender->sendMessage($this->lang()->get("general.invalid-amount"));
            return;
        }
        if ($amount > 1_000_000_000_000.0) {
            $sender->sendMessage($this->lang()->get("general.amount-too-large"));
            return;
        }

        $senderBalance = $this->economy()->getBalance($sender->getName());
        if ($senderBalance === null) {
            $sender->sendMessage($this->lang()->get("general.account-not-found", ["player" => $sender->getName()]));
            return;
        }

        if ($senderBalance < $amount) {
            $sender->sendMessage($this->lang()->get("pay.insufficient", [
                "balance" => $this->economy()->format($senderBalance)
            ]));
            return;
        }

        $targetBalance = $this->economy()->getBalance($target);
        if ($targetBalance === null) {
            $sender->sendMessage($this->lang()->get("general.account-not-found", ["player" => $target]));
            return;
        }

        if (!$this->economy()->transfer($sender->getName(), $target, $amount)) {
            $sender->sendMessage($this->lang()->get("general.database-error"));
            return;
        }

        $newSenderBalance = $this->economy()->getBalance($sender->getName()) ?? ($senderBalance - $amount);
        $newTargetBalance = $this->economy()->getBalance($target) ?? ($targetBalance + $amount);

        $sender->sendMessage($this->lang()->get("pay.success-sender", [
            "amount" => $this->economy()->format($amount),
            "player" => $target,
            "balance" => $this->economy()->format($newSenderBalance)
        ]));

        $targetPlayer = $this->plugin->getServer()->getPlayerExact($target);
        if ($targetPlayer !== null) {
            $targetPlayer->sendMessage($this->lang()->get("pay.success-receiver", [
                "amount" => $this->economy()->format($amount),
                "player" => $sender->getName(),
                "balance" => $this->economy()->format($newTargetBalance)
            ]));
        }
    }
}
