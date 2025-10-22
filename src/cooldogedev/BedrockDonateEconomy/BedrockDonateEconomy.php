<?php

declare(strict_types=1);

namespace cooldogedev\BedrockDonateEconomy;

use cooldogedev\BedrockDonateEconomy\api\BedrockDonateEconomyAPI;
use pocketmine\plugin\PluginBase;
use SQLite3;
use function is_dir;
use function mkdir;

final class BedrockDonateEconomy extends PluginBase
{
    private static BedrockDonateEconomy $instance;

    public static function getInstance(): BedrockDonateEconomy
    {
        return self::$instance;
    }

    protected function onLoad(): void
    {
        self::$instance = $this;
    }

    protected function onEnable(): void
    {
        $this->saveDefaultConfig();
        $this->saveResource("config.yml");

        $dataFolder = $this->getDataFolder();
        if (!is_dir($dataFolder)) {
            mkdir($dataFolder);
        }

        $db = new SQLite3($dataFolder . "donate_balances.db");
        $db->exec("CREATE TABLE IF NOT EXISTS donate_balances (
            xuid TEXT PRIMARY KEY,
            username TEXT NOT NULL,
            amount INTEGER NOT NULL DEFAULT 0,
            decimals INTEGER NOT NULL DEFAULT 0
        );");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_amount ON donate_balances (amount DESC);");

        BedrockDonateEconomyAPI::init($db);

        $map = $this->getServer()->getCommandMap();
        $map->registerAll("BedrockDonateEconomy", [
            new commands\DBalanceCommand(),
            new commands\DPayCommand(),
            new commands\DRichCommand(),
            new commands\DAddBalanceCommand(),
            new commands\DRemoveBalanceCommand(),
            new commands\DSetBalanceCommand(),
        ]);
    }
}