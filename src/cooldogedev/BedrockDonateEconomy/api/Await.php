<?php

declare(strict_types=1);

namespace cooldogedev\BedrockDonateEconomy\api;

use Closure;
use Generator;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use function array_values;
use function spl_object_id;

final class Await
{
    /** @var array<int, Closure> */
    private static array $callbacks = [];

    public static function f2c(Closure $closure): void
    {
        $id = spl_object_id($closure);
        self::$callbacks[$id] = $closure;
        Server::getInstance()->getAsyncPool()->submitTask(new class ($id) extends AsyncTask {
            public function __construct(private int $id) {}

            public function onRun(): void
            {
                // noop
            }

            public function onCompletion(Server $server): void
            {
                $callback = Await::$callbacks[$this->id];
                unset(Await::$callbacks[$this->id]);
                $server->getTickSleeper()->sleep();
                $generator = $callback();
                if ($generator instanceof Generator) {
                    $server->getTickSleeper()->wakeup();
                }
            }
        });
    }
}