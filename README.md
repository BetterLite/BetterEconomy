<img width="2021" height="212" alt="minecraft_title" src="https://github.com/user-attachments/assets/83737769-2daa-4747-a265-375f66434a89" />


A clean, multi-provider economy plugin for **PocketMine-MP**.

## Features

- Multi-provider storage: **SQLite**, **MySQL**, **JSON**, **YAML**
- Multi-language support: `en_US`, `it_IT`, `de_DE`, `es_ES`, `fr_FR`
- Configurable currency format, symbol, decimals, and default balance
- Balance leaderboard with pagination
- Balance change events for developer integration

## Commands

| Command | Description | Permission | Default |
|---|---|---|---|
| `/balance [player]` | View your balance or another player's balance | `bettereconomy.command.balance` | true |
| `/pay <player> <amount>` | Pay another player | `bettereconomy.command.pay` | true |
| `/baltop [page]` | View top balances | `bettereconomy.command.baltop` | true |
| `/eco give <player> <amount>` | Give money to a player | `bettereconomy.command.eco.give` | op |
| `/eco take <player> <amount>` | Take money from a player | `bettereconomy.command.eco.take` | op |
| `/eco set <player> <amount>` | Set a player's balance | `bettereconomy.command.eco.set` | op |
| `/eco reset <player>` | Reset a player's balance to default | `bettereconomy.command.eco.reset` | op |

## Configuration

Edit `plugins/BetterEconomy/config.yml`:

- `language`: language file name (e.g. `en_US`, `it_IT`)
- `provider`: `sqlite`, `mysql`, `json`, or `yaml`
- `default-balance`: starting balance for new players
- `currency`: symbol, format, decimals
- `baltop.per-page`: leaderboard page size
- `auto-save`: auto-save interval in seconds (`0` to disable)

## Developer API

Use `Mellooh\BetterEconomy\api\BetterEconomyAPI`:

```php
use Mellooh\BetterEconomy\api\BetterEconomyAPI;
use Mellooh\BetterEconomy\event\BalanceChangeEvent;

if (!BetterEconomyAPI::isAvailable()) {
    return;
}

// Accounts
BetterEconomyAPI::hasAccount($player); // string|Player
BetterEconomyAPI::createAccount($player, 500.0);

// Balance
$balance = BetterEconomyAPI::getBalance($player); // ?float
BetterEconomyAPI::setBalance($player, 1000.0, BalanceChangeEvent::CAUSE_SET);
BetterEconomyAPI::addBalance($player, 250.0, BalanceChangeEvent::CAUSE_ADD);
BetterEconomyAPI::subtractBalance($player, 100.0, BalanceChangeEvent::CAUSE_SUBTRACT);

// Transfer
BetterEconomyAPI::transfer($fromPlayer, $toPlayer, 50.0);

// Formatting / top
echo BetterEconomyAPI::format(1234.5);
$top = BetterEconomyAPI::getTop(limit: 10, offset: 0);
$total = BetterEconomyAPI::countAccounts();
```

### Events

- `Mellooh\BetterEconomy\event\BalanceChangeEvent` (cancellable)
  - Causes: `CAUSE_SET`, `CAUSE_ADD`, `CAUSE_SUBTRACT`, `CAUSE_TRANSFER`, `CAUSE_RESET`, `CAUSE_CREATE`
  - Methods: `getPlayerName()`, `getOldBalance()`, `getNewBalance()`, `setNewBalance()`, `getCause()`

## ⭐ Contribute

- Found a bug? Open an issue
- Pull requests welcome
- Star the repo if you like the project!
