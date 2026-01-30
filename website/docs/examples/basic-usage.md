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
use Hakam\AiLogInspector\Document\TextDocumentFactory;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;

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
    $platform->getModel()
);
$tool = new LogSearchTool($store, $vectorizer, $platform);

// 3. Create agent
$agent = new LogInspectorAgent($platform, [$tool]);

// 4. Index some logs
$logs = [
    '[2024-01-30 14:23:45] ERROR: Payment gateway timeout for order #12345',
    '[2024-01-30 14:23:46] ERROR: Stripe API returned 504 Gateway Timeout',
    '[2024-01-30 14:23:50] ERROR: Payment failed after 3 retry attempts',
];

foreach ($logs as $i => $log) {
    $doc = TextDocumentFactory::createFromString(
        content: $log,
        metadata: [
            'log_id' => 'log_' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
            'timestamp' => '2024-01-30T14:23:' . (45 + $i) . 'Z',
            'level' => 'error',
            'source' => 'payment-service'
        ]
    );
    
    // Index the log (vectorizes and stores)
    $indexer = new \Hakam\AiLogInspector\Indexer\VectorLogDocumentIndexer(
        $platform->getPlatform(),
        $platform->getModel()->getName(),
        $store
    );
    $indexer->indexAndSaveLogs([$doc]);
}

// 5. Ask a question!
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

Read and index logs from log files:

```php
<?php

use Hakam\AiLogInspector\Service\LogProcessorService;

// Create agent (as shown above)
$agent = new LogInspectorAgent($platform, [$tool]);

// Parse and index log file
$logFile = '/var/log/app/production.log';
$handle = fopen($logFile, 'r');

while (($line = fgets($handle)) !== false) {
    // Parse log line (adjust regex for your format)
    if (preg_match('/\[([^\]]+)\] (\w+): (.+)/', $line, $matches)) {
        $timestamp = $matches[1];
        $level = strtolower($matches[2]);
        $message = $matches[3];
        
        $doc = TextDocumentFactory::createFromString(
            content: $line,
            metadata: [
                'log_id' => md5($line),
                'timestamp' => $timestamp,
                'level' => $level,
                'content' => $message
            ]
        );
        
        $indexer->indexAndSaveLogs([$doc]);
    }
}

fclose($handle);

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

Index logs from different services:

```php
// Service A logs
$serviceALogs = [
    '[2024-01-30 14:00:00] INFO: API Gateway - Incoming request',
    '[2024-01-30 14:00:05] ERROR: API Gateway - Timeout from backend',
];

foreach ($serviceALogs as $log) {
    $doc = TextDocumentFactory::createFromString(
        content: $log,
        metadata: ['source' => 'api-gateway', 'level' => 'error']
    );
    $indexer->indexAndSaveLogs([$doc]);
}

// Service B logs
$serviceBLogs = [
    '[2024-01-30 14:00:01] INFO: Auth Service - User authenticated',
    '[2024-01-30 14:00:04] ERROR: Auth Service - Database timeout',
];

foreach ($serviceBLogs as $log) {
    $doc = TextDocumentFactory::createFromString(
        content: $log,
        metadata: ['source' => 'auth-service', 'level' => 'error']
    );
    $indexer->indexAndSaveLogs([$doc]);
}

// Query across all services
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

Process and index logs in batches:

```php
$logFiles = glob('/var/log/app/*.log');
$batchSize = 100;
$batch = [];

foreach ($logFiles as $file) {
    $handle = fopen($file, 'r');
    
    while (($line = fgets($handle)) !== false) {
        $doc = TextDocumentFactory::createFromString(
            content: $line,
            metadata: ['log_id' => md5($line)]
        );
        
        $batch[] = $doc;
        
        // Index in batches
        if (count($batch) >= $batchSize) {
            $indexer->indexAndSaveLogs($batch);
            $batch = [];
            echo "Indexed batch...\n";
        }
    }
    
    fclose($handle);
}

// Index remaining
if (!empty($batch)) {
    $indexer->indexAndSaveLogs($batch);
}

echo "All logs indexed!\n";
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

Monitor logs in real-time:

```php
// Monitor log file for new entries
$logFile = '/var/log/app/production.log';
$lastPosition = 0;

while (true) {
    $handle = fopen($logFile, 'r');
    fseek($handle, $lastPosition);
    
    while (($line = fgets($handle)) !== false) {
        // Index new log entry
        $doc = TextDocumentFactory::createFromString(
            content: $line,
            metadata: ['log_id' => md5($line . microtime())]
        );
        $indexer->indexAndSaveLogs([$doc]);
        
        // Check for critical errors
        if (str_contains(strtolower($line), 'critical')) {
            $result = $agent->ask('Analyze this critical error: ' . $line);
            echo "🚨 CRITICAL: " . $result->getContent() . "\n";
        }
        
        $lastPosition = ftell($handle);
    }
    
    fclose($handle);
    sleep(1);  // Check every second
}
```

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
