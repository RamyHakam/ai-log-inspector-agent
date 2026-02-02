<?php

namespace Hakam\AiLogInspector\Document;

use DateTimeImmutable;
use DateTimeInterface;
use Hakam\AiLogInspector\DTO\LogDataDTO;

final class LogDocumentFactory
{
    /**
     * Create from DTO or array with rich semantic content for vector search.
     *
     * @param LogDataDTO|array $data Log data as DTO or array
     * @return LogDocument
     */
    public static function createFromData(LogDataDTO|array $data): LogDocument
    {
        $dto             = $data instanceof LogDataDTO ? $data : LogDataDTO::fromArray($data);
        $semanticContent = self::createSemanticContent($dto);
        $metadata        = self::createMetadataFromDTO($dto);

        return new LogDocument(
            content: $semanticContent,
            rowMetadata: $metadata
        );
    }

    public static function createFromString(String $stringText): LogDocument
    {
        return new LogDocument(
            content: $stringText,
            rowMetadata: [
                'created_at'   => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'source'       => 'string',
                'string_query' => $stringText,
            ]
        );
    }

    /**
     * Create rich semantic content optimized for vector search.
     * This method generates human-readable text that captures the essence of the log entry.
     *
     * @param LogDataDTO|array $data Log data as DTO or array
     * @return string Rich semantic content for vectorization
     */
    public static function createSemanticContent(LogDataDTO|array $data): string
    {
        $dto = $data instanceof LogDataDTO ? $data : LogDataDTO::fromArray($data);

        $parts = [];

        $parts[] = "Log Message: {$dto->message}";
        $parts[] = "Severity: {$dto->level}";
        $parts[] = "Channel: {$dto->channel}";
        $parts[] = "Timestamp: " . $dto->getTimestamp()->format('Y-m-d H:i:s T');

        // HTTP Request context
        $requestParts = [];
        if (isset($dto->context['method'])) {
            $requestParts[] = strtoupper($dto->context['method']);
        }
        if (isset($dto->context['url'])) {
            $requestParts[] = $dto->context['url'];
        }
        if (isset($dto->context['route'])) {
            $requestParts[] = "Route: {$dto->context['route']}";
        }
        if (isset($dto->context['status_code'])) {
            $requestParts[] = "Status: {$dto->context['status_code']}";
        }
        if (!empty($requestParts)) {
            $parts[] = "HTTP Request: " . implode(' ', $requestParts);
        }

        // Request identification
        if (isset($dto->context['request_id'])) {
            $parts[] = "Request ID: {$dto->context['request_id']}";
        }

        // User context with role information
        $userParts = [];
        if (isset($dto->context['user_id'])) {
            $userParts[] = "User ID: {$dto->context['user_id']}";
        }
        if (isset($dto->context['user_name']) || isset($dto->context['username'])) {
            $userName    = $dto->context['user_name'] ?? $dto->context['username'];
            $userParts[] = "User: {$userName}";
        }
        if (isset($dto->context['user_roles']) && is_array($dto->context['user_roles'])) {
            $userParts[] = "Roles: " . implode(', ', $dto->context['user_roles']);
        }
        if (!empty($userParts)) {
            $parts[] = implode(' | ', $userParts);
        }

        // Exception details with stack trace hint
        if (isset($dto->context['exception_class'])) {
            $exceptionParts = ["Exception: {$dto->context['exception_class']}"];

            if (isset($dto->context['exception_message'])) {
                $exceptionParts[] = "Message: {$dto->context['exception_message']}";
            }

            if (isset($dto->context['file']) && isset($dto->context['line'])) {
                $file             = basename($dto->context['file']);
                $exceptionParts[] = "Location: {$file}:{$dto->context['line']}";
            }

            if (isset($dto->context['stack_trace'])) {
                $exceptionParts[] = "Has stack trace";
            }

            if (isset($dto->context['previous_exception'])) {
                $exceptionParts[] = "Has previous exception";
            }

            $parts[] = implode(' | ', $exceptionParts);
        }

        // Database query context
        if (isset($dto->context['query'])) {
            $queryInfo = "Database Query";
            if (isset($dto->context['query_time'])) {
                $queryInfo .= " (Duration: {$dto->context['query_time']}ms)";
            }
            if (isset($dto->context['query_type'])) {
                $queryInfo .= " Type: {$dto->context['query_type']}";
            }
            $parts[] = $queryInfo;
        }

        // Performance metrics
        $perfParts = [];
        if (isset($dto->context['duration']) || isset($dto->context['execution_time'])) {
            $duration    = $dto->context['duration'] ?? $dto->context['execution_time'];
            $perfParts[] = "Duration: {$duration}ms";
        }
        if (isset($dto->context['memory_usage'])) {
            $perfParts[] = "Memory: {$dto->context['memory_usage']}";
        }
        if (isset($dto->context['cpu_usage'])) {
            $perfParts[] = "CPU: {$dto->context['cpu_usage']}%";
        }
        if (!empty($perfParts)) {
            $parts[] = "Performance: " . implode(' | ', $perfParts);
        }

        // Business domain context from enriched data
        foreach ($dto->enrichedData as $key => $value) {
            if (is_scalar($value) && $value !== '' && $value !== null) {
                $label   = ucwords(str_replace(['_', '-'], ' ', $key));
                $parts[] = "{$label}: {$value}";
            } elseif (is_array($value) && !empty($value)) {
                $label   = ucwords(str_replace(['_', '-'], ' ', $key));
                $parts[] = "{$label}: " . implode(', ', array_filter($value, 'is_scalar'));
            }
        }

        // Extra metadata (non-redundant)
        foreach ($dto->extra as $key => $value) {
            if (is_scalar($value) && $value !== '' && $value !== null && !isset($dto->context[$key])) {
                $label   = ucwords(str_replace(['_', '-'], ' ', $key));
                $parts[] = "{$label}: {$value}";
            }
        }

        // Join with separator optimized for semantic understanding
        return implode(' | ', array_filter($parts));
    }

    /**
     * Create metadata from LogDataDTO.
     *
     * @param LogDataDTO $dto
     * @return array
     */
    private static function createMetadataFromDTO(LogDataDTO $dto): array
    {
        $timestamp    = $dto->getTimestamp();
        $context      = $dto->context;
        $extra        = $dto->extra;
        $enrichedData = $dto->enrichedData;

        // Merge all context data
        $allContext = array_merge($context, $extra, $enrichedData);

        // Base metadata with time-based features
        $metadata = [
            'created_at' => new DateTimeImmutable()->format(DateTimeInterface::ATOM),
            'timestamp'  => $timestamp->format(DateTimeInterface::ATOM),
            'level'      => strtoupper($dto->level),
            'channel'    => $dto->channel,
            'message'    => $dto->message,

            // Time-based features for temporal analysis
            'hour'        => (int) $timestamp->format('H'),
            'day_of_week' => (int) $timestamp->format('N'),
            'day_name'    => $timestamp->format('l'),
            'is_weekend'  => in_array($timestamp->format('N'), [6, 7], true),
            'month'       => (int) $timestamp->format('m'),
            'year'        => (int) $timestamp->format('Y'),

            // Boolean flags for quick filtering
            'has_exception'        => isset($context['exception_class']),
            'has_stack_trace'      => isset($context['stack_trace']),
            'has_request_context'  => isset($context['request_id']) || isset($context['url']),
            'has_user_context'     => isset($context['user_id'])    || isset($context['username']),
            'has_performance_data' => isset($context['duration'])   || isset($context['memory_usage']),
            'has_database_query'   => isset($context['query']),
        ];

        // Merge with all context data
        return array_merge($metadata, $allContext);
    }
}
