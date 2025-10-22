<?php

declare(strict_types=1);

namespace cooldogedev\BedrockDonateEconomy\commands;

use cooldogedev\BedrockDonateEconomy\BedrockDonateEconomy;
use cooldogedev\BedrockDonateEconomy\api\BedrockDonateEconomyAPI;
use cooldogedev\BedrockDonateEconomy\api\exception\RecordNotFoundException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use function count;

final class DBalanceCommand extends Command implements PluginOwned
{
    public function __construct()
    {
        parent::__construct("dbalance", "View your donate balance", "/dbalance [player]");
        $this->setPermission("bedrockdonateeconomy.command.dbalance");
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

        if (!$sender instanceof Player) {
            $sender->sendMessage("Use in-game only.");
            return;
        }

        $target = count($args) > 0 ? $this->getOwningPlugin()->getServer()->getPlayerByPrefix($args[0]) : $sender;
        if ($target === null) {
            $sender->sendMessage("Player not found.");
            return;
        }

        BedrockDonateEconomyAPI::CLOSURE()->get(
            xuid: $target->getXuid(),
            username: $target->getName(),
            onSuccess: static function (array $result) use ($sender, $target): void {
                $balance = $result["amount"] + ($result["decimals"] / 100);
                $sender->sendMessage("Donate balance of " . $target->getName() . ": $" . number_format($balance, 2) . " (#" . $result["position"] . ")");
            },
            onError: static function (\Throwable $e) use ($sender): void {
                if ($e instanceof RecordNotFoundException) {
                    $sender->sendMessage("Account not found.");
                } else {
                    $sender->sendMessage("Failed to load account.");
                }
            }
        );
    }
}