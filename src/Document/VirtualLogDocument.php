<?php

namespace Hakam\AiLogInspector\Document;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Virtual log document created at runtime with rich context
 * This document exists only in memory and gets converted to vectors immediately
 */
class VirtualLogDocument
{
    public array $enrichedData = [];

    public function __construct(
        public string            $message,
        public string            $level,
        public DateTimeInterface $timestamp,
        public string            $channel = 'app',
        public array             $context = [],
        public array             $extra = []
    )
    {
    }

    /**
     * Create from exception
     */
    public static function fromException(Throwable $exception, array $additionalContext = []): self
    {
        return new self(
            message: $exception->getMessage(),
            level: 'ERROR',
            timestamp: new DateTimeImmutable(),
            channel: 'exception',
            context: array_merge([
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'stack_trace' => $exception->getTraceAsString(),
                'previous_exception' => $exception->getPrevious()?->getMessage(),
                'code' => $exception->getCode(),
            ], $additionalContext)
        );
    }

    public function enrichWith(array $data): self
    {
        $this->enrichedData = array_merge($this->enrichedData, $data);
        return $this;
    }

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function addContextArray(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
}