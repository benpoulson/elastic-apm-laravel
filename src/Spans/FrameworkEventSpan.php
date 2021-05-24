<?php

namespace Itb\ElasticApm\Spans;

use Itb\ElasticApm\Apm;
use Itb\ElasticApm\Contracts\SpanContract;

class FrameworkEventSpan implements SpanContract
{
    use SpanEmptyFieldsTrait;

    private $event;

    private $duration;

    private $start;

    public function __construct(string $event, $startTime, $duration)
    {
        $this->event = $event;
        $this->start = $startTime;
        $this->duration = $duration;
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
            'duration' => $this->duration,
            'start' => $this->start,
            'event' => $this->event
        ];
    }
}
