<?php

namespace Hakam\AiLogInspector\DTO;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Data Transfer Object for log data.
 * Used to pass structured log information to LogDocumentFactory.
 */
readonly class LogDataDTO
{
    public function __construct(
        public string $message,
        public string $level = 'INFO',
        public ?DateTimeInterface $timestamp = null,
        public string $channel = 'app',
        public array $context = [],
        public array $extra = [],
        public array $enrichedData = []
    ) {
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        $timestamp = null;
        if (isset($data['timestamp'])) {
            $timestamp = $data['timestamp'] instanceof DateTimeInterface
                ? $data['timestamp']
                : new DateTimeImmutable($data['timestamp']);
        }

        return new self(
            message: $data['message'] ?? '',
            level: $data['level']     ?? 'INFO',
            timestamp: $timestamp,
            channel: $data['channel']            ?? 'app',
            context: $data['context']            ?? [],
            extra: $data['extra']                ?? [],
            enrichedData: $data['enriched_data'] ?? $data['enrichedData'] ?? []
        );
    }

    /**
     * Get timestamp with fallback to current time.
     */
    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp ?? new DateTimeImmutable();
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'message'       => $this->message,
            'level'         => $this->level,
            'timestamp'     => $this->getTimestamp()->format(DateTimeInterface::ATOM),
            'channel'       => $this->channel,
            'context'       => $this->context,
            'extra'         => $this->extra,
            'enriched_data' => $this->enrichedData,
        ];
    }
}
