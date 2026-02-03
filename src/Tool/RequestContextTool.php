<?php

namespace Hakam\AiLogInspector\Tool;

use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Retriever\LogRetrieverInterface;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Uid\Uuid;

#[AsTool(
    name: 'request_context',
    description: 'Fetch all logs related to a specific request_id, trace_id, or session_id for complete request lifecycle tracking. REQUIRED: Provide an identifier parameter (e.g. "req_12345", "trace-abc-def", "session_xyz"). Perfect for debugging distributed systems and microservices.'
)]
class RequestContextTool implements LogInspectorToolInterface
{
    private const RELEVANCE_THRESHOLD = 0.3; // Lower threshold for identifier matching
    private const MAX_RESULTS = 50; // Higher limit for request tracing
    private bool $supportsVectorization = true;

    public function __construct(
        private readonly VectorLogStoreInterface $store,
        private readonly LogRetrieverInterface $retriever,
        private readonly LogDocumentPlatformInterface $platform,
    ) {
    }

    public function __invoke(string $identifier = ''): array
    {
        $identifier = trim($identifier);
        if (empty($identifier)) {
            return [
                'success' => false,
                'message' => 'Request identifier is required. Please provide a request_id, trace_id, or session_id to track.',
                'logs' => [],
                'examples' => [
                    'req_12345',
                    'trace-abc-def-123',
                    'session_xyz789',
                    'order-uuid-456',
                    'user-session-789',
                ],
                'search_method' => 'none',
            ];
        }

        try {
            // Try vector-based search via retriever first, fall back to keyword on failure
            if ($this->supportsVectorization) {
                $results = $this->performVectorBasedSearch($identifier);
            } else {
                $results = $this->performKeywordBasedSearch($identifier);
            }

            if (empty($results)) {
                return [
                    'success' => false,
                    'reason' => "No logs found containing identifier '{$identifier}'. This could mean the request hasn't been logged, the identifier format is different, or logs haven't been indexed yet.",
                    'evidence_logs' => [],
                    'search_method' => $this->supportsVectorization ? 'vector-based' : 'keyword-based',
                    'identifier' => $identifier,
                    'suggestions' => [
                        'Check if the identifier format is correct',
                        'Verify logs have been properly ingested',
                        'Try searching for partial identifiers',
                        'Check if the request occurred within the indexed time range',
                    ],
                ];
            }

            return $this->formatRequestContext($results, $identifier);
        } catch (\Throwable $e) {
            try {
                $this->supportsVectorization = false;
                $results = $this->performKeywordBasedSearch($identifier);

                return empty($results)
                    ? $this->getNoResultsResponse($identifier, 'keyword-based (fallback)')
                    : $this->formatRequestContext($results, $identifier);
            } catch (\Throwable $fallbackException) {
                return [
                    'success' => false,
                    'message' => 'Request context search failed: '.$e->getMessage().' (Fallback also failed: '.$fallbackException->getMessage().')',
                    'logs' => [],
                    'identifier' => $identifier,
                ];
            }
        }
    }

    private function performVectorBasedSearch(string $identifier): array
    {
        $searchQuery = $this->buildSearchQuery($identifier);

        $searchResults = $this->retriever->retrieve($searchQuery, ['maxItems' => 200]);

        $maxDistance = 1.0 - self::RELEVANCE_THRESHOLD;

        $filteredResults = [];
        $timestamps = [];
        $identifierLower = strtolower($identifier);

        foreach ($searchResults as $result) {
            if ($result instanceof VectorDocument) {
                $metadata = $result->metadata;
                $content = strtolower($metadata['content'] ?? '');

                if (str_contains($content, $identifierLower)) {
                    if (null === $result->score || $result->score <= $maxDistance) {
                        $resultId = $result->id instanceof Uuid
                            ? $result->id->toString()
                            : (string) $result->id;
                        $timestamps[$resultId] = $this->extractTimestamp($metadata);
                        $filteredResults[] = $result;
                    }
                }
            }
        }

        // Sort by timestamp (chronological order)
        usort($filteredResults, function ($a, $b) use ($timestamps) {
            $idA = $a->id instanceof Uuid ? $a->id->toString() : (string) $a->id;
            $idB = $b->id instanceof Uuid ? $b->id->toString() : (string) $b->id;
            $timeA = $timestamps[$idA] ?? 0;
            $timeB = $timestamps[$idB] ?? 0;

            return $timeA <=> $timeB;
        });

        return array_slice($filteredResults, 0, self::MAX_RESULTS);
    }

    private function performKeywordBasedSearch(string $identifier): array
    {
        $neutralVector = new Vector(array_fill(0, 5, 0.5));
        $allResults = $this->store->queryForVector($neutralVector, ['maxItems' => 2000]);

        $matchingResults = [];
        $timestamps = [];
        $identifierLower = strtolower($identifier);

        foreach ($allResults as $result) {
            if ($result instanceof VectorDocument) {
                $metadata = $result->metadata;
                $content = strtolower($metadata['content'] ?? '');

                if (str_contains($content, $identifierLower)) {
                    $score = $this->calculateIdentifierScore($content, $identifierLower);
                    $scoredResult = $result->withScore($score);

                    $resultId = $scoredResult->id instanceof Uuid
                        ? $scoredResult->id->toString()
                        : (string) $scoredResult->id;
                    $timestamps[$resultId] = $this->extractTimestamp($metadata);
                    $matchingResults[] = $scoredResult;
                }
            }
        }

        // Sort by timestamp (chronological order)
        usort($matchingResults, function ($a, $b) use ($timestamps) {
            $idA = $a->id instanceof Uuid ? $a->id->toString() : (string) $a->id;
            $idB = $b->id instanceof Uuid ? $b->id->toString() : (string) $b->id;
            $timeA = $timestamps[$idA] ?? 0;
            $timeB = $timestamps[$idB] ?? 0;

            return $timeA <=> $timeB;
        });

        return array_slice($matchingResults, 0, self::MAX_RESULTS);
    }

    private function buildSearchQuery(string $identifier): string
    {
        $contextTerms = [
            'request trace',
            'request lifecycle',
            'transaction flow',
            'request processing',
            'request context',
            'trace logs',
            'request debugging',
        ];

        $additionalContext = [];
        $identifierLower = strtolower($identifier);

        if (str_contains($identifierLower, 'req') || str_contains($identifierLower, 'request')) {
            $additionalContext[] = 'HTTP request processing';
        }
        if (str_contains($identifierLower, 'trace')) {
            $additionalContext[] = 'distributed tracing';
        }
        if (str_contains($identifierLower, 'session')) {
            $additionalContext[] = 'user session activity';
        }
        if (str_contains($identifierLower, 'order') || str_contains($identifierLower, 'transaction')) {
            $additionalContext[] = 'business transaction processing';
        }
        if (str_contains($identifierLower, 'user')) {
            $additionalContext[] = 'user activity tracking';
        }

        $query = $identifier.' '.implode(' ', array_merge($contextTerms, $additionalContext));

        return $query;
    }

    private function calculateIdentifierScore(string $content, string $identifier): float
    {
        $score = 0.0;

        $occurrences = substr_count($content, $identifier);
        $score += $occurrences * 0.3;

        $position = strpos($content, $identifier);
        if (false !== $position) {
            $relativePosition = $position / strlen($content);
            $score += (1.0 - $relativePosition) * 0.2;
        }

        if (preg_match('/\b'.preg_quote($identifier, '/').'\b/', $content)) {
            $score += 0.3;
        }

        if (preg_match('/(?:request_id|trace_id|session_id|transaction_id)[:\s=]'.preg_quote($identifier, '/').'/i', $content)) {
            $score += 0.4;
        }

        return min($score, 1.0);
    }

    private function extractTimestamp($metadata): int
    {
        if (isset($metadata['timestamp'])) {
            if (is_numeric($metadata['timestamp'])) {
                return (int) $metadata['timestamp'];
            }

            $time = strtotime($metadata['timestamp']);
            if (false !== $time) {
                return $time;
            }
        }

        $content = $metadata['content'] ?? '';

        $patterns = [
            '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)\]/',
            '/(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)/',
            '/\[(\d{2}\/\w{3}\/\d{4}:\d{2}:\d{2}:\d{2}[+-]\d{4})\]/', // Apache format
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $time = strtotime($matches[1]);
                if (false !== $time) {
                    return $time;
                }
            }
        }

        return 0;
    }

    private function getNoResultsResponse(string $identifier, string $method): array
    {
        return [
            'success' => false,
            'reason' => "No logs found containing identifier '{$identifier}' using {$method} search.",
            'evidence_logs' => [],
            'search_method' => $method,
            'identifier' => $identifier,
            'suggestions' => [
                'Check if the identifier format is correct',
                'Verify logs have been properly ingested',
                'Try searching for partial identifiers',
                'Check if the request occurred within the indexed time range',
            ],
        ];
    }

    private function formatRequestContext(array $results, string $identifier): array
    {
        $evidenceLogs = [];
        $services = [];
        $timeline = [];
        $logLevels = [];

        foreach ($results as $result) {
            if ($result instanceof VectorDocument) {
                $metadata = $result->metadata;
                $content = $metadata['content'] ?? 'No content available';
                $logId = $metadata['log_id'] ?? $result->id->toString();
                $timestamp = $metadata['timestamp'] ?? null;
                $level = $metadata['level'] ?? 'unknown';
                $source = $metadata['source'] ?? 'unknown';
                $tags = $metadata['tags'] ?? [];

                // Build evidence logs
                $evidenceLogs[] = [
                    'id' => $logId,
                    'content' => $content,
                    'timestamp' => $timestamp,
                    'level' => $level,
                    'source' => $source,
                    'tags' => $tags,
                    'chronological_order' => count($evidenceLogs) + 1,
                ];

                if ('unknown' !== $source) {
                    $services[$source] = ($services[$source] ?? 0) + 1;
                }

                $logLevels[$level] = ($logLevels[$level] ?? 0) + 1;

                if ($timestamp) {
                    $timelineEvent = $this->extractTimelineEvent($content, $timestamp, $source);
                    if ($timelineEvent) {
                        $timeline[] = $timelineEvent;
                    }
                }
            }
        }

        // Analyze the request context
        $analysis = $this->analyzeRequestContext($evidenceLogs, $identifier);

        return [
            'success' => true,
            'reason' => $analysis['summary'],
            'root_cause' => $analysis['root_cause'],
            'evidence_logs' => $evidenceLogs,
            'request_timeline' => $timeline,
            'services_involved' => $services,
            'log_levels' => $logLevels,
            'total_logs' => count($evidenceLogs),
            'time_span' => $this->calculateTimeSpan($evidenceLogs),
            'search_method' => $this->supportsVectorization ? 'vector-based' : 'keyword-based',
            'identifier' => $identifier,
            'confidence' => $analysis['confidence'],
        ];
    }

    private function extractTimelineEvent(string $content, ?string $timestamp, string $source): ?array
    {
        if (!$timestamp) {
            return null;
        }

        $eventPatterns = [
            'started' => '/(?:started|begin|initiated|commenced)/i',
            'completed' => '/(?:completed|finished|success|done)/i',
            'failed' => '/(?:failed|error|exception|timeout|abort)/i',
            'warning' => '/(?:warning|warn|caution)/i',
            'timeout' => '/(?:timeout|timed out|expired)/i',
            'retry' => '/(?:retry|retrying|attempt)/i',
            'authentication' => '/(?:auth|login|authenticate)/i',
            'database' => '/(?:database|db|sql|query)/i',
            'payment' => '/(?:payment|transaction|charge)/i',
            'api_call' => '/(?:api|http|request|response)/i',
        ];

        $eventType = 'info';
        foreach ($eventPatterns as $type => $pattern) {
            if (preg_match($pattern, $content)) {
                $eventType = $type;

                break;
            }
        }

        return [
            'timestamp' => $timestamp,
            'event_type' => $eventType,
            'description' => $this->extractEventDescription($content),
            'source' => $source,
        ];
    }

    private function extractEventDescription(string $content): string
    {
        $content = trim($content);

        $cleanPatterns = [
            '/^\[[\d\-\s:TZ+]+\]\s*/',
            '/^\w+:\s*/',
            '/^\[\w+\]\s*/',
            '/^[\d\.\-\s:]+\s+\w+\s+/',
        ];

        foreach ($cleanPatterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        if (strlen($content) > 100) {
            $content = substr($content, 0, 97).'...';
        }

        return $content ?: 'Log event';
    }

    private function calculateTimeSpan(array $evidenceLogs): ?array
    {
        if (empty($evidenceLogs)) {
            return null;
        }

        $timestamps = [];
        foreach ($evidenceLogs as $log) {
            if ($log['timestamp']) {
                $time = strtotime($log['timestamp']);
                if (false !== $time) {
                    $timestamps[] = $time;
                }
            }
        }

        if (empty($timestamps)) {
            return null;
        }

        sort($timestamps);
        $firstTime = reset($timestamps);
        $lastTime = end($timestamps);
        $duration = $lastTime - $firstTime;

        return [
            'start_time' => date('Y-m-d H:i:s', $firstTime),
            'end_time' => date('Y-m-d H:i:s', $lastTime),
            'duration_seconds' => $duration,
            'duration_human' => $this->humanReadableDuration($duration),
        ];
    }

    private function humanReadableDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60).'m '.($seconds % 60).'s';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;

            return $hours.'h '.$minutes.'m '.$secs.'s';
        }
    }

    private function analyzeRequestContext(array $evidenceLogs, string $identifier): array
    {
        if (empty($evidenceLogs)) {
            return [
                'summary' => 'No request context found',
                'root_cause' => 'Unable to locate any logs for the provided identifier',
                'confidence' => 'Low',
            ];
        }

        try {
            // Combine all log contents for AI analysis
            $logContents = array_map(fn ($log) => $log['content'], $evidenceLogs);
            $combinedLogs = implode("\n", $logContents);

            $analysisPrompt = "Analyze this request lifecycle trace for identifier '{$identifier}'. Focus on:\n\n".
                "1. What was this request trying to accomplish?\n".
                "2. Did it succeed or fail? If failed, what was the root cause?\n".
                "3. Which services were involved and how did they interact?\n".
                "4. What was the timeline of key events?\n".
                "5. Any performance issues or bottlenecks?\n\n".
                "Log entries (chronological order):\n{$combinedLogs}\n\n".
                'Provide a structured analysis with Summary, Root Cause (if applicable), and Confidence level.';

            $analysisResult = $this->platform->__invoke($analysisPrompt);
            $analysis = $analysisResult->getContent();

            // Parse the AI response to extract components
            return $this->parseAIAnalysis($analysis);
        } catch (\Exception $e) {
            // Fallback to pattern-based analysis
            return $this->performPatternBasedAnalysis($evidenceLogs);
        }
    }

    private function parseAIAnalysis(?string $analysis): array
    {
        if (null === $analysis || '' === trim($analysis)) {
            return [
                'summary' => 'Request context analysis completed',
                'root_cause' => 'Analysis provided in summary',
                'confidence' => 'Medium',
                'full_analysis' => 'No AI analysis available',
            ];
        }

        $summary = 'Request context analysis completed';
        $rootCause = 'Analysis provided in summary';
        $confidence = 'Medium';

        // Look for specific patterns in the response
        if (preg_match('/Summary:?\s*([^\n]+)/i', $analysis, $matches)) {
            $summary = trim($matches[1]);
        } elseif (preg_match('/^([^\n.]+)/', $analysis, $matches)) {
            $summary = trim($matches[1]);
        }

        if (preg_match('/Root Cause:?\s*([^\n]+)/i', $analysis, $matches)) {
            $rootCause = trim($matches[1]);
        }

        if (preg_match('/Confidence:?\s*(\w+)/i', $analysis, $matches)) {
            $confidence = ucfirst(strtolower(trim($matches[1])));
        }

        return [
            'summary' => $summary,
            'root_cause' => $rootCause,
            'confidence' => $confidence,
            'full_analysis' => $analysis,
        ];
    }

    private function performPatternBasedAnalysis(array $evidenceLogs): array
    {
        $errorCount = 0;
        $warningCount = 0;
        $successCount = 0;
        $commonIssues = [];

        foreach ($evidenceLogs as $log) {
            $level = strtolower($log['level']);
            $content = strtolower($log['content']);

            switch ($level) {
                case 'error':
                case 'fatal':
                    $errorCount++;

                    break;
                case 'warning':
                case 'warn':
                    $warningCount++;

                    break;
                case 'info':
                case 'success':
                    $successCount++;

                    break;
            }

            if (str_contains($content, 'timeout')) {
                $commonIssues['timeout'] = ($commonIssues['timeout'] ?? 0) + 1;
            }
            if (str_contains($content, 'failed') || str_contains($content, 'error')) {
                $commonIssues['failure'] = ($commonIssues['failure'] ?? 0) + 1;
            }
            if (str_contains($content, 'slow') || str_contains($content, 'performance')) {
                $commonIssues['performance'] = ($commonIssues['performance'] ?? 0) + 1;
            }
        }

        $totalLogs = count($evidenceLogs);
        $summary = "Request trace contains {$totalLogs} log entries";
        $rootCause = 'Normal request processing';
        $confidence = 'Medium';

        if ($errorCount > 0) {
            $summary .= " with {$errorCount} errors";
            $confidence = 'High';

            if (isset($commonIssues['timeout'])) {
                $rootCause = 'Request failed due to timeout issues';
            } elseif (isset($commonIssues['failure'])) {
                $rootCause = 'Request failed due to processing errors';
            } else {
                $rootCause = 'Request encountered errors during processing';
            }
        } elseif ($warningCount > 0) {
            $summary .= " with {$warningCount} warnings";
            $rootCause = 'Request completed with warnings';
        } else {
            $summary .= ' - appears successful';
            $rootCause = 'Request processed successfully';
            $confidence = 'High';
        }

        return [
            'summary' => $summary,
            'root_cause' => $rootCause,
            'confidence' => $confidence,
        ];
    }
}
