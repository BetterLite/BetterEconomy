<?php

declare(strict_types=1);

namespace Mellooh\BetterEconomy\language;

use Mellooh\BetterEconomy\BetterEconomy;
use pocketmine\utils\TextFormat;
use function array_shift;
use function count;
use function explode;
use function file_exists;
use function is_array;
use function is_string;
use function str_replace;
use function trim;

final class LanguageManager {

    /** @var array<string, mixed> */
    private array $messages = [];
    private string $language;

    public function __construct(
        private readonly BetterEconomy $plugin
    ) {
        $this->language = (string) $this->plugin->getConfig()->get("language", "en_US");
        $this->load();
    }

    public function load(): void {
        $file = $this->plugin->getDataFolder() . "languages/" . $this->language . ".yml";
        if (!file_exists($file)) {
            $this->plugin->getLogger()->warning("Language file '{$this->language}.yml' not found, falling back to en_US.");
            $file = $this->plugin->getDataFolder() . "languages/en_US.yml";
            $this->language = "en_US";
        }

        $this->messages = [];
        $loaded = yaml_parse_file($file);
        if (is_array($loaded)) {
            $this->messages = $loaded;
        } else {
            $this->plugin->getLogger()->error("Failed to parse language file: " . $file);
        }
    }

    public function getLanguage(): string {
        return $this->language;
    }

    /**
     * @param array<string, string|int|float> $params
     */
    public function get(string $key, array $params = [], bool $withPrefix = true): string {
        $message = $this->resolve($key);
        if (!is_string($message)) {
            $message = $key;
        }

        foreach ($params as $name => $value) {
            $message = str_replace("{" . $name . "}", (string) $value, $message);
        }

        $message = TextFormat::colorize($message);

        if ($withPrefix) {
            $prefix = $this->resolve("prefix");
            $prefixStr = is_string($prefix) ? TextFormat::colorize($prefix) : "";
            return $prefixStr . $message;
        }

        return $message;
    }

    private function resolve(string $key): mixed {
        $parts = explode(".", $key);
        $current = $this->messages;

        while (count($parts) > 0) {
            $part = array_shift($parts);
            if (!is_array($current) || !isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}
