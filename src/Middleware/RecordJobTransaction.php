<?php

namespace Itb\ElasticApm\Middleware;

use Itb\ElasticApm\Apm;
use Itb\ElasticApm\Spans\BackgroundJobSpan;
use Carbon\Carbon;
use Closure;

/**
 * Used to track Laravel jobs
 * Class RecordJobTransaction
 * @package Itb\ElasticApm\Middleware
 */
class RecordJobTransaction
{
    /**
     * @param $job
     * @param Closure $next
     * @return mixed
     */
    public function handle($job, Closure $next)
    {
        if (false === config('elastic-apm.active')) {
            return $next($job);
        }

        /** @var Apm $apm */
        $apm = app('elastic-apm');

        $transaction = $apm->getTransaction();
        $transaction->setName($this->getTransactionName($job));
        $transaction->setType($this->getTransactionType());

        // Set span for the job it's processing
        $jobClass = get_class($job);
        $apm->addSpan(new BackgroundJobSpan($jobClass, 'processing', microtime(true)));
        $response = $next($job);

        // set span for the class which is processed
        $apm->addSpan(new BackgroundJobSpan($jobClass, 'processed', microtime(true)));

        $apm->endTransaction();

        return $response;
    }

    /**
     * @param $job
     * @return string
     */
    private function getTransactionName($job)
    {
        return get_class($job);
    }

    /**
     * @return string
     */
    private function getTransactionType()
    {
        return 'queue';
    }
}
