<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\provider;

interface ProviderInterface {

    /**
     * Initialize the provider (open connections, create tables/files, etc.).
     *
     * @throws ProviderException
     */
    public function open(): void;

    /**
     * Close the provider and release resources.
     */
    public function close(): void;

    /**
     * Persist pending changes, if any.
     */
    public function save(): void;

    public function hasAccount(string $name): bool;

    /**
     * @throws ProviderException
     */
    public function createAccount(string $name, float $balance): void;

    /**
     * @return float|null Balance if account exists, null otherwise.
     */
    public function getBalance(string $name): ?float;

    /**
     * @throws ProviderException
     */
    public function setBalance(string $name, float $balance): void;

    /**
     * @return array<int, array{name: string, balance: float}>
     */
    public function getTop(int $limit, int $offset = 0): array;

    public function countAccounts(): int;

    /**
     * @return array<string, float>
     */
    public function getAllBalances(): array;

    public function getName(): string;
}
