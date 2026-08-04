<?php
declare(strict_types=1);

final class EventDispatcher
{
    /** @var array<string,list<callable>> */
    private static array $listeners = [];

    public static function listen(string $event, callable $listener): void
    {
        self::$listeners[$event] ??= [];
        self::$listeners[$event][] = $listener;
    }

    public static function dispatch(string $event, array $payload = []): array
    {
        $results = [];
        foreach (self::$listeners[$event] ?? [] as $listener) {
            try {
                $results[] = $listener($payload);
            } catch (Throwable $e) {
                if (function_exists('app_log')) app_log($e, ['event' => $event]);
            }
        }
        return $results;
    }
}
