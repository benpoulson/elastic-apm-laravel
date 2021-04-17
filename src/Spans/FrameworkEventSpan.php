<?php

namespace Itb\ElasticApm\Spans;

use Itb\ElasticApm\Contracts\SpanContract;

class FrameworkEventSpan implements SpanContract
{
    use SpanEmptyFieldsTrait;

    private $event;

    private $time;

    private $startTime;

    public function __construct(string $event, int $time, int $startTime = null)
    {
        $this->event = $event;
        $this->time = $time;
        $this->startTime = $startTime;
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
        $data = [
            'time' => $this->time,
            'event' => $this->event
        ];

        // Was a start time provided?
        if ($this->startTime) {
            $data['start'] = $this->startTime;
        }

        return $data;
    }
}
