<?php

declare(strict_types=1);

namespace cooldogedev\BedrockDonateEconomy\commands;

use cooldogedev\BedrockDonateEconomy\BedrockDonateEconomy;
use cooldogedev\BedrockDonateEconomy\api\BedrockDonateEconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use function count;

final class DAddBalanceCommand extends Command implements PluginOwned
{
    public function __construct()
    {
        parent::__construct("daddbalance", "Add donate balance to a player", "/daddbalance <player> <amount>");
        $this->setPermission("bedrockdonateeconomy.command.daddbalance");
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

        if (count($args) < 2) {
            $sender->sendMessage("Usage: /daddbalance <player> <amount>");
            return;
        }

        $target = $this->getOwningPlugin()->getServer()->getPlayerByPrefix($args[0]);
        if ($target === null) {
            $sender->sendMessage("Player not found.");
            return;
        }

        if (!is_numeric($args[1]) || $args[1] <= 0) {
            $sender->sendMessage("Invalid amount.");
            return;
        }

        $amount = (float)$args[1];
        $whole = (int)$amount;
        $decimals = (int)(($amount - $whole) * 100);

        BedrockDonateEconomyAPI::CLOSURE()->add(
            xuid: $target->getXuid(),
            username: $target->getName(),
            amount: $whole,
            decimals: $decimals,
            onSuccess: static function () use ($sender, $target, $amount): void {
                $sender->sendMessage("§aAdded §6\$$amount §ato §f{$target->getName()}§a.");
                $target->sendMessage("§aYour donate balance was increased by §6\$$amount§a.");
            },
            onError: static function () use ($sender): void {
                $sender->sendMessage("§cFailed to add balance.");
            }
        );
    }
}