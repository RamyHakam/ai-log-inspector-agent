<?php

namespace Hakam\AiLogInspector\Factory;

use DateTimeImmutable;
use DateTimeInterface;
use Hakam\AiLogInspector\Document\VirtualLogDocument;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\Component\Uid\Uuid;

final class TextDocumentFactory
{
    public static function createFromVirtualDocument( VirtualLogDocument $document ): TextDocument
    {
        return new TextDocument(
            id: Uuid::v4(),
            content: self::createSemanticContent($document),
            metadata: self::createMetadata( $document)
        );
    }

    private static function createSemanticContent(VirtualLogDocument $document): string
    {
        $parts = [];
        $parts[] = "Log Message: " . $document->message;
        $parts[] = "Severity Level: " . $document->level;
        $parts[] = "Timestamp: " . $document->timestamp->format('Y-m-d H:i:s T');
        $parts[] = "Day: " . $document->timestamp->format('l');
        $parts[] = "Hour: " . $document->timestamp->format('H:i');
        $parts[] = "Log Channel: " . $document->channel;

        if (isset($document->context['request_id'])) {
            $parts[] = "Request ID: " . $document->context['request_id'];
        }
        if (isset($document->context['url'])) {
            $parts[] = "URL: " . $document->context['url'];
        }
        if (isset($document->context['method'])) {
            $parts[] = "HTTP Method: " . $document->context['method'];
        }
        if (isset($document->context['route'])) {
            $parts[] = "Route: " . $document->context['route'];
        }

        if (isset($document->context['user_id'])) {
            $parts[] = "User ID: " . $document->context['user_id'];
        }
        if (isset($document->context['user_roles'])) {
            $parts[] = "User Roles: " . implode(', ', (array)$document->context['user_roles']);
        }

        if (isset($document->context['exception_class'])) {
            $parts[] = "Exception Type: " . $document->context['exception_class'];
        }
        if (isset($document->context['file']) && isset($document->context['line'])) {
            $parts[] = "Location: " . basename($document->context['file']) . ':' . $document->context['line'];
        }

        foreach ($document->enrichedData as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = ucwords(str_replace('_', ' ', $key)) . ": " . $value;
            }
        }

        return implode(' | ', $parts);
    }

    private static function createMetadata(VirtualLogDocument $document): Metadata
    {
        $allContext = array_merge($document->context, $document->extra, $document->enrichedData);

        return new Metadata(
            array_merge([
            'created_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'level' => $document->level,
            'channel' => $document->channel,
            'timestamp' => $document->timestamp->format(DateTimeInterface::ISO8601),
            'hour' => (int) $document->timestamp->format('H'),
            'day_of_week' => (int) $document->timestamp->format('N'),
            'day_name' => $document->timestamp->format('l'),
            'is_weekend' => in_array($document->timestamp->format('N'), [6, 7]),

            'has_exception' => isset($document->context['exception_class']),
            'has_stack_trace' => isset($document->context['stack_trace']),
            'has_request_context' => isset($document->context['request_id']),
            'has_user_context' => isset($document->context['user_id']),
            'message' => $document->message,
        ], $allContext)
        );
    }
}