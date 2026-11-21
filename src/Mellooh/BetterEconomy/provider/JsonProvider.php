<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\provider;

use function array_slice;
use function count;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function json_decode;
use function json_encode;
use function rename;
use function strtolower;
use function usort;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class JsonProvider implements ProviderInterface {

    /** @var array<string, array{name: string, balance: float}> */
    private array $accounts = [];
    private bool $dirty = false;

    public function __construct(
        private readonly string $filePath,
        private readonly bool $pretty = true
    ) {}

    public function open(): void {
        if (!file_exists($this->filePath)) {
            $this->accounts = [];
            $this->save();
            return;
        }

        $contents = file_get_contents($this->filePath);
        if ($contents === false || $contents === "") {
            $this->accounts = [];
            return;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProviderException("Failed to parse JSON economy file: " . $e->getMessage(), 0, $e);
        }

        $this->accounts = [];
        if (is_array($decoded)) {
            foreach ($decoded as $key => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $name = (string) ($entry["name"] ?? $key);
                $balance = (float) ($entry["balance"] ?? 0.0);
                $lower = strtolower((string) $key);
                $this->accounts[$lower] = ["name" => $name, "balance" => $balance];
            }
        }
    }

    public function close(): void {
        $this->save();
        $this->accounts = [];
    }

    public function save(): void {
        if (!file_exists($this->filePath) && !$this->dirty && count($this->accounts) === 0) {
            // still write initial file
        }
        $flags = JSON_THROW_ON_ERROR;
        if ($this->pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $payload = [];
        foreach ($this->accounts as $lower => $data) {
            $payload[$lower] = $data;
        }

        try {
            $json = json_encode($payload, $flags);
        } catch (\JsonException $e) {
            throw new ProviderException("Failed to encode JSON economy data: " . $e->getMessage(), 0, $e);
        }

        $tmp = $this->filePath . ".tmp";
        if (file_put_contents($tmp, $json) === false) {
            throw new ProviderException("Failed to write JSON economy file: " . $this->filePath);
        }
        if (!@rename($tmp, $this->filePath)) {
            // fallback for Windows rename-over-existing issues
            if (file_put_contents($this->filePath, $json) === false) {
                throw new ProviderException("Failed to save JSON economy file: " . $this->filePath);
            }
            @unlink($tmp);
        }
        $this->dirty = false;
    }

    public function hasAccount(string $name): bool {
        return isset($this->accounts[strtolower($name)]);
    }

    public function createAccount(string $name, float $balance): void {
        $lower = strtolower($name);
        if (isset($this->accounts[$lower])) {
            return;
        }
        $this->accounts[$lower] = ["name" => $name, "balance" => $balance];
        $this->dirty = true;
    }

    public function getBalance(string $name): ?float {
        $lower = strtolower($name);
        return isset($this->accounts[$lower]) ? (float) $this->accounts[$lower]["balance"] : null;
    }

    public function setBalance(string $name, float $balance): void {
        $lower = strtolower($name);
        if (!isset($this->accounts[$lower])) {
            $this->createAccount($name, $balance);
            return;
        }
        $this->accounts[$lower]["balance"] = $balance;
        $this->accounts[$lower]["name"] = $name;
        $this->dirty = true;
    }

    public function getTop(int $limit, int $offset = 0): array {
        $all = $this->accounts;
        usort($all, static fn(array $a, array $b): int => $b["balance"] <=> $a["balance"]);
        /** @var array<int, array{name: string, balance: float}> $slice */
        $slice = array_slice($all, $offset, $limit);
        return $slice;
    }

    public function countAccounts(): int {
        return count($this->accounts);
    }

    public function getAllBalances(): array {
        $result = [];
        foreach ($this->accounts as $lower => $data) {
            $result[$lower] = (float) $data["balance"];
        }
        return $result;
    }

    public function getName(): string {
        return "JSON";
    }
}
