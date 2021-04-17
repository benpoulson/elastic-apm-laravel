<?php

namespace Itb\ElasticApm\Spans;

use Itb\ElasticApm\Apm;
use Itb\ElasticApm\Contracts\SpanContract;

class FrameworkEventSpan implements SpanContract
{
    use SpanEmptyFieldsTrait;

    private $event;

    private $time;

    private $start;

    public function __construct(string $event, string $startTime)
    {
        $this->event = $event;
        $this->time = Apm::getMicrotime() - $startTime;
        $this->start = $startTime;
    }

    public function getName(): string
    {
        return $this->event;
    }

    public function getType(): string
    {
        return 'framework';
    }

    public function getSubType(): string
    {
        return 'bootstrap';
    }

    public function getLabels(): array
    {
        return $this->getSpanData();
    }

    public function getSpanData(): array
    {
        return [
            'time' => $this->time - $this->start,
            'start' => $this->start,
            'event' => $this->event
        ];
    }
}
