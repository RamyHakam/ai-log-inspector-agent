<?php

namespace Hakam\AiLogInspector\Agent\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\StoreInterface;

#[AsTool(
    name: 'log_search',
    description: 'Search logs semantically for relevant context. Input: user query string.'
)]
class LogSearchTool
{
    private const RELEVANCE_THRESHOLD = 0.7;
    private const MAX_RESULTS = 10;

    public function __construct(
        private readonly StoreInterface $store,
        private readonly PlatformInterface $platform,
        private readonly Model $model
    ) {
    }

    public function __invoke(string $query): array
    {
        if (empty(trim($query))) {
            return [
                'success' => false,
                'message' => 'Query cannot be empty',
                'logs' => []
            ];
        }

        try {
            $results = $this->performSemanticSearch($query);
            return $this->formatResults($results);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
                'logs' => []
            ];
        }
    }

    private function performSemanticSearch(string $query): array
    {
        // Convert query text to vector using the platform
        $vectorResult = $this->platform->invoke($this->model, $query);
        $vectors = $vectorResult->asVectors();
        
        if (empty($vectors)) {
            throw new \RuntimeException('Failed to generate vector from query');
        }
        
        $queryVector = $vectors[0]; // Use the first vector

        // Use the vector store to perform semantic search
        $searchResults = $this->store->query($queryVector, ['maxItems' => self::MAX_RESULTS]);
        
        $filteredResults = [];
        foreach ($searchResults as $result) {
            if ($result instanceof VectorDocument && 
                ($result->score === null || $result->score >= self::RELEVANCE_THRESHOLD)) {
                $filteredResults[] = $result;
            }
        }

        return $filteredResults;
    }

    private function formatResults(array $results): array
    {
        if (empty($results)) {
            return [
                'success' => false,
                'reason' => 'No relevant log entries found to determine the cause of the issue.',
                'evidence_logs' => []
            ];
        }

        // Extract log content and metadata for analysis
        $logContents = [];
        $evidenceLogs = [];
        
        foreach ($results as $result) {
            if ($result instanceof VectorDocument) {
                $metadata = $result->metadata;
                $content = $metadata['content'] ?? 'No content available';
                $logId = $metadata['log_id'] ?? $result->id->toString();
                
                $logContents[] = $content;
                $evidenceLogs[] = [
                    'id' => $logId,
                    'content' => $content,
                    'timestamp' => $metadata['timestamp'] ?? null,
                    'level' => $metadata['level'] ?? 'unknown',
                    'source' => $metadata['source'] ?? 'unknown',
                    'tags' => $metadata['tags'] ?? []
                ];
            }
        }

        // Analyze logs to determine the reason
        $reason = $this->analyzeLogs($logContents);

        return [
            'success' => true,
            'reason' => $reason,
            'evidence_logs' => $evidenceLogs
        ];
    }

    private function analyzeLogs(array $logContents): string
    {
        if (empty($logContents)) {
            return 'No relevant logs found to determine the cause.';
        }

        // Combine logs for analysis
        $combinedLogs = implode("\n", $logContents);
        
        try {
            // Use the platform to analyze the logs and extract the reason
            $analysisPrompt = "Analyze these log entries and provide a concise explanation of what caused the error or issue. Focus on the root cause, not just listing what happened:\n\n" . $combinedLogs;
            
            $analysisResult = $this->platform->invoke($this->model, $analysisPrompt);
            $analysis = $analysisResult->asText();
            
            return trim($analysis);
            
        } catch (\Exception) {
            // Fallback to
            // basic pattern matching if AI analysis fails
            return $this->extractReasonFromLogs($combinedLogs);
        }
    }

    private function extractReasonFromLogs(string $logs): string
    {
        // Basic pattern matching for common error patterns
        $patterns = [
            '/database.*connection.*failed/i' => 'Database connection failure',
            '/timeout/i' => 'Request timeout occurred',
            '/authentication.*failed/i' => 'Authentication failure',
            '/permission.*denied/i' => 'Insufficient permissions',
            '/out of memory/i' => 'System ran out of memory',
            '/disk.*full/i' => 'Disk space exhausted',
            '/invalid.*request/i' => 'Invalid request format or parameters',
            '/service.*unavailable/i' => 'External service unavailable',
            '/500.*internal.*server.*error/i' => 'Internal server error occurred'
        ];

        foreach ($patterns as $pattern => $reason) {
            if (preg_match($pattern, $logs)) {
                return $reason;
            }
        }

        return 'Unable to determine the specific cause from the available logs.';
    }
}