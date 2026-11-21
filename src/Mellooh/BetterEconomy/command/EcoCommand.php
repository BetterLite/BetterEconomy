<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\command;

use Mellooh\BetterEconomy\BetterEconomy;
use Mellooh\BetterEconomy\event\BalanceChangeEvent;
use pocketmine\command\CommandSender;
use function array_shift;
use function count;
use function is_numeric;
use function strtolower;

final class EcoCommand extends BaseCommand {

    public function __construct(BetterEconomy $plugin) {
        parent::__construct(
            $plugin,
            "eco",
            "Manage player balances",
            "/eco <give|take|set|reset> <player> [amount]",
            "bettereconomy.command.eco",
            ["economy"]
        );
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$this->testPermissionSilent($sender)) {
            $this->sendNoPermission($sender);
            return;
        }

        if (count($args) < 2) {
            $sender->sendMessage($this->lang()->get("eco.usage"));
            return;
        }

        $sub = strtolower((string) array_shift($args));
        $target = (string) array_shift($args);

        switch ($sub) {
            case "give":
                $this->handleGive($sender, $target, $args);
                break;
            case "take":
                $this->handleTake($sender, $target, $args);
                break;
            case "set":
                $this->handleSet($sender, $target, $args);
                break;
            case "reset":
                $this->handleReset($sender, $target);
                break;
            default:
                $sender->sendMessage($this->lang()->get("general.unknown-subcommand", [
                    "usage" => "/eco <give|take|set|reset> <player> [amount]"
                ]));
                break;
        }
    }

    /** @param string[] $args */
    private function handleGive(CommandSender $sender, string $target, array $args): void {
        if (!$sender->hasPermission("bettereconomy.command.eco.give")) {
            $this->sendNoPermission($sender);
            return;
        }
        if (!isset($args[0]) || !is_numeric($args[0])) {
            $sender->sendMessage($this->lang()->get("eco.give.usage"));
            return;
        }

        $amount = $this->economy()->round((float) $args[0]);
        if ($amount <= 0.0) {
            $sender->sendMessage($this->lang()->get("general.invalid-amount"));
            return;
        }
        if ($amount > 1_000_000_000_000.0) {
            $sender->sendMessage($this->lang()->get("general.amount-too-large"));
            return;
        }

        $balance = $this->economy()->getBalance($target);
        if ($balance === null) {
            $sender->sendMessage($this->lang()->get("general.account-not-found", ["player" => $target]));
            return;
        }

        if (!$this->economy()->addBalance($target, $amount, BalanceChangeEvent::CAUSE_ADD)) {
            $sender->sendMessage($this->lang()->get("general.database-error"));
            return;
        }

        $newBalance = $this->economy()->getBalance($target) ?? ($balance + $amount);
        $sender->sendMessage($this->lang()->get("eco.give.success", [
            "amount" => $this->economy()->format($amount),
            "player" => $target,
            "balance" => $this->economy()->format($newBalance)
        ]));

        $targetPlayer = $this->plugin->getServer()->getPlayerExact($target);
        if ($targetPlayer !== null) {
            $targetPlayer->sendMessage($this->lang()->get("eco.give.success-target", [
                "amount" => $this->economy()->format($amount),
                "balance" => $this->economy()->format($newBalance)
            ]));
        }
    }

    /** @param string[] $args */
    private function handleTake(CommandSender $sender, string $target, array $args): void {
        if (!$sender->hasPermission("bettereconomy.command.eco.take")) {
            $this->sendNoPermission($sender);
            return;
        }
        if (!isset($args[0]) || !is_numeric($args[0])) {
            $sender->sendMessage($this->lang()->get("eco.take.usage"));
            return;
        }

        $amount = $this->economy()->round((float) $args[0]);
        if ($amount <= 0.0) {
            $sender->sendMessage($this->lang()->get("general.invalid-amount"));
            return;
        }
        if ($amount > 1_000_000_000_000.0) {
            $sender->sendMessage($this->lang()->get("general.amount-too-large"));
            return;
        }

        $balance = $this->economy()->getBalance($target);
        if ($balance === null) {
            $sender->sendMessage($this->lang()->get("general.account-not-found", ["player" => $target]));
            return;
        }
        if ($balance < $amount) {
            $sender->sendMessage($this->lang()->get("eco.take.insufficient", [
                "player" => $target,
                "balance" => $this->economy()->format($balance)
            ]));
            return;
        }

        if (!$this->economy()->subtractBalance($target, $amount, BalanceChangeEvent::CAUSE_SUBTRACT)) {
            $sender->sendMessage($this->lang()->get("general.database-error"));
            return;
        }

        $newBalance = $this->economy()->getBalance($target) ?? ($balance - $amount);
        $sender->sendMessage($this->lang()->get("eco.take.success", [
            "amount" => $this->economy()->format($amount),
            "player" => $target,
            "balance" => $this->economy()->format($newBalance)
        ]));

        $targetPlayer = $this->plugin->getServer()->getPlayerExact($target);
        if ($targetPlayer !== null) {
            $targetPlayer->sendMessage($this->lang()->get("eco.take.success-target", [
                "amount" => $this->economy()->format($amount),
                "balance" => $this->economy()->format($newBalance)
            ]));
        }
    }

    /** @param string[] $args */
    private function handleSet(CommandSender $sender, string $target, array $args): void {
        if (!$sender->hasPermission("bettereconomy.command.eco.set")) {
            $this->sendNoPermission($sender);
            return;
        }
        if (!isset($args[0]) || !is_numeric($args[0])) {
            $sender->sendMessage($this->lang()->get("eco.set.usage"));
            return;
        }

        $amount = $this->economy()->round((float) $args[0]);
        if ($amount < 0.0) {
            $sender->sendMessage($this->lang()->get("general.invalid-amount"));
            return;
        }
        if ($amount > 1_000_000_000_000.0) {
            $sender->sendMessage($this->lang()->get("general.amount-too-large"));
            return;
        }

        $balance = $this->economy()->getBalance($target);
        if ($balance === null) {
            $sender->sendMessage($this->lang()->get("general.account-not-found", ["player" => $target]));
            return;
        }

        if (!$this->economy()->setBalance($target, $amount, BalanceChangeEvent::CAUSE_SET)) {
            $sender->sendMessage($this->lang()->get("general.database-error"));
            return;
        }

        $newBalance = $this->economy()->getBalance($target) ?? $amount;
        $sender->sendMessage($this->lang()->get("eco.set.success", [
            "player" => $target,
            "balance" => $this->economy()->format($newBalance)
        ]));

        $targetPlayer = $this->plugin->getServer()->getPlayerExact($target);
        if ($targetPlayer !== null) {
            $targetPlayer->sendMessage($this->lang()->get("eco.set.success-target", [
                "balance" => $this->economy()->format($newBalance)
            ]));
        }
    }

    private function handleReset(CommandSender $sender, string $target): void {
        if (!$sender->hasPermission("bettereconomy.command.eco.reset")) {
            $this->sendNoPermission($sender);
            return;
        }

        $balance = $this->economy()->getBalance($target);
        if ($balance === null) {
            $sender->sendMessage($this->lang()->get("general.account-not-found", ["player" => $target]));
            return;
        }

        $default = $this->economy()->getDefaultBalance();
        if (!$this->economy()->setBalance($target, $default, BalanceChangeEvent::CAUSE_RESET)) {
            $sender->sendMessage($this->lang()->get("general.database-error"));
            return;
        }

        $newBalance = $this->economy()->getBalance($target) ?? $default;
        $sender->sendMessage($this->lang()->get("eco.reset.success", [
            "player" => $target,
            "balance" => $this->economy()->format($newBalance)
        ]));

        $targetPlayer = $this->plugin->getServer()->getPlayerExact($target);
        if ($targetPlayer !== null) {
            $targetPlayer->sendMessage($this->lang()->get("eco.reset.success-target", [
                "balance" => $this->economy()->format($newBalance)
            ]));
        }
    }
}
