<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;

final class BalanceChangeEvent extends Event implements Cancellable {
    use CancellableTrait;

    public const CAUSE_SET = "set";
    public const CAUSE_ADD = "add";
    public const CAUSE_SUBTRACT = "subtract";
    public const CAUSE_TRANSFER = "transfer";
    public const CAUSE_RESET = "reset";
    public const CAUSE_CREATE = "create";

    public function __construct(
        private readonly string $playerName,
        private readonly float $oldBalance,
        private float $newBalance,
        private readonly string $cause
    ) {}

    public function getPlayerName(): string {
        return $this->playerName;
    }

    public function getOldBalance(): float {
        return $this->oldBalance;
    }

    public function getNewBalance(): float {
        return $this->newBalance;
    }

    public function setNewBalance(float $newBalance): void {
        $this->newBalance = $newBalance;
    }

    public function getCause(): string {
        return $this->cause;
    }
}
