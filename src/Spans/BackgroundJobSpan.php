<?php

namespace Itb\ElasticApm\Spans;

use Itb\ElasticApm\Apm;
use Itb\ElasticApm\Contracts\SpanContract;

class BackgroundJobSpan implements SpanContract
{
    use SpanEmptyFieldsTrait;

    private $job;

    private $time;

    private $state;

    public function __construct(string $job, string $state)
    {
        $this->job = $job;
        $this->state = $state;
        $this->time = Apm::getMicrotime();
    }

    public function getName(): string
    {
        return $this->state;
    }

    public function getType(): string
    {
        return 'job';
    }

    public function getSubType(): string
    {
        return $this->job;
    }

    public function getLabels(): array
    {
        return $this->getSpanData();
    }

    public function getSpanData(): array
    {
        return [
            'time' => $this->time,
            'state' => $this->state,
        ];
    }
}
