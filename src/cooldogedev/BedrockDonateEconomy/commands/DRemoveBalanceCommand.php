<?php

declare(strict_types=1);

namespace cooldogedev\BedrockDonateEconomy\commands;

use cooldogedev\BedrockDonateEconomy\BedrockDonateEconomy;
use cooldogedev\BedrockDonateEconomy\api\BedrockDonateEconomyAPI;
use cooldogedev\BedrockDonateEconomy\api\exception\InsufficientFundsException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use function count;

final class DRemoveBalanceCommand extends Command implements PluginOwned
{
    public function __construct()
    {
        parent::__construct("dremovebalance", "Remove donate balance from a player", "/dremovebalance <player> <amount>");
        $this->setPermission("bedrockdonateeconomy.command.dremovebalance");
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
            $sender->sendMessage("Usage: /dremovebalance <player> <amount>");
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

        BedrockDonateEconomyAPI::CLOSURE()->subtract(
            xuid: $target->getXuid(),
            username: $target->getName(),
            amount: $whole,
            decimals: $decimals,
            onSuccess: static function () use ($sender, $target, $amount): void {
                $sender->sendMessage("§cRemoved §6\$$amount §cfrom §f{$target->getName()}§c.");
                if ($target->isOnline()) {
                    $target->sendMessage("§cYour donate balance was reduced by §6\$$amount§c.");
                }
            },
            onError: static function (\Throwable $e) use ($sender): void {
                if ($e instanceof InsufficientFundsException) {
                    $sender->sendMessage("§cPlayer has insufficient donate balance.");
                } else {
                    $sender->sendMessage("§cFailed to remove balance.");
                }
            }
        );
    }
}