<?php

namespace Itb\ElasticApm;

use Carbon\Carbon;
use Itb\ElasticApm\Contracts\SpanContract;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\TransactionInterface;

class Apm
{
    /** @var self */
    private static $instance = null;

    /**
     * @var array
     */
    private static $commandTimes = [];

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
     * @param string $name
     */
    public function setStartTime(string $name)
    {
        self::$commandTimes[$name] = Carbon::now()->toDateTimeString();
    }

    /**
     * @param string $name
     * @return int
     */
    public function getStartTime(string $name)
    {
        if (!isset(self::$commandTimes[$name])) {
            return Carbon::now()->toDateTimeString();
        }

        $duration = self::$commandTimes[$name];
        unset(self::$commandTimes[$name]);

        return $duration;
    }

    /**
     * @param SpanContract $span
     */
    public function addSpan(SpanContract $span)
    {
        $childSpan = $this->getTransaction()->beginChildSpan($span->getName(), $span->getType(), $span->getSubType());
        if ($labels = $span->getLabels()) {
            foreach ($labels as $key => $value) {
                $childSpan->context()->setLabel($key, $value);
            }
        }
        $childSpan->setAction(json_encode($span->getSpanData()));
        $childSpan->end();
    }
}
