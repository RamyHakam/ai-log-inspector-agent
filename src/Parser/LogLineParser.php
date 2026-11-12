<?php

namespace Hakam\AiLogInspector\Parser;

/**
 * Parses one raw log line (Monolog/Symfony/Laravel, PHP error_log or "[ts] LEVEL: msg")
 * into the metadata the log inspector tools read: content, log_id, level, timestamp, channel, context.
 */
final class LogLineParser
{
    private const MONOLOG = '/^\[(?<ts>[^\]]+)\]\s+(?<channel>[\w-]+)\.(?<level>[A-Z]+):\s*(?<msg>.*)$/';
    private const LEVEL_ONLY = '/^\[(?<ts>[^\]]+)\]\s+(?<level>DEBUG|INFO|NOTICE|WARN|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY|FATAL):\s*(?<msg>.*)$/i';
    private const PHP_ERROR = '/^\[(?<ts>[^\]]+)\]\s+PHP (?<level>Fatal error|Warning|Notice|Deprecated|Parse error):\s*(?<msg>.*)$/';

    /**
     * @return array<string, mixed>
     */
    public function parse(string $line, string $source = 'log', int $lineNumber = 0): array
    {
        $line = trim($line);
        $timestamp = null;
        $channel = 'app';
        $level = 'info';
        $message = $line;

        if (preg_match(self::MONOLOG, $line, $m)) {
            [$timestamp, $channel, $level, $message] = [$m['ts'], $m['channel'], $m['level'], $m['msg']];
        } elseif (preg_match(self::LEVEL_ONLY, $line, $m)) {
            [$timestamp, $level, $message] = [$m['ts'], $m['level'], $m['msg']];
        } elseif (preg_match(self::PHP_ERROR, $line, $m)) {
            [$timestamp, $message] = [$m['ts'], $m['msg']];
            $level = str_contains($m['level'], 'error') ? 'critical' : 'warning';
            $channel = 'php';
        }

        $context = $this->extractContext($message);

        $metadata = [
            'log_id' => \sprintf('%s_%04d', preg_replace('/\W+/', '_', pathinfo($source, \PATHINFO_FILENAME)), $lineNumber),
            'content' => $line,
            'message' => $context['message'],
            'level' => $this->normalizeLevel($level),
            'channel' => $channel,
            'timestamp' => $this->normalizeTimestamp($timestamp),
            'source' => $source,
            'line' => $lineNumber,
        ];

        foreach (['request_id', 'trace_id', 'session_id', 'span_id'] as $key) {
            if (isset($context['context'][$key]) && \is_scalar($context['context'][$key])) {
                $metadata[$key] = (string) $context['context'][$key];
            }
        }

        $metadata['tags'] = array_values(array_filter([$metadata['level'], $channel, $context['context']['service'] ?? null], 'is_string'));

        return $metadata;
    }

    /**
     * @return array{message: string, context: array<string, mixed>}
     */
    private function extractContext(string $message): array
    {
        $start = strpos($message, ' {');
        if (false === $start) {
            return ['message' => $message, 'context' => []];
        }

        // Try the longest JSON object first, then shrink to drop a trailing [extra] block.
        $candidate = substr($message, $start + 1);
        for ($end = strrpos($candidate, '}'); false !== $end && $end > 0; $end = strrpos(substr($candidate, 0, $end), '}')) {
            $decoded = json_decode(substr($candidate, 0, $end + 1), true);
            if (\is_array($decoded)) {
                return ['message' => trim(substr($message, 0, $start)), 'context' => $decoded];
            }
        }

        return ['message' => $message, 'context' => []];
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower($level);

        return match ($level) {
            'warn' => 'warning',
            'fatal' => 'critical',
            default => $level,
        };
    }

    private function normalizeTimestamp(?string $timestamp): ?string
    {
        if (null === $timestamp) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($timestamp))->format(\DateTimeInterface::ATOM);
        } catch (\Exception) {
            return $timestamp;
        }
    }
}
