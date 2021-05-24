<?php

namespace Itb\ElasticApm\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Itb\ElasticApm\Apm;
use Itb\ElasticApm\Services\TimerService;
use Itb\ElasticApm\Spans\FrameworkEventSpan;
use Itb\ElasticApm\Spans\QuerySpan;
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
//            $this->listenExecutedQueries();
//            $this->listenExecutedRedis();
            $this->listenArtisanCommands();
        }

        event('boot');
    }

    public function register()
    {
        $this->app->singleton(Apm::class, function ($app) {
            return Apm::instance();
        });
        $this->app->alias(Apm::class, 'elastic-apm');
    }

    private function listenExecutedQueries()
    {
        app('db')->listen(function (QueryExecuted $query) {
            $sql = $query->sql;
            $connection = $query->connection->getName();
            $duration = $query->time;
            app('elastic-apm')->addSpan(new QuerySpan($connection, $sql, $duration));
        });
    }

    private function listenArtisanCommands()
    {
//        Event::listen(CommandStarting::class, function (CommandStarting $event) {
//            /** @var Apm $apm */
//            $apm = app('elastic-apm');
//            $apm->setStartTime($event->command);
//        });
//
//        Event::listen(CommandFinished::class, function (CommandFinished $event) {
//            /** @var Apm $apm */
//            $apm = app('elastic-apm');
//            $time = $apm->getStartTime($event->command);
//            $apm->addSpan(new CommandSpan($event->command, $event->input, $event->output, $event->exitCode, $time, microtime(true)));
//        });
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

        TimerService::init();

        Event::listen('boot', function () {
            TimerService::startLaravel();

        });

        $this->app->booting(function () {
            TimerService::start('Boot');
        });

        $this->app->booted(function () use ($apm) {
            TimerService::finish('Boot');
            TimerService::start('route');
        });

        Event::listen(RouteMatched::class, function () {
            TimerService::finish('Route');
            TimerService::start('Request');
        });

        /** @codeCoverageIgnoreStart */
        Event::listen('kernel.handled', function () {
            TimerService::finish('Request');
            TimerService::start('Response');
        });
        /** @codeCoverageIgnoreEnd */

        Event::listen(RequestHandled::class, function () {
            TimerService::finish('Request');
            TimerService::start('Response');
        });

        $this->app->afterBootstrapping(BootProviders::class, function () use ($apm) {
            $this->app->terminating(function () use ($apm) {
                TimerService::finish('Response');
                TimerService::finishLaravel();

                foreach (TimerService::all() as $name => $value) {
                    $apm->addSpan(new FrameworkEventSpan($name, $value['start'], $value['finish']));
                }

                $end = number_format((int)(TimerService::getLatestFinish()) - $apm->getTransaction()->getTimestamp(), 3, '.', '');

                $apm->getTransaction()->end($end / 1000);

            });
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
