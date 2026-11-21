<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\provider;

use mysqli;
use mysqli_sql_exception;
use function strtolower;

final class MySQLProvider implements ProviderInterface {

    private ?mysqli $db = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = 10
    ) {}

    public function open(): void {
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @mysqli_init();
        if ($db === false) {
            throw new ProviderException("Failed to initialize MySQLi.");
        }

        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->timeout);
        if (!@$db->real_connect($this->host, $this->username, $this->password, $this->database, $this->port)) {
            throw new ProviderException("MySQL connection failed: " . $db->connect_error);
        }

        $db->set_charset("utf8mb4");
        $this->db = $db;

        $this->query(
            "CREATE TABLE IF NOT EXISTS bettereconomy_balances (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                display_name VARCHAR(64) NOT NULL,
                balance DOUBLE NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );
    }

    public function close(): void {
        if ($this->db !== null) {
            $this->db->close();
            $this->db = null;
        }
    }

    public function save(): void {
        // MySQL writes are immediate; nothing to flush.
    }

    public function hasAccount(string $name): bool {
        $stmt = $this->prepare("SELECT 1 FROM bettereconomy_balances WHERE name = ? LIMIT 1;");
        $lower = strtolower($name);
        $stmt->bind_param("s", $lower);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result !== false && $result->fetch_row() !== null;
        $stmt->close();
        return $exists;
    }

    public function createAccount(string $name, float $balance): void {
        $stmt = $this->prepare(
            "INSERT IGNORE INTO bettereconomy_balances (name, display_name, balance) VALUES (?, ?, ?);"
        );
        $lower = strtolower($name);
        $stmt->bind_param("ssd", $lower, $name, $balance);
        $stmt->execute();
        $stmt->close();
    }

    public function getBalance(string $name): ?float {
        $stmt = $this->prepare("SELECT balance FROM bettereconomy_balances WHERE name = ? LIMIT 1;");
        $lower = strtolower($name);
        $stmt->bind_param("s", $lower);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result !== false ? $result->fetch_assoc() : null;
        $stmt->close();
        if ($row === null || !isset($row["balance"])) {
            return null;
        }
        return (float) $row["balance"];
    }

    public function setBalance(string $name, float $balance): void {
        $lower = strtolower($name);
        if (!$this->hasAccount($lower)) {
            $this->createAccount($name, $balance);
            return;
        }
        $stmt = $this->prepare(
            "UPDATE bettereconomy_balances SET balance = ?, display_name = ? WHERE name = ?;"
        );
        $stmt->bind_param("dss", $balance, $name, $lower);
        $stmt->execute();
        $stmt->close();
    }

    public function getTop(int $limit, int $offset = 0): array {
        $stmt = $this->prepare(
            "SELECT display_name, balance FROM bettereconomy_balances ORDER BY balance DESC LIMIT ? OFFSET ?;"
        );
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        if ($result !== false) {
            while (($row = $result->fetch_assoc()) !== null) {
                $rows[] = [
                    "name" => (string) $row["display_name"],
                    "balance" => (float) $row["balance"]
                ];
            }
        }
        $stmt->close();
        return $rows;
    }

    public function countAccounts(): int {
        $result = $this->db()->query("SELECT COUNT(*) AS cnt FROM bettereconomy_balances;");
        if ($result === false) {
            throw new ProviderException("MySQL query failed: " . $this->db()->error);
        }
        $row = $result->fetch_assoc();
        $result->free();
        return (int) ($row["cnt"] ?? 0);
    }

    public function getAllBalances(): array {
        $result = $this->db()->query("SELECT name, balance FROM bettereconomy_balances;");
        if ($result === false) {
            throw new ProviderException("MySQL query failed: " . $this->db()->error);
        }
        $balances = [];
        while (($row = $result->fetch_assoc()) !== null) {
            $balances[(string) $row["name"]] = (float) $row["balance"];
        }
        $result->free();
        return $balances;
    }

    public function getName(): string {
        return "MySQL";
    }

    private function db(): mysqli {
        if ($this->db === null) {
            throw new ProviderException("MySQL provider is not initialized.");
        }
        return $this->db;
    }

    private function prepare(string $query): \mysqli_stmt {
        $stmt = $this->db()->prepare($query);
        if ($stmt === false) {
            throw new ProviderException("Failed to prepare MySQL statement: " . $this->db()->error);
        }
        return $stmt;
    }

    private function query(string $query): void {
        if ($this->db()->query($query) === false) {
            throw new ProviderException("MySQL query failed: " . $this->db()->error);
        }
    }
}
