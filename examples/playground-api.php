<?php

/**
 * Playground API - Multi-Platform AI Log Inspector
 *
 * Supports: OpenAI, Anthropic, Ollama (local)
 * Features:
 * - Separate brain (chat) and embedding models
 * - File upload for custom log files
 * - LogFileIndexer for processing uploaded logs
 * - API keys are passed per-request and never stored on the server.
 *
 * Usage:
 *   cd /path/to/ai-log-inspector-agent
 *   php -S localhost:8080 examples/playground-api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Hakam\AiLogInspector\Chat\LogInspectorChatFactory;
use Hakam\AiLogInspector\Enum\PlatformEnum;
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;
use Hakam\AiLogInspector\Retriever\LogRetriever;
use Symfony\AI\Store\Bridge\Cache\Store as CacheStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Uid\Uuid;

// Configuration
$sessionDir = getenv('SESSION_DIR') ?: sys_get_temp_dir() . '/playground-sessions';
$uploadDir = getenv('UPLOAD_DIR') ?: sys_get_temp_dir() . '/playground-uploads';
$cacheDir = getenv('CACHE_DIR') ?: sys_get_temp_dir() . '/playground-cache';

// Only run as web server
if (php_sapi_name() !== 'cli' || isset($_SERVER['REQUEST_URI'])) {
    // Increase execution time for AI API calls
    set_time_limit(300);
    ini_set('max_execution_time', '300');
    ini_set('upload_max_filesize', '10M');
    ini_set('post_max_size', '12M');

    // CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Handle multipart form data for file upload
    $input = [];
    if ($path === '/upload' && $method === 'POST') {
        $input = $_POST;
    } else {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    try {
        switch (true) {
            case $method === 'GET' && $path === '/health':
                echo json_encode([
                    'status' => 'ok',
                    'storage' => 'in-memory',
                    'supported_platforms' => ['openai', 'anthropic', 'ollama'],
                    'message' => 'API key required per request (not stored)',
                    'features' => ['brain_model', 'embedding_model', 'file_upload'],
                ]);
                break;

            case $method === 'POST' && $path === '/init':
                // Initialize logs in store
                $sessionId = $input['session_id'] ?? 'default';
                $platform = $input['platform'] ?? 'openai';
                $brainModel = $input['brain_model'] ?? $input['model'] ?? 'gpt-4o-mini';
                $embeddingModel = $input['embedding_model'] ?? 'text-embedding-3-small';
                $apiKey = $input['api_key'] ?? '';
                $ollamaHost = $input['ollama_host'] ?? 'http://localhost:11434';

                // Validate based on platform
                if ($platform !== 'ollama' && empty($apiKey)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'API key is required']);
                    break;
                }

                if ($platform === 'ollama') {
                    // Check if Ollama is available
                    if (!isOllamaAvailable($ollamaHost)) {
                        http_response_code(400);
                        echo json_encode(['error' => "Ollama not available at {$ollamaHost}. Make sure Ollama is running."]);
                        break;
                    }
                }

                error_log("=== INIT REQUEST ===");
                error_log("[init] session={$sessionId} platform={$platform} brain={$brainModel} embedding={$embeddingModel}");
                $logs = loadSampleLogs();
                error_log("[init] Loaded " . count($logs) . " sample logs");
                // Pre-index logs into cache using the embedding model when available
                indexLogsInCache($logs, $sessionId, $platform, $embeddingModel, $apiKey, $ollamaHost);
                cacheLogsData($logs, $sessionId);
                error_log("[init] Logs indexed and cached for session={$sessionId}");
                $statusFile = $sessionDir . '/' . $sessionId . '-init.json';
                $result = [
                    'initialized' => true,
                    'status' => 'ready',
                    'message' => 'Ready! ' . count($logs) . ' logs cached.',
                    'progress' => 100,
                    'logs_count' => count($logs),
                    'storage' => 'in-memory',
                    'session_id' => $sessionId,
                    'brain_model' => $brainModel,
                    'embedding_model' => $embeddingModel,
                ];
                file_put_contents($statusFile, json_encode($result));
                echo json_encode($result);
                break;

            case $method === 'POST' && $path === '/upload':
                // Handle file upload and process with indexer
                $sessionId = $input['session_id'] ?? 'default';
                $platform = $input['platform'] ?? 'openai';
                $brainModel = $input['brain_model'] ?? 'gpt-4o-mini';
                $embeddingModel = $input['embedding_model'] ?? 'text-embedding-3-small';
                $apiKey = $input['api_key'] ?? '';
                $ollamaHost = $input['ollama_host'] ?? 'http://localhost:11434';

                // Validate based on platform
                if ($platform !== 'ollama' && empty($apiKey)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'API key is required']);
                    break;
                }

                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'File upload failed']);
                    break;
                }

                $uploadedFile = $_FILES['file'];
                $targetPath = $uploadDir . '/' . $sessionId . '-' . basename($uploadedFile['name']);

                if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to save uploaded file']);
                    break;
                }

                try {
                    $result = processUploadedFile(
                        $targetPath,
                        $sessionId,
                        $platform,
                        $brainModel,
                        $embeddingModel,
                        $apiKey,
                        $ollamaHost,
                        $sessionDir
                    );
                    echo json_encode($result);
                } catch (Throwable $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Failed to process log file: ' . $e->getMessage(),
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine(),
                    ]);
                }
                break;

            case $method === 'GET' && $path === '/init-status':
                $sessionId = $_GET['session_id'] ?? 'default';
                $statusFile = $sessionDir . '/' . $sessionId . '-init.json';

                if (file_exists($statusFile)) {
                    echo file_get_contents($statusFile);
                } else {
                    $logs = loadSampleLogs();
                    echo json_encode([
                        'initialized' => true,
                        'logs_count' => count($logs),
                        'status' => 'ready',
                        'progress' => 100,
                        'message' => 'Logs loaded',
                    ]);
                }
                break;

            case $method === 'GET' && $path === '/logs':
                $sessionId = $_GET['session_id'] ?? 'default';
                $sessionLogsFile = $sessionDir . '/' . $sessionId . '-logs.json';

                // Check for session-specific logs (from upload)
                if (file_exists($sessionLogsFile)) {
                    $logs = json_decode(file_get_contents($sessionLogsFile), true) ?? [];
                } else {
                    $logs = loadSampleLogs();
                }
                echo json_encode(['logs' => $logs, 'count' => count($logs)]);
                break;

            case $method === 'POST' && $path === '/chat':
                $sessionId = $input['session_id'] ?? 'default';
                $question = $input['question'] ?? '';
                $platform = $input['platform'] ?? 'openai';
                $brainModel = $input['brain_model'] ?? $input['model'] ?? 'gpt-4o-mini';
                $embeddingModel = $input['embedding_model'] ?? 'text-embedding-3-small';
                $apiKey = $input['api_key'] ?? '';
                $ollamaHost = $input['ollama_host'] ?? 'http://localhost:11434';

                if (empty($question)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Question is required']);
                    break;
                }

                if ($platform !== 'ollama' && empty($apiKey)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'API key is required']);
                    break;
                }

                $response = handleChat(
                    $sessionId,
                    $question,
                    $platform,
                    $brainModel,
                    $embeddingModel,
                    $apiKey,
                    $ollamaHost,
                    $sessionDir
                );
                echo json_encode($response);
                break;

            case $method === 'POST' && $path === '/reset':
                $sessionId = $input['session_id'] ?? 'default';
                $safeSessionId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId);

                // Clean up session files
                $patterns = [
                    $sessionDir . '/' . $sessionId . '.json',
                    $sessionDir . '/' . $safeSessionId . '.session.json',
                    $sessionDir . '/' . $sessionId . '-init.json',
                    $sessionDir . '/' . $sessionId . '-logs.json',
                    $sessionDir . '/' . $sessionId . '-store.json',
                ];

                foreach ($patterns as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }

                // Clear the cache for this session
                $cacheAdapter = new FilesystemAdapter(
                    namespace: 'logs_' . $safeSessionId,
                    defaultLifetime: 3600,
                    directory: $cacheDir
                );
                $cacheAdapter->clear();

                echo json_encode(['status' => 'ok', 'message' => 'Session reset']);
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Not found', 'path' => $path]);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        error_log("Playground API Error: " . $e->getMessage());
        error_log("File: " . $e->getFile() . ":" . $e->getLine());
        error_log("Trace: " . $e->getTraceAsString());

        echo json_encode([
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ]);
    }
}

/**
 * Check if Ollama is available
 */
function isOllamaAvailable(string $host): bool
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET',
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($host . '/api/version', false, $context);
    return $result !== false;
}

/**
 * Get platform enum from string
 */
function getPlatformEnum(string $platform): PlatformEnum
{
    return match ($platform) {
        'openai' => PlatformEnum::OPENAI,
        'anthropic' => PlatformEnum::ANTHROPIC,
        'ollama' => PlatformEnum::OLLAMA,
        default => PlatformEnum::OPENAI,
    };
}

/**
 * Get platform configuration
 */
function getPlatformConfig(string $platform, string $brainModel, string $apiKey, string $ollamaHost): array
{
    $clientOptions = [
        'timeout' => 120,
        'max_duration' => 120,
    ];

    return match ($platform) {
        'openai' => [
            'provider' => 'openai',
            'api_key' => $apiKey,
            'model' => [
                'name' => $brainModel,
                'capabilities' => ['text', 'tool_calling'],
                'options' => [
                    'temperature' => 0.1,
                    'max_tokens' => 1000,
                ]
            ],
            'client_options' => $clientOptions,
        ],
        'anthropic' => [
            'provider' => 'anthropic',
            'api_key' => $apiKey,
            'model' => [
                'name' => $brainModel,
                'capabilities' => ['text', 'tool_calling'],
                'options' => [
                    'temperature' => 0.1,
                    'max_tokens' => 1000,
                ]
            ],
            'client_options' => $clientOptions,
        ],
        'ollama' => [
            'provider' => 'ollama',
            'host' => $ollamaHost,
            'model' => [
                'name' => $brainModel,
                'capabilities' => ['text', 'tool_calling'],
                'options' => [
                    'temperature' => 0.1,
                    'num_ctx' => 4096,
                ]
            ],
            'client_options' => $clientOptions,
        ],
        default => throw new InvalidArgumentException("Unsupported platform: {$platform}"),
    };
}

/**
 * Create a dedicated embedding platform based on the provider.
 * Returns null if the platform cannot be created (e.g. missing API key).
 */
function createEmbeddingPlatform(
    string $platform,
    string $embeddingModel,
    string $apiKey,
    string $ollamaHost
): ?LogDocumentPlatformInterface {
    error_log("[createEmbeddingPlatform] platform={$platform} model={$embeddingModel}");

    // Anthropic does not provide an embedding API.
    // Fall back to category vectors (the caller handles the null return).
    if ($platform === 'anthropic') {
        error_log("[createEmbeddingPlatform] Anthropic has no embedding API - will use fallback vectors");
        return null;
    }

    try {
        $result = match ($platform) {
            'ollama' => LogDocumentPlatformFactory::createEmbeddingPlatform(
                PlatformEnum::OLLAMA,
                ['host' => $ollamaHost, 'model' => $embeddingModel]
            ),
            'openai' => !empty($apiKey) ? LogDocumentPlatformFactory::createEmbeddingPlatform(
                PlatformEnum::OPENAI,
                ['api_key' => $apiKey, 'model' => $embeddingModel]
            ) : null,
            default => null,
        };
        if ($result !== null) {
            error_log("[createEmbeddingPlatform] Successfully created {$platform} embedding platform with model={$embeddingModel}");
        } else {
            error_log("[createEmbeddingPlatform] Returned null (missing credentials or unsupported platform)");
        }
        return $result;
    } catch (\Throwable $e) {
        error_log("[createEmbeddingPlatform] FAILED: " . $e->getMessage());
        return null;
    }
}

/**
 * Category vectors for semantic similarity (pre-computed for keyword matching)
 */
function getCategoryVectors(): array
{
    return [
        'payment' => [0.9, 0.1, 0.2, 0.8, 0.3],
        'database' => [0.2, 0.3, 0.9, 0.1, 0.5],
        'security' => [0.1, 0.9, 0.0, 0.3, 0.7],
        'application' => [0.4, 0.6, 0.3, 0.7, 0.8],
        'performance' => [0.7, 0.4, 0.5, 0.2, 0.9],
        'laravel' => [0.5, 0.5, 0.4, 0.6, 0.7],
        'kubernetes' => [0.3, 0.4, 0.6, 0.5, 0.8],
        'microservices' => [0.6, 0.3, 0.5, 0.4, 0.9],
        'general' => [0.5, 0.5, 0.5, 0.5, 0.5],
    ];
}

/**
 * Detect log category from content
 */
function detectCategory(string $content): string
{
    $contentLower = strtolower($content);

    if (preg_match('/payment|stripe|paypal|card|checkout|order/i', $contentLower)) {
        return 'payment';
    }
    if (preg_match('/database|mysql|postgres|sql|connection|query/i', $contentLower)) {
        return 'database';
    }
    if (preg_match('/security|auth|login|password|brute|attack|token/i', $contentLower)) {
        return 'security';
    }
    if (preg_match('/memory|cpu|slow|latency|timeout|performance/i', $contentLower)) {
        return 'performance';
    }
    if (preg_match('/kubernetes|k8s|pod|container|deployment/i', $contentLower)) {
        return 'kubernetes';
    }
    if (preg_match('/laravel|illuminate|artisan/i', $contentLower)) {
        return 'laravel';
    }
    if (preg_match('/service|api|endpoint|microservice/i', $contentLower)) {
        return 'microservices';
    }

    return 'application';
}

/**
 * Parse a log line
 */
function parseLogLine(string $line, string $defaultCategory, int $logId): ?array
{
    $line = trim($line);
    if (empty($line)) {
        return null;
    }

    $laravelPattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/';
    $genericPattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+): (.+)$/';
    $simplePattern = '/^(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}).*?(ERROR|WARN|WARNING|INFO|DEBUG|CRITICAL|FATAL)[:\s]+(.+)$/i';

    $timestamp = null;
    $level = 'INFO';
    $channel = 'app';
    $messageWithContext = '';

    if (preg_match($laravelPattern, $line, $matches)) {
        $timestamp = $matches[1];
        $channel = $matches[2];
        $level = strtoupper($matches[3]);
        $messageWithContext = $matches[4];
    } elseif (preg_match($genericPattern, $line, $matches)) {
        $timestamp = $matches[1];
        $level = strtoupper($matches[2]);
        $messageWithContext = $matches[3];
    } elseif (preg_match($simplePattern, $line, $matches)) {
        $timestamp = $matches[1];
        $level = strtoupper($matches[2]);
        $messageWithContext = $matches[3];
    } else {
        // Fallback: try to extract any timestamp-like pattern
        if (preg_match('/(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/', $line, $tsMatch)) {
            $timestamp = $tsMatch[1];
        }
        $messageWithContext = $line;
    }

    $context = [];
    $message = $messageWithContext;
    if (preg_match('/^(.+?)\s*(\{.+\})\s*(\[.+\])?$/', $messageWithContext, $msgMatches)) {
        $message = trim($msgMatches[1]);
        $contextJson = $msgMatches[2] ?? '{}';
        $context = json_decode($contextJson, true) ?? [];
    }

    // Auto-detect category from content
    $category = detectCategory($message);

    return [
        'log_id' => sprintf('%s_%03d', substr($category, 0, 3), $logId),
        'content' => $line,
        'message' => $message,
        'timestamp' => $timestamp ?? date('Y-m-d H:i:s'),
        'level' => strtolower($level),
        'channel' => $channel,
        'category' => $category,
        'context' => $context,
    ];
}

/**
 * Load sample logs for API response
 */
function loadSampleLogs(): array
{
    $fixturesDir = __DIR__ . '/../test/fixtures/logs';
    $logFiles = [
        'payment' => 'payment-errors.log',
        'database' => 'database-errors.log',
        'security' => 'security-errors.log',
        'application' => 'application-errors.log',
        'kubernetes' => 'kubernetes-errors.log',
        'microservices' => 'microservices-errors.log',
        'symfony' => 'symfony.log',
        'laravel-info' => 'laravel.log',
    ];

    $logs = [];
    $logId = 1;

    foreach ($logFiles as $category => $filename) {
        $filePath = $fixturesDir . '/' . $filename;
        if (!file_exists($filePath)) {
            continue;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parsed = parseLogLine($line, $category, $logId);
            if ($parsed) {
                $logs[] = [
                    'id' => $parsed['log_id'],
                    'level' => strtoupper($parsed['level']),
                    'message' => $parsed['message'],
                    'timestamp' => $parsed['timestamp'],
                    'category' => $category,
                    'context' => $parsed['context'],
                ];
                $logId++;
            }
        }
    }

    return $logs;
}

/**
 * Process uploaded log file using the indexer
 */
function processUploadedFile(
    string $filePath,
    string $sessionId,
    string $platform,
    string $brainModel,
    string $embeddingModel,
    string $apiKey,
    string $ollamaHost,
    string $sessionDir
): array {
    $startTime = microtime(true);
    error_log("=== UPLOAD: Processing file ===");
    error_log("[upload] file={$filePath} session={$sessionId} platform={$platform} embedding={$embeddingModel}");

    // Parse the log file
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    error_log("[upload] Read " . count($lines) . " lines from file");
    $logs = [];
    $logId = 1;

    foreach ($lines as $line) {
        $parsed = parseLogLine($line, 'general', $logId);
        if ($parsed) {
            $logs[] = [
                'id' => $parsed['log_id'],
                'level' => strtoupper($parsed['level']),
                'message' => $parsed['message'],
                'timestamp' => $parsed['timestamp'],
                'category' => $parsed['category'],
                'context' => $parsed['context'],
            ];
            $logId++;
        }
    }
    error_log("[upload] Parsed " . count($logs) . " log entries from " . count($lines) . " lines");

    // Save parsed logs for the session
    $logsFile = $sessionDir . '/' . $sessionId . '-logs.json';
    file_put_contents($logsFile, json_encode($logs));
    cacheLogsData($logs, $sessionId);

    // Create vector store and index the logs (persisted in cache per session)
    global $cacheDir;
    $safeSessionId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId);
    $cacheAdapter = new FilesystemAdapter(
        namespace: 'logs_' . $safeSessionId,
        defaultLifetime: 3600,
        directory: $cacheDir
    );
    $cacheStore = new CacheStore($cacheAdapter);
    $cacheStore->setup();
    $vectorStore = new VectorLogDocumentStore($cacheStore);
    // drop() clears the entire cache namespace; re-save logs_data afterwards
    error_log("[upload] Dropping old cache and re-saving logs_data");
    $cacheStore->drop();

    $logsDataItem = $cacheAdapter->getItem('logs_data');
    $logsDataItem->set($logs);
    $cacheAdapter->save($logsDataItem);

    // If embedding platform is available, use the indexer
    // Otherwise, use simple category-based vectors
    $useIndexer = false;

    try {
        if ($platform === 'ollama') {
            // Try to use Ollama embedding
            error_log("[upload] Creating Ollama embedding platform: model={$embeddingModel} host={$ollamaHost}");
            $embeddingPlatform = LogDocumentPlatformFactory::createEmbeddingPlatform(
                PlatformEnum::OLLAMA,
                [
                    'host' => $ollamaHost,
                    'model' => $embeddingModel,
                ]
            );
            error_log("[upload] Ollama embedding platform created successfully");

            $indexer = new LogFileIndexer(
                embeddingPlatform: $embeddingPlatform->getPlatform(),
                model: $embeddingModel,
                logStore: $vectorStore,
                chunkSize: 500,
                chunkOverlap: 100
            );

            error_log("[upload] Indexing file with LogFileIndexer using {$embeddingModel}...");
            $indexStartTime = microtime(true);
            $indexer->indexLogFiles([$filePath]);
            $indexDuration = round((microtime(true) - $indexStartTime) * 1000);
            error_log("[upload] LogFileIndexer completed in {$indexDuration}ms");
            $useIndexer = true;
        } elseif ($platform === 'openai' && !empty($apiKey)) {
            // Use OpenAI embedding
            error_log("[upload] Creating OpenAI embedding platform: model={$embeddingModel}");
            $embeddingPlatform = LogDocumentPlatformFactory::createEmbeddingPlatform(
                PlatformEnum::OPENAI,
                [
                    'api_key' => $apiKey,
                    'model' => $embeddingModel,
                ]
            );
            error_log("[upload] OpenAI embedding platform created successfully");

            $indexer = new LogFileIndexer(
                embeddingPlatform: $embeddingPlatform->getPlatform(),
                model: $embeddingModel,
                logStore: $vectorStore,
                chunkSize: 500,
                chunkOverlap: 100
            );

            error_log("[upload] Indexing file with LogFileIndexer using {$embeddingModel}...");
            $indexStartTime = microtime(true);
            $indexer->indexLogFiles([$filePath]);
            $indexDuration = round((microtime(true) - $indexStartTime) * 1000);
            error_log("[upload] LogFileIndexer completed in {$indexDuration}ms");
            $useIndexer = true;
        }
    } catch (Throwable $e) {
        error_log("[upload] Indexer failed, falling back to simple vectors: " . $e->getMessage());
        $useIndexer = false;
    }

    // If indexer failed, use simple category-based vectors
    if (!$useIndexer) {
        error_log("[upload] Using category-based fallback vectors for " . count($logs) . " logs");
        $categoryVectors = getCategoryVectors();

        foreach ($logs as $log) {
            $category = $log['category'] ?? 'general';
            $vector = new Vector($categoryVectors[$category] ?? $categoryVectors['general']);
            $metadata = new Metadata([
                'log_id' => $log['id'],
                'content' => $log['message'],
                'message' => $log['message'],
                'timestamp' => $log['timestamp'],
                'level' => strtolower($log['level']),
                'category' => $category,
            ]);
            $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
            $cacheStore->add($document);
        }
        error_log("[upload] Fallback indexing complete");
    }

    // Persist logs hash so chat can reuse the existing store
    $logsHash = sha1(json_encode($logs));
    $hashItem = $cacheAdapter->getItem('logs_hash');
    $hashItem->set($logsHash);
    $cacheAdapter->save($hashItem);
    error_log("[upload] Cache hash saved: {$logsHash}");

    // Save store state for later use
    $storeFile = $sessionDir . '/' . $sessionId . '-store.json';
    file_put_contents($storeFile, json_encode([
        'logs' => $logs,
        'indexed' => $useIndexer,
        'embedding_model' => $embeddingModel,
    ]));

    $duration = round((microtime(true) - $startTime) * 1000);

    // Save init status
    $statusFile = $sessionDir . '/' . $sessionId . '-init.json';
    $result = [
        'initialized' => true,
        'status' => 'ready',
        'message' => 'Ready! ' . count($logs) . ' logs indexed from uploaded file.',
        'progress' => 100,
        'logs_count' => count($logs),
        'storage' => 'in-memory',
        'session_id' => $sessionId,
        'brain_model' => $brainModel,
        'embedding_model' => $embeddingModel,
        'indexed_with_embeddings' => $useIndexer,
        'duration_ms' => $duration,
        'file_name' => basename($filePath),
    ];
    file_put_contents($statusFile, json_encode($result));

    return $result;
}

/**
 * Extract evidence logs from AI response
 */
function extractEvidenceLogs(string $content, array $allLogs): array
{
    $evidenceLogs = [];
    $patterns = [
        '/\b(pay_\d{3})\b/i',
        '/\b(dat_\d{3})\b/i',
        '/\b(db_\d{3})\b/i',
        '/\b(sec_\d{3})\b/i',
        '/\b(app_\d{3})\b/i',
        '/\b(per_\d{3})\b/i',
        '/\b(lar_\d{3})\b/i',
        '/\b(kub_\d{3})\b/i',
        '/\b(mic_\d{3})\b/i',
        '/\b(gen_\d{3})\b/i',
    ];

    $foundIds = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            $foundIds = array_merge($foundIds, $matches[1]);
        }
    }
    $foundIds = array_unique($foundIds);

    foreach ($allLogs as $log) {
        $logId = strtolower($log['id'] ?? '');
        foreach ($foundIds as $foundId) {
            if (stripos($logId, strtolower($foundId)) !== false) {
                $evidenceLogs[] = $log;
                break;
            }
        }
    }

    // Keyword fallback
    if (count($evidenceLogs) < 3) {
        $categoryKeywords = [
            'payment' => ['payment', 'stripe', 'paypal', 'card'],
            'database' => ['database', 'mysql', 'connection', 'query'],
            'security' => ['security', 'brute force', 'authentication', 'login'],
            'application' => ['memory', 'exception', 'error', 'fatal'],
            'performance' => ['slow', 'timeout', 'latency', 'performance'],
        ];
        $contentLower = strtolower($content);
        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($contentLower, $keyword) !== false) {
                    foreach ($allLogs as $log) {
                        if (($log['category'] ?? '') === $category && !in_array($log, $evidenceLogs)) {
                            $evidenceLogs[] = $log;
                            if (count($evidenceLogs) >= 5) break 3;
                        }
                    }
                }
            }
        }
    }

    return array_slice($evidenceLogs, 0, 8);
}

/**
 * Handle chat request
 */
function handleChat(
    string $sessionId,
    string $question,
    string $platform,
    string $brainModel,
    string $embeddingModel,
    string $apiKey,
    string $ollamaHost,
    string $sessionDir
): array {
    $startTime = microtime(true);

    error_log("=== CHAT REQUEST ===");
    error_log("[chat] session={$sessionId} platform={$platform} brain={$brainModel} embedding={$embeddingModel}");
    error_log("[chat] question: {$question}");

    // Use FilesystemAdapter for persistent caching across requests
    global $cacheDir;
    $safeSessionId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId);
    $cacheAdapter = new FilesystemAdapter(
        namespace: 'logs_' . $safeSessionId,
        defaultLifetime: 3600, // 1 hour cache TTL
        directory: $cacheDir
    );
    $cacheStore = new CacheStore($cacheAdapter);
    $cacheStore->setup();
    $vectorStore = new VectorLogDocumentStore($cacheStore);
    error_log("[chat] Cache initialized: namespace=logs_{$safeSessionId} dir={$cacheDir}");

    // Load logs from cache if available, otherwise from disk/fixtures once
    $logsItem = $cacheAdapter->getItem('logs_data');
    if ($logsItem->isHit()) {
        $allLogs = $logsItem->get();
        error_log("[chat] Logs loaded from cache: " . count($allLogs) . " entries");
    } else {
        $sessionLogsFile = $sessionDir . '/' . $sessionId . '-logs.json';
        if (file_exists($sessionLogsFile)) {
            $allLogs = json_decode(file_get_contents($sessionLogsFile), true) ?? [];
            error_log("[chat] Logs loaded from session file: {$sessionLogsFile} (" . count($allLogs) . " entries)");
        } else {
            $allLogs = loadSampleLogs();
            error_log("[chat] Logs loaded from sample fixtures: " . count($allLogs) . " entries");
        }
        $logsItem->set($allLogs);
        $cacheAdapter->save($logsItem);
        error_log("[chat] Logs saved to cache");
    }

    // Check if logs are already indexed and cached for this session
    $logsHash = sha1(json_encode($allLogs));
    $hashItem = $cacheAdapter->getItem('logs_hash');
    $vectorsItem = $cacheAdapter->getItem('_vectors');
    $vectors = $vectorsItem->isHit() ? $vectorsItem->get() : [];
    $hasVectors = is_array($vectors) && count($vectors) > 0;
    $cachedModelItem = $cacheAdapter->getItem('embedding_model');
    $cachedModel = $cachedModelItem->isHit() ? $cachedModelItem->get() : '';
    $modelChanged = !empty($embeddingModel) && $cachedModel !== $embeddingModel;
    $needsReindex = !$hashItem->isHit() || $hashItem->get() !== $logsHash || !$hasVectors || $modelChanged;

    error_log(sprintf(
        "[chat] Cache check: hash_hit=%s hash_match=%s vectors_count=%d cached_model=%s requested_model=%s model_changed=%s needs_reindex=%s",
        $hashItem->isHit() ? 'yes' : 'no',
        ($hashItem->isHit() && $hashItem->get() === $logsHash) ? 'yes' : 'no',
        is_array($vectors) ? count($vectors) : 0,
        $cachedModel ?: '(none)',
        $embeddingModel,
        $modelChanged ? 'YES' : 'no',
        $needsReindex ? 'YES' : 'no'
    ));

    if ($needsReindex) {
        error_log("[chat][index] Starting log indexing for session={$sessionId}");

        // drop() calls cache->clear() which wipes the entire namespace,
        // including logs_data and logs_hash. We must re-save them afterwards.
        $cacheStore->drop();
        error_log("[chat][index] Cache dropped (all keys cleared)");

        // Re-save logs_data so it survives the drop
        $logsItem = $cacheAdapter->getItem('logs_data');
        $logsItem->set($allLogs);
        $cacheAdapter->save($logsItem);
        error_log("[chat][index] logs_data re-saved after drop");

        // Try to use a real embedding platform for proper vectorization
        $indexed = false;
        try {
            error_log("[chat][index] Creating embedding platform: platform={$platform} model={$embeddingModel}");
            $embeddingPlatform = createEmbeddingPlatform($platform, $embeddingModel, $apiKey, $ollamaHost);
            if ($embeddingPlatform !== null) {
                error_log("[chat][index] Embedding platform created, starting vectorization of " . count($allLogs) . " logs");
                $vectorizer = \Hakam\AiLogInspector\Vectorizer\VectorizerFactory::create(
                    $embeddingPlatform->getPlatform(),
                    $embeddingModel
                );
                $indexStartTime = microtime(true);
                $vectorizedCount = 0;
                foreach ($allLogs as $log) {
                    $category = $log['category'] ?? 'general';
                    $content = ($log['message'] ?? '') . ' [' . strtolower($log['level'] ?? '') . '] [' . $category . ']';
                    $embedStartTime = microtime(true);
                    $vector = $vectorizer->vectorize($content);
                    $embedDuration = round((microtime(true) - $embedStartTime) * 1000);
                    $vectorDim = count($vector->getData());
                    $vectorizedCount++;
                    if ($vectorizedCount <= 3 || $vectorizedCount % 10 === 0) {
                        error_log(sprintf(
                            "[chat][index][embed] log %d/%d: dim=%d time=%dms id=%s",
                            $vectorizedCount,
                            count($allLogs),
                            $vectorDim,
                            $embedDuration,
                            $log['id'] ?? 'unknown'
                        ));
                    }
                    $metadata = new Metadata([
                        'log_id' => $log['id'],
                        'content' => $log['message'],
                        'message' => $log['message'],
                        'timestamp' => $log['timestamp'],
                        'level' => strtolower($log['level']),
                        'category' => $category,
                    ]);
                    $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
                    $cacheStore->add($document);
                }
                $indexDuration = round((microtime(true) - $indexStartTime) * 1000);
                $indexed = true;
                error_log(sprintf(
                    "[chat][index] Embedding indexing complete: %d logs, dim=%d, total=%dms, avg=%dms/log",
                    $vectorizedCount,
                    $vectorDim ?? 0,
                    $indexDuration,
                    $vectorizedCount > 0 ? round($indexDuration / $vectorizedCount) : 0
                ));
            } else {
                error_log("[chat][index] Embedding platform returned null, will use fallback");
            }
        } catch (\Throwable $e) {
            error_log("[chat][index] Embedding indexing failed: " . $e->getMessage());
            error_log("[chat][index] Falling back to category vectors");
        }

        // Fallback: use simple category-based vectors
        if (!$indexed) {
            error_log("[chat][index] Using category-based fallback vectors for " . count($allLogs) . " logs");
            $categoryVectors = getCategoryVectors();
            foreach ($allLogs as $log) {
                $category = $log['category'] ?? 'general';
                $vector = new Vector($categoryVectors[$category] ?? $categoryVectors['general']);
                $metadata = new Metadata([
                    'log_id' => $log['id'],
                    'content' => $log['message'],
                    'message' => $log['message'],
                    'timestamp' => $log['timestamp'],
                    'level' => strtolower($log['level']),
                    'category' => $category,
                ]);
                $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
                $cacheStore->add($document);
            }
            error_log("[chat][index] Fallback indexing complete: " . count($allLogs) . " logs with 5-dim category vectors");
        }

        $hashItem = $cacheAdapter->getItem('logs_hash');
        $hashItem->set($logsHash);
        $cacheAdapter->save($hashItem);
        $usedModel = $indexed ? $embeddingModel : 'category_fallback';
        $modelItem = $cacheAdapter->getItem('embedding_model');
        $modelItem->set($usedModel);
        $cacheAdapter->save($modelItem);
        error_log("[chat][index] Cache saved: hash={$logsHash} model={$usedModel}");
    } else {
        error_log("[chat] Using cached index: " . count($vectors) . " vectors, " . count($allLogs) . " logs, model={$cachedModel}");
    }

    // Create brain platform for reasoning/chat
    error_log("[chat][platform] Creating brain platform: provider={$platform} model={$brainModel}");
    $platformConfig = getPlatformConfig($platform, $brainModel, $apiKey, $ollamaHost);
    $brainPlatform = LogDocumentPlatformFactory::create($platformConfig);
    error_log("[chat][platform] Brain platform created successfully");

    // Create a dedicated embedding platform for retrieval.
    // The retriever must use the same embedding model that was used for indexing,
    // so the query vectors match the stored document vectors.
    error_log("[chat][platform] Creating embedding platform for retriever: model={$embeddingModel}");
    $embeddingPlatform = createEmbeddingPlatform($platform, $embeddingModel, $apiKey, $ollamaHost);

    if ($embeddingPlatform !== null) {
        $retriever = new LogRetriever(
            $embeddingPlatform->getPlatform(),
            $embeddingModel,
            $vectorStore,
        );
        error_log("[chat][platform] Retriever created with dedicated embedding platform: {$embeddingModel}");
    } else {
        // Fallback: use brain platform (only works when brain model supports embeddings)
        $retriever = new LogRetriever(
            $brainPlatform->getPlatform(),
            $brainModel,
            $vectorStore,
        );
        error_log("[chat][platform] WARNING: Retriever falling back to brain platform: {$brainModel}");
    }

    // Create tools - brain platform is used for analysis/reasoning, retriever for search
    $searchTool = new LogSearchTool($vectorStore, $retriever, $brainPlatform);
    $contextTool = new RequestContextTool($vectorStore, $retriever, $brainPlatform);
    error_log("[chat][tools] LogSearchTool and RequestContextTool created");

    // Create chat with session persistence
    $chat = LogInspectorChatFactory::createSession(
        $sessionId,
        $brainPlatform,
        $searchTool,
        $contextTool,
        $sessionDir
    );
    error_log("[chat][session] Chat session created: session={$sessionId} dir={$sessionDir}");

    // Start investigation if new session (check the correct session file path)
    // SessionMessageStore uses: $storagePath.'/'.$sessionId.'.session.json'
    $sessionFile = $sessionDir . '/' . $safeSessionId . '.session.json';
    if (file_exists($sessionFile)) {
        $sessionSize = @filesize($sessionFile) ?: 0;
        error_log(sprintf(
            "[chat][session] Existing session found: file=%s bytes=%d",
            $sessionFile,
            $sessionSize
        ));
    } else {
        error_log(sprintf(
            "[chat][session] No existing session: file=%s (new conversation)",
            $sessionFile
        ));
    }

    if (!file_exists($sessionFile)) {
        error_log("[chat][session] Starting new investigation");
        $chat->startInvestigation('Playground investigation - ' . date('Y-m-d H:i:s'));
        error_log("[chat][session] Investigation started with system prompt");
    }

    // Send question and get response
    error_log("[chat][agent] Sending question to agent...");
    $agentStartTime = microtime(true);
    $response = $chat->ask($question);
    $content = $response->getContent();
    $agentDuration = round((microtime(true) - $agentStartTime) * 1000);
    error_log(sprintf(
        "[chat][agent] Response received: length=%d chars, time=%dms",
        strlen($content),
        $agentDuration
    ));
    error_log("[chat][agent] Response preview: " . substr(preg_replace('/\s+/', ' ', $content), 0, 200) . "...");

    // Extract evidence logs from the response
    $evidenceLogs = extractEvidenceLogs($content, $allLogs);
    error_log("[chat][evidence] Extracted " . count($evidenceLogs) . " evidence logs from response");

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log(sprintf(
        "[chat] Request complete: total=%dms agent=%dms session=%s",
        $duration,
        $agentDuration,
        $sessionId
    ));

    return [
        'success' => true,
        'content' => $content,
        'evidence_logs' => $evidenceLogs,
        'duration_ms' => $duration,
        'model' => $brainModel,
        'embedding_model' => $embeddingModel,
        'platform' => $platform,
        'session_id' => $sessionId,
    ];
}

/**
 * Pre-index logs into cache for a given session.
 * Uses the embedding platform when available, falls back to category vectors.
 */
function indexLogsInCache(
    array $logs,
    string $sessionId,
    string $platform = '',
    string $embeddingModel = '',
    string $apiKey = '',
    string $ollamaHost = 'http://localhost:11434'
): void {
    error_log("[indexLogsInCache] session={$sessionId} logs=" . count($logs) . " platform={$platform} embedding={$embeddingModel}");
    global $cacheDir;
    $safeSessionId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId);
    $cacheAdapter = new FilesystemAdapter(
        namespace: 'logs_' . $safeSessionId,
        defaultLifetime: 3600,
        directory: $cacheDir
    );
    $cacheStore = new CacheStore($cacheAdapter);
    $cacheStore->setup();

    $logsHash = sha1(json_encode($logs));
    $hashItem = $cacheAdapter->getItem('logs_hash');
    $vectorsItem = $cacheAdapter->getItem('_vectors');
    $vectors = $vectorsItem->isHit() ? $vectorsItem->get() : [];
    $hasVectors = is_array($vectors) && count($vectors) > 0;
    $cachedModelItem = $cacheAdapter->getItem('embedding_model');
    $cachedModel = $cachedModelItem->isHit() ? $cachedModelItem->get() : '';
    $modelChanged = !empty($embeddingModel) && $cachedModel !== $embeddingModel;
    $needsReindex = !$hashItem->isHit() || $hashItem->get() !== $logsHash || !$hasVectors || $modelChanged;

    error_log(sprintf(
        "[indexLogsInCache] hash_hit=%s hash_match=%s vectors=%d cached_model=%s model_changed=%s needs_reindex=%s",
        $hashItem->isHit() ? 'yes' : 'no',
        ($hashItem->isHit() && $hashItem->get() === $logsHash) ? 'yes' : 'no',
        is_array($vectors) ? count($vectors) : 0,
        $cachedModel ?: '(none)',
        $modelChanged ? 'YES' : 'no',
        $needsReindex ? 'YES' : 'no'
    ));

    if (!$needsReindex) {
        error_log("[indexLogsInCache] Skipping - already indexed with model={$cachedModel}");
        return;
    }

    // drop() calls cache->clear() which wipes the entire namespace.
    // Re-save logs_data afterwards so the chat handler doesn't reload from disk.
    $cacheStore->drop();
    error_log("[indexLogsInCache] Cache dropped, re-saving logs_data");

    $logsDataItem = $cacheAdapter->getItem('logs_data');
    $logsDataItem->set($logs);
    $cacheAdapter->save($logsDataItem);

    // Try to use a real embedding platform for proper vectorization
    $indexed = false;
    if (!empty($platform) && !empty($embeddingModel)) {
        try {
            error_log("[indexLogsInCache] Creating embedding platform: {$platform}/{$embeddingModel}");
            $embeddingPlatform = createEmbeddingPlatform($platform, $embeddingModel, $apiKey, $ollamaHost);
            if ($embeddingPlatform !== null) {
                $vectorizer = \Hakam\AiLogInspector\Vectorizer\VectorizerFactory::create(
                    $embeddingPlatform->getPlatform(),
                    $embeddingModel
                );
                error_log("[indexLogsInCache] Vectorizing " . count($logs) . " logs with {$embeddingModel}...");
                $indexStartTime = microtime(true);
                $vectorizedCount = 0;
                foreach ($logs as $log) {
                    $category = $log['category'] ?? 'general';
                    $content = ($log['message'] ?? '') . ' [' . strtolower($log['level'] ?? '') . '] [' . $category . ']';
                    $embedStartTime = microtime(true);
                    $vector = $vectorizer->vectorize($content);
                    $embedDuration = round((microtime(true) - $embedStartTime) * 1000);
                    $vectorizedCount++;
                    if ($vectorizedCount <= 3 || $vectorizedCount % 20 === 0) {
                        error_log(sprintf(
                            "[indexLogsInCache][embed] log %d/%d: dim=%d time=%dms",
                            $vectorizedCount,
                            count($logs),
                            count($vector->getData()),
                            $embedDuration
                        ));
                    }
                    $metadata = new Metadata([
                        'log_id' => $log['id'],
                        'content' => $log['message'],
                        'message' => $log['message'],
                        'timestamp' => $log['timestamp'],
                        'level' => strtolower($log['level']),
                        'category' => $category,
                    ]);
                    $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
                    $cacheStore->add($document);
                }
                $indexDuration = round((microtime(true) - $indexStartTime) * 1000);
                $indexed = true;
                error_log(sprintf(
                    "[indexLogsInCache] Embedding indexing complete: %d logs, total=%dms, avg=%dms/log",
                    $vectorizedCount,
                    $indexDuration,
                    $vectorizedCount > 0 ? round($indexDuration / $vectorizedCount) : 0
                ));
            }
        } catch (\Throwable $e) {
            error_log("[indexLogsInCache] Embedding failed: " . $e->getMessage());
        }
    }

    // Fallback: use simple category-based vectors
    if (!$indexed) {
        error_log("[indexLogsInCache] Fallback: indexing " . count($logs) . " logs with category vectors (5-dim)");
        $categoryVectors = getCategoryVectors();
        foreach ($logs as $log) {
            $category = $log['category'] ?? 'general';
            $vector = new Vector($categoryVectors[$category] ?? $categoryVectors['general']);
            $metadata = new Metadata([
                'log_id' => $log['id'],
                'content' => $log['message'],
                'message' => $log['message'],
                'timestamp' => $log['timestamp'],
                'level' => strtolower($log['level']),
                'category' => $category,
            ]);
            $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
            $cacheStore->add($document);
        }
    }

    // Save hash and embedding model used, so chat handler knows what model was used
    $hashItem = $cacheAdapter->getItem('logs_hash');
    $hashItem->set($logsHash);
    $cacheAdapter->save($hashItem);
    $modelItem = $cacheAdapter->getItem('embedding_model');
    $modelItem->set($indexed ? $embeddingModel : 'category_fallback');
    $cacheAdapter->save($modelItem);
    error_log("[indexLogsInCache] Complete: " . count($logs) . " logs indexed, model=" . ($indexed ? $embeddingModel : 'category_fallback') . ", hash={$logsHash}");
}

/**
 * Cache parsed logs for a given session to avoid re-reading files.
 */
function cacheLogsData(array $logs, string $sessionId): void
{
    global $cacheDir;
    $safeSessionId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId);
    $cacheAdapter = new FilesystemAdapter(
        namespace: 'logs_' . $safeSessionId,
        defaultLifetime: 3600,
        directory: $cacheDir
    );

    $logsItem = $cacheAdapter->getItem('logs_data');
    $logsItem->set($logs);
    $cacheAdapter->save($logsItem);
}
