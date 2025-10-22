<?php

declare(strict_types=1);

namespace cooldogedev\BedrockDonateEconomy\commands;

use cooldogedev\BedrockDonateEconomy\BedrockDonateEconomy;
use cooldogedev\BedrockDonateEconomy\api\BedrockDonateEconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use function count;

final class DRichCommand extends Command implements PluginOwned
{
    public function __construct()
    {
        parent::__construct("drich", "View top donate balances", "/drich [page]");
        $this->setPermission("bedrockdonateeconomy.command.drich");
    }

    public function getOwningPlugin(): BedrockDonateEconomy
    {
        return BedrockDonateEconomy::getInstance();
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$this->testPermission($sender)) {
            return;
        }

        $page = count($args) > 0 && is_numeric($args[0]) ? (int)$args[0] : 1;
        if ($page < 1) {
            $page = 1;
        }

        BedrockDonateEconomyAPI::CLOSURE()->bulk(
            list: [],
            onSuccess: static function (array $result) use ($sender, $page): void {
                $perPage = 10;
                $offset = ($page - 1) * $perPage;
                $slice = array_slice($result, $offset, $perPage);
                if (empty($slice)) {
                    $sender->sendMessage("§cNo players on page $page.");
                    return;
                }

                $msg = "§6Top Donate Balances (Page $page):\n";
                foreach ($slice as $i => $data) {
                    $balance = $data["amount"] + ($data["decimals"] / 100);
                    $msg .= "§7" . ($offset + $i + 1) . ". §f{$data["username"]} §a\$$balance\n";
                }
                $sender->sendMessage(rtrim($msg));
            },
            onError: static function () use ($sender): void {
                $sender->sendMessage("§cFailed to load leaderboard.");
            }
        );
    }
}