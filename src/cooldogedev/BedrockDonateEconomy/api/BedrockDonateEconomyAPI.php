<?php

declare(strict_types=1);

namespace cooldogedev\BedrockDonateEconomy\api;

use cooldogedev\BedrockDonateEconomy\api\exception\InsufficientFundsException;
use cooldogedev\BedrockDonateEconomy\api\exception\RecordNotFoundException;
use SQLite3;
use function intval;

final class BedrockDonateEconomyAPI
{
    private static SQLite3 $database;

    public static function init(SQLite3 $db): void
    {
        self::$database = $db;
    }

    public static function CLOSURE(): self
    {
        return new self();
    }

    public static function ASYNC(): self
    {
        return new self();
    }

    public function get(string $xuid, string $username, ?callable $onSuccess = null, ?callable $onError = null): array
    {
        $stmt = self::$database->prepare("SELECT amount, decimals FROM donate_balances WHERE xuid = :xuid");
        $stmt->bindValue(":xuid", $xuid);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if (!$row) {
            $e = new RecordNotFoundException("Account not found");
            if ($onError !== null) {
                $onError($e);
            }
            throw $e;
        }

        $position = intval(self::$database->querySingle("SELECT COUNT(*) FROM donate_balances WHERE amount > {$row["amount"]} OR (amount = {$row["amount"]} AND decimals > {$row["decimals"]})")) + 1;

        $data = [
            "amount" => intval($row["amount"]),
            "decimals" => intval($row["decimals"]),
            "position" => $position
        ];

        if ($onSuccess !== null) {
            $onSuccess($data);
        }
        return $data;
    }

    public function bulk(array $list, ?callable $onSuccess = null, ?callable $onError = null): array
    {
        if (empty($list)) {
            $e = new RecordNotFoundException("No players provided");
            if ($onError !== null) {
                $onError($e);
            }
            throw $e;
        }

        // Получаем топ-100 (как в оригинале)
        $res = self::$database->query("SELECT username, amount, decimals FROM donate_balances ORDER BY amount DESC, decimals DESC LIMIT 100");
        $result = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $result[] = $row;
        }

        if (empty($result)) {
            $e = new RecordNotFoundException("No records found");
            if ($onError !== null) {
                $onError($e);
            }
            throw $e;
        }

        if ($onSuccess !== null) {
            $onSuccess($result);
        }
        return $result;
    }

    public function add(string $xuid, string $username, int $amount, int $decimals, ?callable $onSuccess = null, ?callable $onError = null): void
    {
        try {
            $this->get($xuid, $username); // убедимся, что аккаунт существует

            $newDecimals = intval(self::$database->querySingle("SELECT decimals FROM donate_balances WHERE xuid = '$xuid'")) + $decimals;
            $carry = intdiv($newDecimals, 100);
            $newDecimals = $newDecimals % 100;
            $newAmount = intval(self::$database->querySingle("SELECT amount FROM donate_balances WHERE xuid = '$xuid'")) + $amount + $carry;

            $stmt = self::$database->prepare("UPDATE donate_balances SET amount = :amount, decimals = :decimals WHERE xuid = :xuid");
            $stmt->bindValue(":amount", $newAmount);
            $stmt->bindValue(":decimals", $newDecimals);
            $stmt->bindValue(":xuid", $xuid);
            $stmt->execute();

            if ($onSuccess !== null) {
                $onSuccess();
            }
        } catch (\Throwable $e) {
            if ($onError !== null) {
                $onError($e);
            }
        }
    }

    public function subtract(string $xuid, string $username, int $amount, int $decimals, ?callable $onSuccess = null, ?callable $onError = null): void
    {
        try {
            $balance = $this->get($xuid, $username);
            $current = $balance["amount"] * 100 + $balance["decimals"];
            $toRemove = $amount * 100 + $decimals;

            if ($current < $toRemove) {
                throw new InsufficientFundsException("Insufficient funds");
            }

            $newTotal = $current - $toRemove;
            $newAmount = intdiv($newTotal, 100);
            $newDecimals = $newTotal % 100;

            $stmt = self::$database->prepare("UPDATE donate_balances SET amount = :amount, decimals = :decimals WHERE xuid = :xuid");
            $stmt->bindValue(":amount", $newAmount);
            $stmt->bindValue(":decimals", $newDecimals);
            $stmt->bindValue(":xuid", $xuid);
            $stmt->execute();

            if ($onSuccess !== null) {
                $onSuccess();
            }
        } catch (\Throwable $e) {
            if ($onError !== null) {
                $onError($e);
            }
        }
    }

    public function transfer(array $source, array $target, int $amount, int $decimals, ?callable $onSuccess = null, ?callable $onError = null): void
    {
        try {
            $this->subtract($source["xuid"], $source["username"], $amount, $decimals);
            $this->add($target["xuid"], $target["username"], $amount, $decimals);
            if ($onSuccess !== null) {
                $onSuccess();
            }
        } catch (\Throwable $e) {
            if ($onError !== null) {
                $onError($e);
            }
        }
    }

    // 🔥 Добавлен метод set — как в оригинальном BedrockEconomy (для setbalance)
    public function set(string $xuid, string $username, int $amount, int $decimals, ?callable $onSuccess = null, ?callable $onError = null): void
    {
        try {
            // Создаём или обновляем запись (как INSERT OR REPLACE)
            $stmt = self::$database->prepare("INSERT OR REPLACE INTO donate_balances (xuid, username, amount, decimals) VALUES (:xuid, :username, :amount, :decimals)");
            $stmt->bindValue(":xuid", $xuid);
            $stmt->bindValue(":username", $username);
            $stmt->bindValue(":amount", $amount);
            $stmt->bindValue(":decimals", $decimals);
            $stmt->execute();

            if ($onSuccess !== null) {
                $onSuccess();
            }
        } catch (\Throwable $e) {
            if ($onError !== null) {
                $onError($e);
            }
        }
    }
}