<?php

namespace Itb\ElasticApm\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Itb\ElasticApm\Apm;
use Itb\ElasticApm\Spans\CommandSpan;
use Itb\ElasticApm\Spans\FrameworkEventSpan;
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
            $this->listenLaravelEvents();
            $this->listenExecutedQueries();
//            $this->listenExecutedRedis();
            $this->listenArtisanCommands();
        }
    }

    public function register()
    {
        $this->registerApmAgent();
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
            $apm->startTimer($event->command);
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            /** @var Apm $apm */
            $apm = app('elastic-apm');
            $time = $apm->stopTimer($event->command);
            $apm->addSpan(new CommandSpan($event->command, $event->input, $event->output, $event->exitCode, $time));
        });
    }

//    private function listenExecutedRedis()
//    {
//        app('redis')->enableEvents();
//        app('redis')->listen(
//            function (CommandExecuted $command) {
//                $cmd = $command->command;
//                $connection = $command->connectionName;
//                $duration = $command->time;
//
//                app('elastic-apm')->addSpan(new RedisSpan($connection, $cmd, $duration));
//            }
//        );
//    }

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

    private function listenLaravelEvents()
    {
        /** @var Apm $apm */
        $apm = app('elastic-apm');
        $apm->startTimer('app_boot');

        $this->app->booting(function () use ($apm) {
            $apm->startTimer('laravel_boot');
            $appBootTime = $apm->stopTimer('app_boot');
            $apm->addSpan(new FrameworkEventSpan("App Boot", $appBootTime, LARAVEL_START));
        });

        $this->app->booted(function () use ($apm) {
            $laravelBootTime = $apm->stopTimer('laravel_boot');
            $apm->addSpan(new FrameworkEventSpan("Laravel Boot", $laravelBootTime));
        });

        $this->app->booted(function () use ($apm) {
            $apm->startTimer('route_matching');
        });

        Event::listen(RouteMatched::class, function () use ($apm) {
            $apm->startTimer('request_handled');
            $routeMatchingTime = $apm->stopTimer('route_matching');
            $apm->addSpan(new FrameworkEventSpan("Route Matching", $routeMatchingTime));
        });

        Event::listen(RequestHandled::class, function () use ($apm) {
            // Some middlewares might return a response
            // before the RouteMatched has been dispatched
            $requestHandledTime = $apm->stopTimer('request_handled');
            $apm->addSpan(new FrameworkEventSpan($this->getController(), $requestHandledTime));
        });
    }

    protected function getController(): ?string
    {
        $router = $this->app['router'];

        $route = $router->current();
        $controller = $route ? $route->getActionName() : null;

        if ($controller instanceof \Closure) {
            $controller = 'anonymous function';
        } elseif (is_object($controller)) {
            $controller = 'instance of ' . get_class($controller);
        } elseif (is_array($controller) && 2 == count($controller)) {
            if (is_object($controller[0])) {
                $controller = get_class($controller[0]) . '->' . $controller[1];
            } else {
                $controller = $controller[0] . '::' . $controller[1];
            }
        } elseif (!is_string($controller)) {
            $controller = null;
        }

        return $controller;
    }
}
