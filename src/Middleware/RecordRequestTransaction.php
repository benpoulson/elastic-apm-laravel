<?php

namespace Itb\ElasticApm\Middleware;

use Itb\ElasticApm\Apm;
use Itb\ElasticApm\Spans\RequestProcessedSpan;
use Itb\ElasticApm\Transaction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * Used to track inbound HTTP requests
 * Class RecordRequestTransaction
 * @package Itb\ElasticApm\Middleware
 */
class RecordRequestTransaction
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if (false === config('elastic-apm.active')) {
            return $response;
        }

        $this->setupTransaction($request);

        return $response;
    }

    private function setupTransaction($request)
    {
        /** @var Apm $apm */
        $apm = app('elastic-apm');
        $transaction = $apm->getTransaction();
        $transaction->setName($this->getTransactionName($request));
        $transaction->setType($this->getTransactionType());
    }

    private function getTransactionType()
    {
        return 'request';
    }

    private function getTransactionName(Request $request)
    {
        $route = $request->route();
        // Lumen returns ARRAY.
        if (is_array($route)) {
            // Try the assigned controller action
            if (isset($route[1]) && isset($route[1]['uses'])) {
                return $route[1]['uses'];
            } elseif (isset($route[1]) && isset($route[1]['as'])) {
                // Try named routes
                return $route[1]['as'];
            }
        } elseif ($route instanceof Route) {
            // Laravel returns Route also with other types
            if (is_string($uses = $route->getAction('uses'))) {
                return $uses;
            } elseif (is_string($as = $route->getAction('as'))) {
                return $as;
            }
        }

        // Either missed from lumen array or Missed from Laravel Route object
        if (!is_null($route)) {
            return sprintf('%s %s', $request->getMethod(), $request->path());
        }

        // Possibly 404
        return 'index.php';
    }

    public function terminate($request, $response)
    {
        if (false === config('elastic-apm.active')) {
            return;
        }

        /** @var Apm $apm */
        $apm = app('elastic-apm');

        $apm->addSpan(
            new RequestProcessedSpan(
                $this->getTransactionName($request),
                [
                    'now' => now()->toDateTimeString(),
                    'status_code' => $response->getStatusCode(),
                    'path' => $request->path(),
                    'processing_time' => microtime(true) - LARAVEL_START,
                    'user_agent' => $request->userAgent(),
                ]
            )
        );

        $apm->endTransaction();
    }
}
