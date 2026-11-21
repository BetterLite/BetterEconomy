<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\provider;

use SQLite3;
use SQLite3Stmt;
use function dirname;
use function file_exists;
use function is_dir;
use function mkdir;
use function strtolower;

final class SQLiteProvider implements ProviderInterface {

    private ?SQLite3 $db = null;

    public function __construct(
        private readonly string $filePath
    ) {}

    public function open(): void {
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new ProviderException("Failed to create directory: " . $dir);
        }

        $this->db = new SQLite3($this->filePath);
        $this->db->busyTimeout(5000);
        $this->db->exec("PRAGMA journal_mode = WAL;");
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS balances (
                name TEXT PRIMARY KEY,
                display_name TEXT NOT NULL,
                balance REAL NOT NULL DEFAULT 0
            );"
        );
    }

    public function close(): void {
        if ($this->db !== null) {
            $this->db->close();
            $this->db = null;
        }
    }

    public function save(): void {
        // SQLite writes are immediate; nothing to flush.
    }

    public function hasAccount(string $name): bool {
        $stmt = $this->prepare("SELECT 1 FROM balances WHERE name = :name LIMIT 1;");
        $stmt->bindValue(":name", strtolower($name), SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result !== false ? $result->fetchArray(SQLITE3_NUM) : false;
        $stmt->close();
        return $row !== false;
    }

    public function createAccount(string $name, float $balance): void {
        $stmt = $this->prepare(
            "INSERT OR IGNORE INTO balances (name, display_name, balance) VALUES (:name, :display_name, :balance);"
        );
        $stmt->bindValue(":name", strtolower($name), SQLITE3_TEXT);
        $stmt->bindValue(":display_name", $name, SQLITE3_TEXT);
        $stmt->bindValue(":balance", $balance, SQLITE3_FLOAT);
        $stmt->execute();
        $stmt->close();
    }

    public function getBalance(string $name): ?float {
        $stmt = $this->prepare("SELECT balance FROM balances WHERE name = :name LIMIT 1;");
        $stmt->bindValue(":name", strtolower($name), SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result !== false ? $result->fetchArray(SQLITE3_ASSOC) : false;
        $stmt->close();
        if ($row === false || !isset($row["balance"])) {
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
            "UPDATE balances SET balance = :balance, display_name = :display_name WHERE name = :name;"
        );
        $stmt->bindValue(":balance", $balance, SQLITE3_FLOAT);
        $stmt->bindValue(":display_name", $name, SQLITE3_TEXT);
        $stmt->bindValue(":name", $lower, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();
    }

    public function getTop(int $limit, int $offset = 0): array {
        $stmt = $this->prepare(
            "SELECT display_name, balance FROM balances ORDER BY balance DESC LIMIT :limit OFFSET :offset;"
        );
        $stmt->bindValue(":limit", $limit, SQLITE3_INTEGER);
        $stmt->bindValue(":offset", $offset, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $rows = [];
        if ($result !== false) {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
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
        $result = $this->db()->querySingle("SELECT COUNT(*) FROM balances;");
        return (int) ($result ?? 0);
    }

    public function getAllBalances(): array {
        $result = $this->db()->query("SELECT name, balance FROM balances;");
        $balances = [];
        if ($result !== false) {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $balances[(string) $row["name"]] = (float) $row["balance"];
            }
        }
        return $balances;
    }

    public function getName(): string {
        return "SQLite";
    }

    private function db(): SQLite3 {
        if ($this->db === null) {
            throw new ProviderException("SQLite provider is not initialized.");
        }
        return $this->db;
    }

    private function prepare(string $query): SQLite3Stmt {
        $stmt = $this->db()->prepare($query);
        if ($stmt === false) {
            throw new ProviderException("Failed to prepare SQLite statement: " . $this->db()->lastErrorMsg());
        }
        return $stmt;
    }
}
