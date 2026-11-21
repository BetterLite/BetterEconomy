<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\api;

use Mellooh\BetterEconomy\BetterEconomy;
use Mellooh\BetterEconomy\event\BalanceChangeEvent;
use Mellooh\BetterEconomy\manager\EconomyManager;
use pocketmine\player\Player;
use pocketmine\Server;

final class BetterEconomyAPI {

    private function __construct() {}

    /**
     * Returns whether the BetterEconomy API is available.
     */
    public static function isAvailable(): bool {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("BetterEconomy");
        return $plugin instanceof BetterEconomy && $plugin->isEnabled();
    }

    /**
     * Returns the BetterEconomy plugin instance.
     *
     * @throws \RuntimeException If the plugin is not loaded or enabled.
     */
    public static function getPlugin(): BetterEconomy {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("BetterEconomy");
        if (!$plugin instanceof BetterEconomy || !$plugin->isEnabled()) {
            throw new \RuntimeException("BetterEconomy is not loaded or enabled.");
        }
        return $plugin;
    }

    /**
     * Returns the economy manager for advanced usage.
     */
    public static function getManager(): EconomyManager {
        return self::getPlugin()->getEconomyManager();
    }

    /**
     * Checks whether an account exists.
     */
    public static function hasAccount(string|Player $player): bool {
        return self::getManager()->hasAccount(self::resolveName($player));
    }

    /**
     * Creates an account with the default balance, or a custom balance if provided.
     *
     * @return bool True if the account was created, false if it already exists or was cancelled.
     */
    public static function createAccount(string|Player $player, ?float $balance = null): bool {
        return self::getManager()->createAccount(self::resolveName($player), $balance);
    }

    /**
     * Gets a player's balance.
     *
     * @return float|null Balance if account exists, null otherwise.
     */
    public static function getBalance(string|Player $player): ?float {
        return self::getManager()->getBalance(self::resolveName($player));
    }

    /**
     * Sets a player's balance.
     *
     * @return bool True if the balance was updated, false if account not found or cancelled.
     */
    public static function setBalance(string|Player $player, float $amount, string $cause = BalanceChangeEvent::CAUSE_SET): bool {
        return self::getManager()->setBalance(self::resolveName($player), $amount, $cause);
    }

    /**
     * Adds money to a player's balance.
     *
     * @return bool True if the balance was updated, false otherwise.
     */
    public static function addBalance(string|Player $player, float $amount, string $cause = BalanceChangeEvent::CAUSE_ADD): bool {
        return self::getManager()->addBalance(self::resolveName($player), $amount, $cause);
    }

    /**
     * Subtracts money from a player's balance.
     *
     * @return bool True if the balance was updated, false if insufficient funds or account not found.
     */
    public static function subtractBalance(string|Player $player, float $amount, string $cause = BalanceChangeEvent::CAUSE_SUBTRACT): bool {
        return self::getManager()->subtractBalance(self::resolveName($player), $amount, $cause);
    }

    /**
     * Transfers money from one player to another.
     *
     * @return bool True if the transfer succeeded, false otherwise.
     */
    public static function transfer(string|Player $from, string|Player $to, float $amount): bool {
        return self::getManager()->transfer(self::resolveName($from), self::resolveName($to), $amount);
    }

    /**
     * Formats an amount using the configured currency format.
     */
    public static function format(float $amount): string {
        return self::getManager()->format($amount);
    }

    /**
     * Returns the default balance for new accounts.
     */
    public static function getDefaultBalance(): float {
        return self::getManager()->getDefaultBalance();
    }

    /**
     * Returns the top balances.
     *
     * @return array<int, array{name: string, balance: float}>
     */
    public static function getTop(int $limit = 10, int $offset = 0): array {
        return self::getManager()->getTop($limit, $offset);
    }

    /**
     * Returns the total number of accounts.
     */
    public static function countAccounts(): int {
        return self::getManager()->countAccounts();
    }

    private static function resolveName(string|Player $player): string {
        return $player instanceof Player ? $player->getName() : $player;
    }
}
