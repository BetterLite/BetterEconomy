<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\manager;

use Mellooh\BetterEconomy\BetterEconomy;
use Mellooh\BetterEconomy\event\BalanceChangeEvent;
use Mellooh\BetterEconomy\provider\ProviderException;
use Mellooh\BetterEconomy\provider\ProviderInterface;
use Mellooh\BetterEconomy\util\CurrencyFormatter;
use function max;
use function round;
use function strtolower;

final class EconomyManager {

    private CurrencyFormatter $formatter;
    private float $defaultBalance;
    private int $decimals;

    public function __construct(
        private readonly BetterEconomy $plugin,
        private readonly ProviderInterface $provider
    ) {
        $cfg = $this->plugin->getConfig();
        $this->decimals = max(0, (int) $cfg->getNested("currency.decimals", 2));
        $this->defaultBalance = $this->round((float) $cfg->getNested("default-balance", 1000.00));
        $this->formatter = new CurrencyFormatter(
            (string) $cfg->getNested("currency.symbol", "$"),
            (string) $cfg->getNested("currency.format", "{symbol}{amount}"),
            $this->decimals,
            $this->decimals > 0
        );
    }

    public function getProvider(): ProviderInterface {
        return $this->provider;
    }

    public function getFormatter(): CurrencyFormatter {
        return $this->formatter;
    }

    public function getDefaultBalance(): float {
        return $this->defaultBalance;
    }

    public function round(float $amount): float {
        return round($amount, $this->decimals);
    }

    public function format(float $amount): string {
        return $this->formatter->format($amount);
    }

    public function hasAccount(string $name): bool {
        return $this->provider->hasAccount($this->normalize($name));
    }

    public function createAccount(string $name, ?float $balance = null): bool {
        $normalized = $this->normalize($name);
        if ($this->provider->hasAccount($normalized)) {
            return false;
        }

        $amount = $this->round($balance ?? $this->defaultBalance);
        $event = new BalanceChangeEvent($name, 0.0, $amount, BalanceChangeEvent::CAUSE_CREATE);
        $event->call();
        if ($event->isCancelled()) {
            return false;
        }

        $this->provider->createAccount($name, $this->round($event->getNewBalance()));
        return true;
    }

    public function getBalance(string $name): ?float {
        $balance = $this->provider->getBalance($this->normalize($name));
        return $balance === null ? null : $this->round($balance);
    }

    public function setBalance(string $name, float $amount, string $cause = BalanceChangeEvent::CAUSE_SET): bool {
        $normalized = $this->normalize($name);
        $old = $this->provider->getBalance($normalized);
        if ($old === null) {
            return false;
        }

        $new = $this->round(max(0.0, $amount));
        $event = new BalanceChangeEvent($name, $this->round($old), $new, $cause);
        $event->call();
        if ($event->isCancelled()) {
            return false;
        }

        $this->provider->setBalance($name, $this->round(max(0.0, $event->getNewBalance())));
        return true;
    }

    public function addBalance(string $name, float $amount, string $cause = BalanceChangeEvent::CAUSE_ADD): bool {
        $amount = $this->round($amount);
        if ($amount <= 0.0) {
            return false;
        }
        $old = $this->getBalance($name);
        if ($old === null) {
            return false;
        }
        return $this->setBalance($name, $old + $amount, $cause);
    }

    public function subtractBalance(string $name, float $amount, string $cause = BalanceChangeEvent::CAUSE_SUBTRACT): bool {
        $amount = $this->round($amount);
        if ($amount <= 0.0) {
            return false;
        }
        $old = $this->getBalance($name);
        if ($old === null || $old < $amount) {
            return false;
        }
        return $this->setBalance($name, $old - $amount, $cause);
    }

    public function transfer(string $from, string $to, float $amount): bool {
        $amount = $this->round($amount);
        if ($amount <= 0.0) {
            return false;
        }

        $fromNorm = $this->normalize($from);
        $toNorm = $this->normalize($to);
        if ($fromNorm === $toNorm) {
            return false;
        }

        $fromBalance = $this->provider->getBalance($fromNorm);
        $toBalance = $this->provider->getBalance($toNorm);
        if ($fromBalance === null || $toBalance === null || $fromBalance < $amount) {
            return false;
        }

        $fromEvent = new BalanceChangeEvent($from, $this->round($fromBalance), $this->round($fromBalance - $amount), BalanceChangeEvent::CAUSE_TRANSFER);
        $fromEvent->call();
        if ($fromEvent->isCancelled()) {
            return false;
        }

        $toEvent = new BalanceChangeEvent($to, $this->round($toBalance), $this->round($toBalance + $amount), BalanceChangeEvent::CAUSE_TRANSFER);
        $toEvent->call();
        if ($toEvent->isCancelled()) {
            return false;
        }

        $this->provider->setBalance($from, $this->round(max(0.0, $fromEvent->getNewBalance())));
        $this->provider->setBalance($to, $this->round(max(0.0, $toEvent->getNewBalance())));
        return true;
    }

    /**
     * @return array<int, array{name: string, balance: float}>
     */
    public function getTop(int $limit, int $offset = 0): array {
        return $this->provider->getTop($limit, $offset);
    }

    public function countAccounts(): int {
        return $this->provider->countAccounts();
    }

    public function save(): void {
        $this->provider->save();
    }

    public function close(): void {
        $this->provider->close();
    }

    private function normalize(string $name): string {
        return strtolower($name);
    }
}
