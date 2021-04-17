<?php

namespace Itb\ElasticApm\Spans;

use Itb\ElasticApm\Contracts\SpanContract;

class RequestProcessedSpan implements SpanContract
{
    use SpanEmptyFieldsTrait;

    private $transactionName;

    private $data;

    public function __construct(string $transactionName, array $data)
    {
        $this->transactionName = $transactionName;
        $this->data = $data;
    }

    public function getName(): string
    {
        return $this->transactionName;
    }

    public function getType(): string
    {
        return 'request';
    }

    public function getSubType(): string
    {
        return 'processed';
    }

    public function getSpanData(): array
    {
        return $this->data;
    }
}
