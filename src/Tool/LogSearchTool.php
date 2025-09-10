<?php

namespace Hakam\AiLogInspector\Tool;

use Hakam\AiLogInspector\Document\TextDocumentFactory;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Platform\Vector\Vector;

#[AsTool(
    name: 'log_search',
    description: 'Search logs for relevant entries. REQUIRED: Provide a query string parameter with keywords to search for (e.g. "payment errors", "database timeouts", "security threats"). Supports both semantic and keyword-based search depending on platform capabilities.'
)]
class LogSearchTool implements LogInspectorToolInterface
{
    private const RELEVANCE_THRESHOLD = 0.7;
    private const MAX_RESULTS = 15;
    private bool $supportsVectorization = true;

    public function __construct(
        private readonly VectorLogStoreInterface        $store,
        private readonly LogDocumentVectorizerInterface $vectorizer,
        private readonly LogDocumentPlatformInterface   $platform,
    )
    {
        // Defer vectorization capability test until first use to avoid constructor failures
        // $this->supportsVectorization will be tested on first search attempt
    }

    public function __invoke(string $query = ''): array
    {
        $query = trim($query);
        if (empty($query)) {
            return [
                'success' => false,
                'message' => 'Query parameter is required and cannot be empty. Please provide a search term to find relevant log entries.',
                'logs' => [],
                'examples' => ['payment errors', 'database timeouts', 'security threats', 'application exceptions'],
                'search_method' => 'none'
            ];
        }

        try {
            // Test vectorization capability on first use
            if ($this->supportsVectorization && $this->testVectorizationSupport()) {
                $results = $this->performSemanticSearch($query);
            } else {
                $this->supportsVectorization = false; // Cache the result
                $results = $this->performKeywordSearch($query);
            }
            return $this->formatResults($results, $query);
        } catch (\Exception $e) {
            // If semantic search fails, fallback to keyword search
            try {
                $results = $this->performKeywordSearch($query);
                return $this->formatResults($results, $query);
            } catch (\Exception $fallbackException) {
                return [
                    'success' => false,
                    'message' => 'Search failed: ' . $e->getMessage() . ' (Fallback also failed: ' . $fallbackException->getMessage() . ')',
                    'logs' => []
                ];
            }
        }
    }

    private function performSemanticSearch(string $query): array
    {

        $queryDocument = TextDocumentFactory::createFromString($query);
        $vectorDocuments = $this->vectorizer->vectorizeLogTextDocuments([$queryDocument])[0];
        $queryVector = $vectorDocuments->vector;

        $searchResults = $this->store->queryForVector($queryVector, ['maxItems' => self::MAX_RESULTS]);

        $filteredResults = [];
        foreach ($searchResults as $result) {
            if ($result instanceof VectorDocument &&
                ($result->score === null || $result->score >= self::RELEVANCE_THRESHOLD)) {
                $filteredResults[] = $result;
            }
        }

        return $filteredResults;
    }

    private function formatResults(array $results, string $query = ''): array
    {
        if (empty($results)) {
            $searchMethod = $this->supportsVectorization ? 'semantic' : 'keyword-based';
            return [
                'success' => false,
                'reason' => "No relevant log entries found matching your query using {$searchMethod} search. Try different keywords or check if the logs have been loaded correctly.",
                'evidence_logs' => [],
                'search_method' => $searchMethod,
                'query' => $query
            ];
        }

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

        $searchMethod = $this->supportsVectorization ? 'semantic' : 'keyword-based';
        return [
            'success' => true,
            'reason' => $reason,
            'evidence_logs' => $evidenceLogs,
            'search_method' => $searchMethod,
            'log_count' => count($evidenceLogs),
            'query' => $query
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

            $analysisResult = $this->platform->__invoke($analysisPrompt);
            $analysis = $analysisResult->getContent();

            return trim($analysis);

        } catch (\Exception $exception) {
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
            '/payment/i' => 'Payment gateway timeout',
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

    /**
     * Test if the current platform supports vectorization
     */
    private function testVectorizationSupport(): bool
    {
        try {
            // Try to vectorize a simple test document
            $testDocument = TextDocumentFactory::createFromString('test');
            $result = $this->vectorizer->vectorizeLogTextDocuments([$testDocument]);
            // Check if we got a valid result
            return !empty($result) && isset($result[0]) && $result[0] instanceof VectorDocument;
        } catch (\Throwable $e) {
            // If vectorization fails for any reason, the platform doesn't support it
            // This includes TypeError, ArgumentException, etc.
            return false;
        }
    }

    /**
     * Perform keyword-based search when vectorization is not available
     */
    private function performKeywordSearch(string $query): array
    {
        // Get all documents from the store using a neutral vector query
        // This is a workaround to get all stored documents
        $neutralVector = new Vector([0.5, 0.5, 0.5, 0.5, 0.5]); // Neutral vector
        $allResults = $this->store->queryForVector($neutralVector, ['maxItems' => 1000]);
        
        $matchingResults = [];
        $queryLower = strtolower(trim($query));
        $queryWords = array_filter(explode(' ', $queryLower), fn($word) => strlen($word) > 2);
        
        foreach ($allResults as $result) {
            if ($result instanceof VectorDocument) {
                $metadata = $result->metadata;
                $content = strtolower($metadata['content'] ?? '');
                $category = strtolower($metadata['category'] ?? '');
                $level = strtolower($metadata['level'] ?? '');
                $tags = array_map('strtolower', $metadata['tags'] ?? []);
                
                $score = 0;
                
                // Direct query match
                if (str_contains($content, $queryLower)) {
                    $score += 10;
                }
                
                // Category match
                if (str_contains($queryLower, $category) || str_contains($category, $queryLower)) {
                    $score += 8;
                }
                
                // Level match (error, warning, etc.)
                if (str_contains($queryLower, $level)) {
                    $score += 5;
                }
                
                // Tag matches
                foreach ($tags as $tag) {
                    if (str_contains($queryLower, $tag) || str_contains($tag, $queryLower)) {
                        $score += 6;
                    }
                }
                
                // Individual word matches
                foreach ($queryWords as $word) {
                    if (str_contains($content, $word)) {
                        $score += 2;
                    }
                }
                
                // Semantic keyword matching
                $semanticMatches = $this->getSemanticMatches($queryLower, $content);
                $score += $semanticMatches * 3;
                
                // Only include results with some relevance
                if ($score > 0) {
                    // Add artificial score to match VectorDocument interface expectations
                    $result->score = $score / 20.0; // Normalize to 0-1 range
                    $matchingResults[] = $result;
                }
            }
        }
        
        // Sort by relevance score (descending)
        usort($matchingResults, fn($a, $b) => ($b->score ?? 0) <=> ($a->score ?? 0));
        
        // Apply relevance threshold and limit results
        $filteredResults = array_slice(
            array_filter($matchingResults, fn($result) => ($result->score ?? 0) >= 0.1),
            0,
            self::MAX_RESULTS
        );
        
        return $filteredResults;
    }

    /**
     * Get semantic keyword matches for better keyword-based search
     */
    private function getSemanticMatches(string $query, string $content): int
    {
        $semanticMap = [
            // Payment related
            'payment' => ['stripe', 'paypal', 'gateway', 'transaction', 'checkout', 'billing'],
            'error' => ['exception', 'failure', 'problem', 'issue', 'bug'],
            'timeout' => ['slow', 'delay', 'hang', 'freeze'],
            
            // Database related
            'database' => ['db', 'sql', 'mysql', 'postgres', 'connection', 'query'],
            'connection' => ['connect', 'link', 'network', 'socket'],
            
            // Security related
            'security' => ['auth', 'authentication', 'login', 'breach', 'attack'],
            'attack' => ['hack', 'intrusion', 'malicious', 'threat'],
            
            // Performance related
            'performance' => ['slow', 'fast', 'speed', 'optimization', 'memory', 'cpu'],
            'memory' => ['ram', 'heap', 'allocation', 'leak'],
        ];
        
        $matches = 0;
        foreach ($semanticMap as $key => $synonyms) {
            if (str_contains($query, $key)) {
                foreach ($synonyms as $synonym) {
                    if (str_contains($content, $synonym)) {
                        $matches++;
                    }
                }
            }
        }
        
        return $matches;
    }
}
