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

final class DPayCommand extends Command implements PluginOwned
{
    public function __construct()
    {
        parent::__construct("dpay", "Pay someone with donate currency", "/dpay <player> <amount>");
        $this->setPermission("bedrockdonateeconomy.command.dpay");
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

        if (count($args) < 2) {
            $sender->sendMessage("Usage: /dpay <player> <amount>");
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

        $source = ["xuid" => $sender->getXuid(), "username" => $sender->getName()];
        $dest = ["xuid" => $target->getXuid(), "username" => $target->getName()];

        BedrockDonateEconomyAPI::CLOSURE()->transfer(
            source: $source,
            target: $dest,
            amount: $whole,
            decimals: $decimals,
            onSuccess: static function () use ($sender, $target, $amount): void {
                $sender->sendMessage("§aYou paid §6\$$amount §ato §f{$target->getName()}§a.");
                $target->sendMessage("§aYou received §6\$$amount §afrom §f{$sender->getName()}§a.");
            },
            onError: static function (\Throwable $e) use ($sender): void {
                if ($e instanceof InsufficientFundsException) {
                    $sender->sendMessage("§cInsufficient donate balance.");
                } else {
                    $sender->sendMessage("§cPayment failed.");
                }
            }
        );
    }
}