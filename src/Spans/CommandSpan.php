<?php

namespace Itb\ElasticApm\Spans;

use Itb\ElasticApm\Contracts\SpanContract;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CommandSpan
 * @package Itb\ElasticApm\Spans
 */
class CommandSpan implements SpanContract
{
    use SpanEmptyFieldsTrait;

    /**
     * @var string
     */
    private $command;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var int
     */
    private $exitCode;

    /**
     * @var int
     */
    private $time;

    /**
     * CommandSpan constructor.
     * @param string $command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param int $exitCode
     * @param int $time
     */
    public function __construct(
        string $command,
        InputInterface $input,
        OutputInterface $output,
        int $exitCode,
        int $time
    ) {
        $this->command = $command;
        $this->input = $input;
        $this->output = $output;
        $this->exitCode = $exitCode;
        $this->time = $time;
    }

    public function getName(): string
    {
        return $this->command;
    }

    public function getType(): string
    {
        return 'command';
    }

    public function getSubType(): string
    {
        return 'artisan';
    }

    public function getLabels(): array
    {
        return $this->getSpanData();
    }

    public function getSpanData(): array
    {
        return [
            'command' => $this->command,
            'exitCode' => $this->exitCode,
            'time' => $this->time
        ];
    }
}
