<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\util;

use function number_format;
use function round;
use function str_replace;

final class CurrencyFormatter {

    public function __construct(
        private readonly string $symbol,
        private readonly string $format,
        private readonly int $decimals,
        private readonly bool $useDecimals
    ) {}

    public function format(float $amount): string {
        $amount = round($amount, $this->decimals);
        $formattedNumber = $this->useDecimals
            ? number_format($amount, $this->decimals, ".", ",")
            : number_format((float) (int) round($amount), 0, ".", ",");

        return str_replace(
            ["{symbol}", "{amount}"],
            [$this->symbol, $formattedNumber],
            $this->format
        );
    }

    public function getSymbol(): string {
        return $this->symbol;
    }

    public function getDecimals(): int {
        return $this->decimals;
    }
}
