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

final class DSetBalanceCommand extends Command implements PluginOwned
{
    public function __construct()
    {
        parent::__construct("dsetbalance", "Set a player's donate balance", "/dsetbalance <player> <amount>");
        $this->setPermission("bedrockdonateeconomy.command.dsetbalance");
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
            $sender->sendMessage("Usage: /dsetbalance <player> <amount>");
            return;
        }

        $target = $this->getOwningPlugin()->getServer()->getPlayerByPrefix($args[0]);
        if ($target === null) {
            $sender->sendMessage("Player not found.");
            return;
        }

        if (!is_numeric($args[1]) || $args[1] < 0) {
            $sender->sendMessage("Amount must be a number >= 0.");
            return;
        }

        // ✅ Объявляем переменную $amount здесь
        $amount = (float)$args[1];
        $whole = (int)$amount;
        $decimals = (int)(($amount - $whole) * 100);

        // Используем API set
        BedrockDonateEconomyAPI::CLOSURE()->set(
            xuid: $target->getXuid(),
            username: $target->getName(),
            amount: $whole,
            decimals: $decimals,
            onSuccess: static function () use ($sender, $target, $amount): void { // ✅ Передаём $amount через use
                $sender->sendMessage("§aSet §f{$target->getName()}§a's donate balance to §6\$$amount§a.");
                if ($target->isOnline()) {
                    $target->sendMessage("§aYour donate balance was set to §6\$$amount§a.");
                }
            },
            onError: static function (\Throwable $e) use ($sender): void {
                if ($e instanceof RecordNotFoundException) {
                    $sender->sendMessage("§cAccount not found.");
                } else {
                    $sender->sendMessage("§cFailed to set balance: " . $e->getMessage());
                }
            }
        );
    }
}