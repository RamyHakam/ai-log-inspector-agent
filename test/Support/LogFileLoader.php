<?php

namespace Hakam\AiLogInspector\Test\Support;

use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\Uid\Uuid;

/**
 * LogFileLoader - Loads realistic PHP application logs from fixture files
 */
class LogFileLoader
{
    private array $categoryVectors = [
        'payment' => [0.9, 0.1, 0.2, 0.8, 0.3],
        'database' => [0.2, 0.3, 0.9, 0.1, 0.5],
        'security' => [0.1, 0.9, 0.0, 0.3, 0.7],
        'application' => [0.4, 0.6, 0.3, 0.7, 0.8],
        'performance' => [0.7, 0.4, 0.5, 0.2, 0.9],
    ];

    private array $logCategories = [
        'payment-errors.log' => 'payment',
        'database-errors.log' => 'database', 
        'security-errors.log' => 'security',
        'application-errors.log' => 'application',
        'performance-errors.log' => 'performance',
    ];

    public function loadLogsIntoStore(InMemoryStore $store, ?array $categories = null): array
    {
        $loadedLogs = [];
        $fixturesPath = __DIR__ . '/../fixtures/logs/';
        
        $categoriesToLoad = $categories ?? array_values($this->logCategories);
        
        foreach ($categoriesToLoad as $category) {
            $filename = $this->getCategoryFilename($category);
            if (!$filename) {
                continue;
            }
            
            $filePath = $fixturesPath . $filename;
            if (!file_exists($filePath)) {
                throw new \RuntimeException("Log fixture file not found: {$filePath}");
            }
            
            $logs = $this->parseLogFile($filePath, $category);
            foreach ($logs as $logData) {
                $vector = new Vector($logData['vector']);
                $metadata = new Metadata($logData['metadata']);
                $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
                $store->add($document);
                
                $loadedLogs[] = $logData;
            }
        }
        
        return $loadedLogs;
    }

    public function getLogsByCategory(string $category): array
    {
        $filename = $this->getCategoryFilename($category);
        if (!$filename) {
            return [];
        }
        
        $fixturesPath = __DIR__ . '/../fixtures/logs/';
        $filePath = $fixturesPath . $filename;
        
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Log fixture file not found: {$filePath}");
        }
        
        return $this->parseLogFile($filePath, $category);
    }

    public function getAllAvailableCategories(): array
    {
        return array_values($this->logCategories);
    }

    public function getLogStats(): array
    {
        $stats = [];
        $fixturesPath = __DIR__ . '/../fixtures/logs/';
        
        foreach ($this->logCategories as $filename => $category) {
            $filePath = $fixturesPath . $filename;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $logCount = substr_count($content, "\n") + 1;
                
                $stats[$category] = [
                    'filename' => $filename,
                    'logCount' => $logCount,
                    'vector' => $this->categoryVectors[$category],
                ];
            }
        }
        
        return $stats;
    }

    private function getCategoryFilename(string $category): ?string
    {
        foreach ($this->logCategories as $filename => $cat) {
            if ($cat === $category) {
                return $filename;
            }
        }
        return null;
    }

    private function parseLogFile(string $filePath, string $category): array
    {
        $content = file_get_contents($filePath);
        $lines = array_filter(explode("\n", $content));
        $logs = [];
        $logId = 1;
        
        foreach ($lines as $line) {
            $parsedLog = $this->parseLogEntry($line, $category, $logId);
            if ($parsedLog) {
                $logs[] = $parsedLog;
                $logId++;
            }
        }
        
        return $logs;
    }

    private function parseLogEntry(string $logLine, string $category, int $logId): ?array
    {
        // Parse Monolog format: [timestamp] level.LEVEL: message {context} [extra]
        $pattern = '/^\[(.+?)\]\s+(.+?)\.(\w+):\s+(.+?)(?:\s+(\{.+?\}))?(?:\s+(\[.+?\]))?$/';
        
        if (!preg_match($pattern, $logLine, $matches)) {
            return null;
        }
        
        $timestamp = $matches[1] ?? '';
        $channel = $matches[2] ?? 'app';
        $level = strtolower($matches[3] ?? 'info');
        $message = $matches[4] ?? '';
        $contextJson = $matches[5] ?? '{}';
        $extraJson = $matches[6] ?? '[]';
        
        // Parse context and extra data
        $context = json_decode($contextJson, true) ?? [];
        $extra = json_decode($extraJson, true) ?? [];
        
        // Extract exception class if present
        $exceptionClass = $this->extractExceptionClass($message);
        
        // Determine tags based on content analysis
        $tags = $this->generateTags($category, $message, $context, $level);
        
        return [
            'content' => $logLine,
            'metadata' => [
                'log_id' => sprintf('%s_%03d', $category, $logId),
                'content' => $logLine,
                'timestamp' => $this->normalizeTimestamp($timestamp),
                'level' => $level,
                'channel' => $channel,
                'message' => $message,
                'category' => $category,
                'exception_class' => $exceptionClass,
                'context' => $context,
                'extra' => $extra,
                'tags' => $tags,
            ],
            'vector' => $this->categoryVectors[$category] ?? [0.5, 0.5, 0.5, 0.5, 0.5],
        ];
    }

    private function extractExceptionClass(string $message): ?string
    {
        // Extract exception class name from the message
        if (preg_match('/^([A-Za-z\\\\]+(?:Exception|Error)):\s*/', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function generateTags(string $category, string $message, array $context, string $level): array
    {
        $tags = [$category, $level];
        
        // Add specific tags based on category
        switch ($category) {
            case 'payment':
                if (str_contains($message, 'stripe') || str_contains($message, 'Stripe')) {
                    $tags[] = 'stripe';
                }
                if (str_contains($message, 'paypal') || str_contains($message, 'PayPal')) {
                    $tags[] = 'paypal';
                }
                if (str_contains($message, 'timeout')) {
                    $tags[] = 'timeout';
                }
                if (str_contains($message, 'declined') || str_contains($message, 'CardException')) {
                    $tags[] = 'card-declined';
                }
                break;
                
            case 'database':
                if (str_contains($message, 'connection') || str_contains($message, 'Connection')) {
                    $tags[] = 'connection';
                }
                if (str_contains($message, 'Doctrine')) {
                    $tags[] = 'doctrine';
                }
                if (str_contains($message, 'deadlock') || str_contains($message, 'Deadlock')) {
                    $tags[] = 'deadlock';
                }
                if (str_contains($message, 'timeout')) {
                    $tags[] = 'timeout';
                }
                break;
                
            case 'security':
                if (str_contains($message, 'authentication') || str_contains($message, 'Authentication')) {
                    $tags[] = 'authentication';
                }
                if (str_contains($message, 'brute force') || str_contains($message, 'Brute force')) {
                    $tags[] = 'brute-force';
                }
                if (str_contains($message, 'injection') || str_contains($message, 'SQL injection')) {
                    $tags[] = 'injection';
                }
                if (str_contains($message, 'XSS') || str_contains($message, 'xss')) {
                    $tags[] = 'xss';
                }
                break;
                
            case 'application':
                if (str_contains($message, 'NotFound') || str_contains($message, '404')) {
                    $tags[] = '404';
                }
                if (str_contains($message, 'OutOfMemory') || str_contains($message, 'memory')) {
                    $tags[] = 'memory';
                }
                if (str_contains($message, 'TypeError') || str_contains($message, 'type')) {
                    $tags[] = 'type-error';
                }
                break;
                
            case 'performance':
                if (str_contains($message, 'CPU') || str_contains($message, 'cpu')) {
                    $tags[] = 'cpu';
                }
                if (str_contains($message, 'memory') || str_contains($message, 'Memory')) {
                    $tags[] = 'memory';
                }
                if (str_contains($message, 'timeout') || str_contains($message, 'slow')) {
                    $tags[] = 'slow';
                }
                break;
        }
        
        return array_unique($tags);
    }

    private function normalizeTimestamp(string $timestamp): string
    {
        try {
            $dt = new \DateTime($timestamp);
            return $dt->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }
}
