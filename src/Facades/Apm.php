<?php

namespace Itb\ElasticApm\Facades;

use Itb\ElasticApm\Apm as ElasticAgent;
use Itb\ElasticApm\Transaction;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string instance()
 * @method static void reset()
 * @method static \Itb\ElasticApm\Apm reinstance()
 * @method static ElasticAgent setTransaction(Transaction $transaction)
 * @method static Transaction|null getTransaction(Transaction $transaction)
 * @method static void capture()
 *
 * @see \Itb\ElasticApm\Apm
 */
class Apm extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'elastic-apm-agent';
    }
}
