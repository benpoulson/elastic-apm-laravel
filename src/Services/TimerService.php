<?php

namespace Itb\ElasticApm\Services;

use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;

/**
 * Class TimerService
 * @package Itb\ElasticApm\Services
 */
class TimerService
{
    /**
     * @var Collection
     */
    protected static $time;

    /**
     * @var string
     */
    protected static $customNamePrefix = 'custom-';

    /**
     * Initialiser
     */
    public static function init()
    {
        self::$time = Collection::make();
    }

    /**
     * @param string $name
     * @return void
     */
    public static function start(string $name): void
    {
        self::$time->put($name, [
            'start' => self::now(),
        ]);
    }

    /**
     * @param string $name
     * @return void
     */
    public static function finish(string $name): void
    {
        self::$time->put($name, array_merge(self::getByName($name), [
            'finish' => self::now(),
        ]));
    }

    /**
     * @param string $name
     * @return void
     * @throws TimerException
     */
    public static function startCustom(string $name): void
    {
        $customName = self::$customNamePrefix . $name;

        self::guardTimerAlreadyStarted($customName);

        self::start($customName);
    }

    /**
     * @param string $name
     * @return void
     * @throws TimerException
     */
    public static function finishCustom(string $name): void
    {
        $customName = self::$customNamePrefix . $name;

        self::guardTimerAlreadyFinished($customName);

        self::guardTimerNotStartedYet($customName);

        self::finish($customName);
    }

    /**
     * @return void
     */
    public static function startLaravel(): void
    {
        self::$time->put('Bootstrap', [
            'start' => self::laravelStartTimeOrNow(),
        ]);
    }

    /**
     * @return void
     */
    public static function finishLaravel(): void
    {
        self::finish('Bootstrap');
    }

    /**
     * @param string $name
     * @return float
     */
    public static function milliseconds(string $name): float
    {
        return self::millisecondsOf(self::getByName($name));
    }

    /**
     * @param string $name
     * @return float
     */
    public static function millisecondsCustom(string $name): float
    {
        return self::milliseconds(self::$customNamePrefix . $name);
    }

    /**
     * @return string
     */
    public static function getLatestFinish()
    {
        return self::$time->filter(function ($item) {
            return self::isCompleted($item);
        })->max(function ($item) {
            return $item['finish'];
        });
    }

    /**
     * @return array
     */
    public static function all(): array
    {
        return self::$time->filter(function ($item) {
            return self::isCompleted($item);
        })->map(function ($item) {
            return [
                'start' => $item['start'],
                'finish' => $item['finish'],
                'total' => $item['finish'] - $item['start']
            ];
        })->toArray();
    }

    /**
     * @return float
     */
    protected static function now(): float
    {
        return self::convertMicrotime(\microtime(true));
    }

    protected static function convertMicrotime($microtime)
    {
        return (int)($microtime * 1000000);
    }

    /**
     * @return float
     */
    protected static function laravelStartTimeOrNow(): float
    {
        return defined('LARAVEL_START') ? self::convertMicrotime(LARAVEL_START) : self::now();
    }

    /**
     * @param string $name
     * @return array
     */
    public static function getByName(string $name): array
    {
        return self::$time->first(function ($a, $b) use ($name) {
                return $a === $name || $b === $name;
            }) ?? [];
    }

    /**
     * @param array $item
     * @return float
     */
    protected static function millisecondsOf(array $item): float
    {
        if (!self::isCompleted($item)) {
            return -1;
        }

        return ($item['finish'] - $item['start']) * 1000;
    }

    /**
     * @param array $item
     * @return bool
     */
    protected static function isCompleted(array $item): bool
    {
        return isset($item['start']) && isset($item['finish']);
    }
}
