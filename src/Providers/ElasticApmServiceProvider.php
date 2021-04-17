<?php

namespace Itb\ElasticApm\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Itb\ElasticApm\Apm;
use Itb\ElasticApm\Spans\CommandSpan;
use Itb\ElasticApm\Spans\QuerySpan;
use Itb\ElasticApm\Spans\RedisSpan;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

/**
 * Class ElasticApmServiceProvider
 * @package Itb\ElasticApm\Providers
 */
class ElasticApmServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $source = realpath($raw = __DIR__ . '/../config/elastic-apm.php') ?: $raw;

        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path('elastic-apm.php')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('elastic-apm');
        }

        $this->mergeConfigFrom($source, 'elastic-apm');

        if (config('elastic-apm.active')) {
            if (config('elastic-apm.send_queries')) {
                $this->listenExecutedQueries();
            }
            if (config('elastic-apm.send_redis')) {
                $this->listenExecutedRedis();
            }
            if (config('elastic-apm.send_artisan')) {
                $this->listenArtisanCommands();
            }
        }
    }

    public function register()
    {
        $this->registerApmAgent();
        $this->registerMiddleware();
    }

    private function listenExecutedQueries()
    {
        app('db')->listen(
            function (QueryExecuted $query) {
                $sql = $query->sql;
                $connection = $query->connection->getName();
                $duration = $query->time;

                app('elastic-apm')->addSpan(new QuerySpan($connection, $sql, $duration));
            }
        );
    }

    private function listenArtisanCommands()
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            /** @var Apm $apm */
            $apm = app('elastic-apm');
            $apm->startCommandTimer($event->command);
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            /** @var Apm $apm */
            $apm = app('elastic-apm');
            $time = $apm->stopCommandTimer($event->command);
            $apm->addSpan(new CommandSpan($event->command, $event->input, $event->output, $event->exitCode, $time));
        });
    }

    private function listenExecutedRedis()
    {
        app('redis')->enableEvents();
        app('redis')->listen(
            function (CommandExecuted $command) {
                $cmd = $command->command;
                $connection = $command->connectionName;
                $duration = $command->time;

                app('elastic-apm')->addSpan(new RedisSpan($connection, $cmd, $duration));
            }
        );
    }

    private function registerApmAgent()
    {
        $this->app->singleton(
            Apm::class,
            function ($app) {
                return Apm::instance();
            }
        );
        $this->app->alias(Apm::class, 'elastic-apm');
    }

    public function provides()
    {
        return [
            Apm::class,
            'elastic-apm',
        ];
    }
}
