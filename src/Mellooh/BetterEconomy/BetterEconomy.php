<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy;

use Mellooh\BetterEconomy\command\BalanceCommand;
use Mellooh\BetterEconomy\command\BaltopCommand;
use Mellooh\BetterEconomy\command\EcoCommand;
use Mellooh\BetterEconomy\command\PayCommand;
use Mellooh\BetterEconomy\language\LanguageManager;
use Mellooh\BetterEconomy\listener\EventListener;
use Mellooh\BetterEconomy\manager\EconomyManager;
use Mellooh\BetterEconomy\provider\JsonProvider;
use Mellooh\BetterEconomy\provider\MySQLProvider;
use Mellooh\BetterEconomy\provider\ProviderException;
use Mellooh\BetterEconomy\provider\ProviderInterface;
use Mellooh\BetterEconomy\provider\SQLiteProvider;
use Mellooh\BetterEconomy\provider\YamlProvider;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use function is_dir;
use function mkdir;
use function strtolower;

final class BetterEconomy extends PluginBase {
    use SingletonTrait;

    private ?LanguageManager $languageManager = null;
    private ?EconomyManager $economyManager = null;
    private ?ProviderInterface $provider = null;

    protected function onLoad(): void {
        self::setInstance($this);
    }

    protected function onEnable(): void {
        $this->saveDefaultConfig();
        $this->saveLanguageResources();

        $this->languageManager = new LanguageManager($this);

        try {
            $this->provider = $this->createProvider();
            $this->provider->open();
        } catch (ProviderException $e) {
            $this->getLogger()->error("Failed to initialize provider: " . $e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        } catch (\Throwable $e) {
            $this->getLogger()->error("Unexpected error while initializing provider: " . $e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->economyManager = new EconomyManager($this, $this->provider);

        $this->registerCommands();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->scheduleAutoSave();

        $this->getLogger()->info("BetterEconomy enabled using provider: " . $this->provider->getName());
    }

    protected function onDisable(): void {
        if ($this->economyManager !== null) {
            try {
                $this->economyManager->save();
                $this->economyManager->close();
            } catch (\Throwable $e) {
                $this->getLogger()->error("Error while shutting down provider: " . $e->getMessage());
            }
        }
    }

    public function getLanguageManager(): LanguageManager {
        if ($this->languageManager === null) {
            throw new \LogicException("LanguageManager is not initialized.");
        }
        return $this->languageManager;
    }

    public function getEconomyManager(): EconomyManager {
        if ($this->economyManager === null) {
            throw new \LogicException("EconomyManager is not initialized.");
        }
        return $this->economyManager;
    }

    private function saveLanguageResources(): void {
        $langDir = $this->getDataFolder() . "languages/";
        if (!is_dir($langDir)) {
            @mkdir($langDir, 0777, true);
        }

        foreach (["en_US.yml", "it_IT.yml", "de_DE.yml", "es_ES.yml", "fr_FR.yml"] as $langFile) {
            $this->saveResource("languages/" . $langFile);
        }
    }

    private function createProvider(): ProviderInterface {
        $type = strtolower((string) $this->getConfig()->get("provider", "sqlite"));

        return match ($type) {
            "sqlite" => new SQLiteProvider($this->getDataFolder() . (string) $this->getConfig()->getNested("sqlite.file", "economy.sqlite")),
            "mysql" => new MySQLProvider(
                (string) $this->getConfig()->getNested("mysql.host", "127.0.0.1"),
                (int) $this->getConfig()->getNested("mysql.port", 3306),
                (string) $this->getConfig()->getNested("mysql.database", "bettereconomy"),
                (string) $this->getConfig()->getNested("mysql.username", "root"),
                (string) $this->getConfig()->getNested("mysql.password", ""),
                (int) $this->getConfig()->getNested("mysql.timeout", 10)
            ),
            "json" => new JsonProvider(
                $this->getDataFolder() . (string) $this->getConfig()->getNested("json.file", "economy.json"),
                (bool) $this->getConfig()->getNested("json.pretty", true)
            ),
            "yaml", "yml" => new YamlProvider(
                $this->getDataFolder() . (string) $this->getConfig()->getNested("yaml.file", "economy.yml")
            ),
            default => throw new ProviderException("Unknown provider type: " . $type),
        };
    }

    private function registerCommands(): void {
        $this->getServer()->getCommandMap()->registerAll("bettereconomy", [
            new BalanceCommand($this),
            new PayCommand($this),
            new BaltopCommand($this),
            new EcoCommand($this),
        ]);
    }

    private function scheduleAutoSave(): void {
        $interval = (int) $this->getConfig()->get("auto-save", 300);
        if ($interval <= 0) {
            return;
        }

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            if ($this->economyManager !== null) {
                try {
                    $this->economyManager->save();
                } catch (\Throwable $e) {
                    $this->getLogger()->warning("Auto-save failed: " . $e->getMessage());
                }
            }
        }), $interval * 20);
    }
}
