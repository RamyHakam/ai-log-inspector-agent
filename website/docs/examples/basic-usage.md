# Basic Usage Examples

This guide provides simple, practical examples to get you started with the AI Log Inspector Agent.

## Simple Question & Answer

The most basic usage pattern:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizer;
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Document\CachedLogsDocumentLoader;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;

// 1. Setup platform
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'openai',
    'api_key' => $_ENV['OPENAI_API_KEY'],
    'model' => ['name' => 'gpt-4o-mini']
]);

// 2. Create components
$store = new VectorLogDocumentStore(new InMemoryStore());
$vectorizer = new LogDocumentVectorizer(
    $platform->getPlatform(),
    'text-embedding-3-small'
);
$tool = new LogSearchTool($store, $vectorizer, $platform);

// 3. Create agent
$agent = new LogInspectorAgent($platform, [$tool]);

// 4. Create a loader and indexer for your log files
$loader = new CachedLogsDocumentLoader('/var/log/app');

$indexer = new LogFileIndexer(
    embeddingPlatform: $platform->getPlatform(),
    model: 'text-embedding-3-small',
    loader: $loader,
    logStore: $store,
    chunkSize: 500,
    chunkOverlap: 100
);

// 5. Index log files
$indexer->indexLogFile('errors.log');
// Or index all logs: $indexer->indexAllLogs();

// 6. Ask a question!
$result = $agent->ask('Why did the payment fail for order 12345?');

echo "AI Response:\n";
echo $result->getContent() . "\n";
```

**Output**:
```
AI Response:
The payment failed for order #12345 due to a payment gateway timeout. The Stripe API
returned a 504 Gateway Timeout error, and despite 3 retry attempts, the payment could
not be completed. This indicates network connectivity issues or the Stripe service
experiencing problems at that time.
```

## Loading Logs from Files

The recommended approach is to use the loader-based indexer:

```php
<?php

use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Document\CachedLogsDocumentLoader;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;

// Create a loader pointing to your logs directory
$loader = new CachedLogsDocumentLoader('/var/log/app');

// Create the indexer
$indexer = new LogFileIndexer(
    embeddingPlatform: $platform->getPlatform(),
    model: 'text-embedding-3-small',
    loader: $loader,
    logStore: $store,
    chunkSize: 500,
    chunkOverlap: 100
);

// Index a single log file
$indexer->indexLogFile('production.log');

// Or index multiple specific files
$indexer->indexLogFiles(['production.log', 'errors.log', 'security.log']);

// Or index all .log files in the directory
$indexer->indexAllLogs();

// With options for pattern matching
$indexer->indexAllLogs([
    'pattern' => '*.log',      // Glob pattern (default: *.log)
    'recursive' => true,       // Search subdirectories
]);

// Now query the indexed logs
$result = $agent->ask('What errors occurred in the last hour?');
echo $result->getContent();
```

## Finding Specific Error Types

Search for particular kinds of errors:

```php
// Database errors
$result = $agent->ask('Show me all database errors');
echo $result->getContent() . "\n\n";

// Payment errors
$result = $agent->ask('What payment failures occurred?');
echo $result->getContent() . "\n\n";

// Memory errors
$result = $agent->ask('Are there any out of memory errors?');
echo $result->getContent() . "\n\n";

// Authentication errors
$result = $agent->ask('Show me failed login attempts');
echo $result->getContent() . "\n\n";
```

## Time-Based Queries

Query logs for specific time ranges:

```php
// Recent errors
$result = $agent->ask('What errors happened in the last hour?');

// Specific time window
$result = $agent->ask('Show errors between 2 PM and 3 PM today');

// After a specific event
$result = $agent->ask('What happened after the deployment at 14:30?');

// Before an incident
$result = $agent->ask('What logs exist before the outage at 15:00?');
```

## Pattern Discovery

Find recurring issues:

```php
// Frequent errors
$result = $agent->ask('What are the most common errors?');

// Recurring patterns
$result = $agent->ask('Are there any patterns in the timeout errors?');

// Anomalies
$result = $agent->ask('Any unusual activity in the logs?');

// Trends
$result = $agent->ask('Are errors increasing or decreasing over time?');
```

## Debugging Specific Features

Debug particular application features:

```php
// Checkout flow
$result = $agent->ask('What errors occurred during checkout?');

// User authentication
$result = $agent->ask('Why are users unable to log in?');

// File uploads
$result = $agent->ask('What upload errors happened?');

// Email sending
$result = $agent->ask('Show failed email deliveries');
```

## Multiple Log Sources

Index logs from different services using multiple loaders:

```php
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Document\CachedLogsDocumentLoader;

// Index logs from API Gateway service
$apiGatewayLoader = new CachedLogsDocumentLoader('/var/log/api-gateway');
$apiGatewayIndexer = new LogFileIndexer(
    embeddingPlatform: $platform->getPlatform(),
    model: 'text-embedding-3-small',
    loader: $apiGatewayLoader,
    logStore: $store  // Use same store for unified search
);
$apiGatewayIndexer->indexAllLogs();

// Index logs from Auth Service
$authServiceLoader = new CachedLogsDocumentLoader('/var/log/auth-service');
$authServiceIndexer = new LogFileIndexer(
    embeddingPlatform: $platform->getPlatform(),
    model: 'text-embedding-3-small',
    loader: $authServiceLoader,
    logStore: $store  // Same store - logs are combined
);
$authServiceIndexer->indexAllLogs();

// Query across all services (logs from both are in the same store)
$result = $agent->ask('What caused the timeout errors?');
echo $result->getContent();
```

## Using Different AI Providers

### OpenAI

```php
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'openai',
    'api_key' => $_ENV['OPENAI_API_KEY'],
    'model' => ['name' => 'gpt-4o-mini']  // or 'gpt-4o'
]);
```

### Anthropic Claude

```php
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'anthropic',
    'api_key' => $_ENV['ANTHROPIC_API_KEY'],
    'model' => ['name' => 'claude-3-5-sonnet-20241022']
]);
```

### Ollama (Local)

```php
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'ollama',
    'host' => 'http://localhost:11434',
    'model' => ['name' => 'llama3.2:1b']
]);
```

## Batch Processing

The indexer handles batch processing automatically with configurable chunk sizes:

```php
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Document\CachedLogsDocumentLoader;

// Create loader for your logs directory
$loader = new CachedLogsDocumentLoader('/var/log/app');

// Create indexer with custom chunk settings
$indexer = new LogFileIndexer(
    embeddingPlatform: $platform->getPlatform(),
    model: 'text-embedding-3-small',
    loader: $loader,
    logStore: $store,
    chunkSize: 1000,    // Larger chunks for less API calls
    chunkOverlap: 200   // Overlap to preserve context
);

// Index all log files - the indexer handles batching internally
$indexer->indexAllLogs([
    'chunk_size' => 50  // Process 50 documents at a time
]);

echo "All logs indexed!\n";

// Or index specific files in sequence
$logFiles = ['app-2024-01.log', 'app-2024-02.log', 'app-2024-03.log'];
$indexer->indexLogFiles($logFiles);
```

## Error Handling

Handle errors gracefully:

```php
try {
    $result = $agent->ask('What payment errors occurred?');
    
    if ($result->getContent()) {
        echo "Analysis:\n";
        echo $result->getContent();
    } else {
        echo "No relevant logs found\n";
    }
    
} catch (\Exception $e) {
    echo "Error during analysis: " . $e->getMessage() . "\n";
    
    // Fallback to simpler query
    $result = $agent->ask('Show me error logs');
    echo $result->getContent();
}
```

## Filtering Results

Work with structured responses:

```php
$result = $agent->ask('What errors occurred?');

// Get the full response
$content = $result->getContent();

// Check if analysis was successful
if (str_contains($content, 'No relevant logs')) {
    echo "No errors found\n";
} else {
    echo "Errors found:\n";
    echo $content . "\n";
}
```

## Real-Time Log Monitoring

For real-time monitoring, periodically re-index the log file:

```php
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Document\CachedLogsDocumentLoader;

// Create loader and indexer
$loader = new CachedLogsDocumentLoader('/var/log/app');
$indexer = new LogFileIndexer(
    embeddingPlatform: $platform->getPlatform(),
    model: 'text-embedding-3-small',
    loader: $loader,
    logStore: $store
);

// Initial indexing
$indexer->indexLogFile('production.log');

// Monitor for new entries periodically
while (true) {
    // Re-index to pick up new log entries
    // Note: For production, consider using a streaming approach
    // or file watching to detect changes
    $indexer->indexLogFile('production.log');

    // Check for critical errors
    $result = $agent->ask('Are there any new critical errors?');
    $content = $result->getContent();

    if (!str_contains(strtolower($content), 'no critical')) {
        echo "🚨 CRITICAL ALERT:\n" . $content . "\n";
    }

    sleep(60);  // Check every minute
}
```

For more efficient real-time processing, consider implementing a custom `LoaderInterface` that tracks file positions or uses file watching.

## Using with Cron Jobs

Automated log analysis via cron:

```php
#!/usr/bin/env php
<?php
// analyze-logs.php

require_once __DIR__ . '/vendor/autoload.php';

// Setup agent
$agent = new LogInspectorAgent($platform, [$tool]);

// Index logs from last hour
$since = date('Y-m-d H:i:s', strtotime('-1 hour'));
// ... index logs ...

// Run analysis
$analyses = [
    'errors' => $agent->ask('What errors occurred in the last hour?'),
    'performance' => $agent->ask('Any performance issues in the last hour?'),
    'security' => $agent->ask('Any security concerns in the last hour?'),
];

// Send report
$report = "Hourly Log Analysis - " . date('Y-m-d H:i') . "\n\n";
foreach ($analyses as $type => $result) {
    $report .= strtoupper($type) . ":\n";
    $report .= $result->getContent() . "\n\n";
}

mail('ops@example.com', 'Log Analysis Report', $report);
```

Add to cron:
```bash
0 * * * * /path/to/analyze-logs.php
```

## Next Steps

- **[Conversational Debugging](chat-interface.md)**: Multi-turn conversations
- **[Request Tracing](request-tracing.md)**: Trace requests across services
- **[Multi-Tool Usage](multi-tool-usage.md)**: Combine multiple tools
- **[Production Setup](production-setup.md)**: Production deployment
