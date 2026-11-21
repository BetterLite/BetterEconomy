<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\command;

use Mellooh\BetterEconomy\BetterEconomy;
use pocketmine\command\CommandSender;
use function ceil;
use function is_numeric;
use function max;

final class BaltopCommand extends BaseCommand {

    public function __construct(BetterEconomy $plugin) {
        parent::__construct(
            $plugin,
            "baltop",
            "View the richest players",
            "/baltop [page]",
            "bettereconomy.command.baltop",
            ["topbalance", "rich"]
        );
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$this->testPermissionSilent($sender)) {
            $this->sendNoPermission($sender);
            return;
        }

        $perPage = max(1, (int) $this->plugin->getConfig()->getNested("baltop.per-page", 10));
        $totalAccounts = $this->economy()->countAccounts();
        if ($totalAccounts <= 0) {
            $sender->sendMessage($this->lang()->get("baltop.empty"));
            return;
        }

        $totalPages = (int) max(1, (int) ceil($totalAccounts / $perPage));
        $page = 1;
        if (isset($args[0]) && is_numeric($args[0])) {
            $page = (int) $args[0];
        }

        if ($page < 1 || $page > $totalPages) {
            $sender->sendMessage($this->lang()->get("baltop.invalid-page", [
                "total_pages" => (string) $totalPages
            ]));
            return;
        }

        $offset = ($page - 1) * $perPage;
        $rows = $this->economy()->getTop($perPage, $offset);

        $sender->sendMessage($this->lang()->get("baltop.header", [
            "page" => (string) $page,
            "total_pages" => (string) $totalPages
        ], false));

        $rank = $offset + 1;
        foreach ($rows as $row) {
            $sender->sendMessage($this->lang()->get("baltop.entry", [
                "rank" => (string) $rank,
                "player" => $row["name"],
                "balance" => $this->economy()->format((float) $row["balance"])
            ], false));
            $rank++;
        }

        $sender->sendMessage($this->lang()->get("baltop.footer", [], false));
    }
}
