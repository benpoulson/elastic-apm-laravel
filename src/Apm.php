<?php

namespace Itb\ElasticApm;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\TransactionInterface;
use Illuminate\Support\Facades\Log;
use Itb\ElasticApm\Contracts\SpanContract;

/**
 * Class Apm
 * @package Itb\ElasticApm
 */
class Apm
{
    /** @var self */
    private static $instance = null;

    /**
     * @return Apm
     */
    public static function instance()
    {
        return static::$instance ? static::$instance : (static::$instance = new self());
    }

    public function getTransaction(): TransactionInterface
    {
        return ElasticApm::getCurrentTransaction() ?? ElasticApm::beginCurrentTransaction(null, null);
    }

    public function endTransaction()
    {
        $this->getTransaction()->end();
    }

    /**
     * @param SpanContract $span
     */
    public function addSpan(SpanContract $span)
    {
        $labels = $span->getLabels();
        $timestamp = $this->getTransaction()->getTimestamp();
        $childSpan = $this->getTransaction()->beginChildSpan($span->getName(), $span->getType(), $span->getSubType(), null, $labels['start']);
        $labels['duration'] = $labels['duration'] - $labels['start'];
        $labels['start'] = $labels['start'] - $timestamp;
        $labels['duration'] /= 1000;
        $labels['start'] /= 1000;
        foreach ($labels as $key => $value) {
            $childSpan->context()->setLabel($key, $value);
        }
        $childSpan->setAction(json_encode($labels + $span->getSpanData()));
        $childSpan->end($labels['duration']);
    }
}
