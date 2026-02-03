# Multi-Tool Usage

Learn how to combine `LogSearchTool` and `RequestContextTool` for comprehensive log analysis and debugging.

## Why Use Multiple Tools?

Different tools excel at different tasks:

- **LogSearchTool**: Best for general error search, pattern discovery, keyword matching
- **RequestContextTool**: Best for tracing specific requests, debugging distributed systems

The AI agent automatically selects the right tool based on your query!

## Setup

Create an agent with both tools:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizer;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;

// Setup platform
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'openai',
    'api_key' => $_ENV['OPENAI_API_KEY'],
    'model' => ['name' => 'gpt-4o-mini']
]);

// Create components
$store = new VectorLogDocumentStore(new InMemoryStore());
$vectorizer = new LogDocumentVectorizer(
    $platform->getPlatform(),
    $platform->getModel()
);

// Create BOTH tools
$logSearchTool = new LogSearchTool($store, $vectorizer, $platform);
$requestContextTool = new RequestContextTool($store, $vectorizer, $platform);

// Create agent with multiple tools
$agent = new LogInspectorAgent(
    $platform,
    [$logSearchTool, $requestContextTool]  // ✨ Both tools here!
);
```

## Automatic Tool Selection

The AI automatically chooses the right tool:

```php
// Uses LogSearchTool (general search)
$result = $agent->ask('What payment errors occurred?');

// Uses RequestContextTool (specific request ID)
$result = $agent->ask('Debug request req_12345');

// Uses LogSearchTool (pattern discovery)
$result = $agent->ask('Show me database timeout patterns');

// Uses RequestContextTool (trace ID)
$result = $agent->ask('What happened to trace trace_abc123?');
```

## Complete Example: E-Commerce Debugging

### Step 1: Index Logs with Request IDs

The recommended approach is to use the loader-based indexer with log files:

```php
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Document\CachedLogsDocumentLoader;

// Create a loader pointing to your logs directory
$loader = new CachedLogsDocumentLoader('/var/log/services');

// Create the indexer
$indexer = new LogFileIndexer(
    embeddingPlatform: $platform->getPlatform(),
    model: 'text-embedding-3-small',
    loader: $loader,
    logStore: $store,
    chunkSize: 500,
    chunkOverlap: 100
);

// Index logs from multiple services
// Assuming log files contain entries like:
// [2024-01-30 14:00:00] req_001 INFO: API Gateway - Incoming checkout request
// [2024-01-30 14:00:01] req_001 INFO: Payment successful for $299.99
// [2024-01-30 14:05:00] req_002 INFO: API Gateway - Incoming checkout request
// [2024-01-30 14:05:02] req_002 ERROR: Payment gateway timeout
// [2024-01-30 14:05:03] req_002 ERROR: Stripe API timeout after 2 seconds

// Index all .log files in the directory
$indexer->indexAllLogs();

// Or index specific service logs
$indexer->indexLogFiles(['api-gateway.log', 'payment-service.log']);
```

### Step 2: General Investigation (LogSearchTool)

```php
// Question without specific request ID → Uses LogSearchTool
$result = $agent->ask('What payment errors occurred today?');

echo "General Analysis:\n";
echo $result->getContent() . "\n\n";
// Output: "There were payment gateway timeout errors from Stripe API..."
```

### Step 3: Specific Request Debugging (RequestContextTool)

```php
// Question with specific request ID → Uses RequestContextTool
$result = $agent->ask('What happened to request req_002?');

echo "Request Timeline:\n";
echo $result->getContent() . "\n\n";
// Output: Complete chronological timeline of req_002
```

## Real-World Scenario: Production Incident

### Scenario: Users Report Checkout Failures

```php
// Step 1: General overview
$overview = $agent->ask('What checkout errors occurred in the last hour?');
echo "=== OVERVIEW ===\n";
echo $overview->getContent() . "\n\n";
// Uses LogSearchTool: Finds all checkout-related errors

// Step 2: Identify affected requests
// AI response mentions specific request IDs: req_102, req_105, req_108

// Step 3: Drill down into specific failure
$details = $agent->ask('Debug request req_105');
echo "=== REQUEST DETAILS ===\n";
echo $details->getContent() . "\n\n";
// Uses RequestContextTool: Shows complete timeline

// Step 4: Find root cause
$rootCause = $agent->ask('What caused the payment gateway timeouts?');
echo "=== ROOT CAUSE ===\n";
echo $rootCause->getContent() . "\n\n";
// Uses LogSearchTool: Analyzes pattern across all failures

// Step 5: Check impact
$impact = $agent->ask('How many requests were affected?');
echo "=== IMPACT ===\n";
echo $impact->getContent() . "\n";
// Uses LogSearchTool: Counts related errors
```

## Microservices Debugging

### Distributed System with Multiple Services

```php
// Index logs from various microservices
$microserviceLogs = [
    // API Gateway
    ['content' => '[req_999] API Gateway received /api/checkout', 'source' => 'api-gateway'],
    
    // Auth Service
    ['content' => '[req_999] Validating JWT token', 'source' => 'auth-service'],
    ['content' => '[req_999] User authenticated', 'source' => 'auth-service'],
    
    // Inventory Service
    ['content' => '[req_999] Checking stock for items', 'source' => 'inventory-service'],
    ['content' => '[req_999] Stock available', 'source' => 'inventory-service'],
    
    // Payment Service
    ['content' => '[req_999] Processing payment', 'source' => 'payment-service'],
    ['content' => '[req_999] ERROR: Database connection timeout', 'source' => 'payment-service'],
    
    // API Gateway
    ['content' => '[req_999] Returning 500 Internal Server Error', 'source' => 'api-gateway'],
];

// Query 1: General question (LogSearchTool)
$result = $agent->ask('What database errors occurred?');
// Finds all database-related errors across services

// Query 2: Specific request (RequestContextTool)  
$result = $agent->ask('Trace request req_999 across all services');
// Shows complete flow: API Gateway → Auth → Inventory → Payment (failed) → API Gateway

// Query 3: Service-specific (LogSearchTool)
$result = $agent->ask('What errors happened in payment-service?');
// Analyzes payment service logs specifically
```

## Combining Insights

Use both tools in sequence for comprehensive analysis:

```php
// Investigation workflow
function investigateIncident($agent, $timeRange) {
    echo "🔍 Starting incident investigation...\n\n";
    
    // Step 1: Overview with LogSearchTool
    $overview = $agent->ask("What errors occurred in $timeRange?");
    echo "📊 OVERVIEW:\n{$overview->getContent()}\n\n";
    
    // Step 2: Extract request IDs from overview response
    // (In practice, you'd parse the response)
    $affectedRequests = ['req_201', 'req_202', 'req_203'];
    
    // Step 3: Analyze each request with RequestContextTool
    foreach ($affectedRequests as $reqId) {
        $timeline = $agent->ask("Show timeline for $reqId");
        echo "📋 REQUEST $reqId:\n{$timeline->getContent()}\n\n";
    }
    
    // Step 4: Pattern analysis with LogSearchTool
    $patterns = $agent->ask("What patterns exist in these failures?");
    echo "🔁 PATTERNS:\n{$patterns->getContent()}\n\n";
    
    // Step 5: Root cause with LogSearchTool
    $rootCause = $agent->ask("What is the root cause?");
    echo "🎯 ROOT CAUSE:\n{$rootCause->getContent()}\n";
}

investigateIncident($agent, 'the last hour');
```

## Tool Selection Hints

Help the AI choose the right tool by phrasing your questions clearly:

### For LogSearchTool (General Search)

```php
$agent->ask('What errors occurred?');                    // General search
$agent->ask('Show me all timeout errors');               // Pattern search
$agent->ask('Find database connection failures');        // Keyword search
$agent->ask('What are the most common errors?');         // Aggregation
$agent->ask('Any security threats?');                    // Category search
```

### For RequestContextTool (Request Tracing)

```php
$agent->ask('Debug request req_12345');                  // Explicit request ID
$agent->ask('Trace req_67890');                          // Request trace
$agent->ask('Show me logs for trace trace_abc');         // Trace ID
$agent->ask('What happened to session sess_xyz?');       // Session ID
$agent->ask('Timeline for req_999');                     // Request timeline
```

## Advanced: Custom Analysis Pipeline

Build complex analysis workflows:

```php
class IncidentAnalyzer {
    public function __construct(
        private LogInspectorAgent $agent
    ) {}
    
    public function analyze(string $timeRange): array {
        $report = [];
        
        // 1. Get error overview (LogSearchTool)
        $errors = $this->agent->ask("All errors in $timeRange");
        $report['overview'] = $errors->getContent();
        
        // 2. Extract mentioned request IDs
        $requestIds = $this->extractRequestIds($errors->getContent());
        
        // 3. Trace each request (RequestContextTool)
        $report['request_traces'] = [];
        foreach ($requestIds as $reqId) {
            $trace = $this->agent->ask("Complete timeline for $reqId");
            $report['request_traces'][$reqId] = $trace->getContent();
        }
        
        // 4. Find patterns (LogSearchTool)
        $patterns = $this->agent->ask("Common patterns in these errors");
        $report['patterns'] = $patterns->getContent();
        
        // 5. Root cause analysis (LogSearchTool)
        $rootCause = $this->agent->ask("Root cause of the incident");
        $report['root_cause'] = $rootCause->getContent();
        
        return $report;
    }
    
    private function extractRequestIds(string $text): array {
        preg_match_all('/req_[a-z0-9]+/i', $text, $matches);
        return array_unique($matches[0]);
    }
}

// Usage
$analyzer = new IncidentAnalyzer($agent);
$report = $analyzer->analyze('14:00 to 15:00');

print_r($report);
```

## Performance Optimization

When using multiple tools, optimize for performance:

```php
// ✅ Good: Specific queries
$agent->ask('Debug request req_12345');                  // Fast, uses RequestContextTool
$agent->ask('Payment errors in last 10 minutes');        // Fast, limited scope

// ❌ Avoid: Vague, broad queries
$agent->ask('Tell me everything about everything');      // Slow, unclear tool choice
$agent->ask('All logs');                                 // Too broad
```

## Error Handling

Handle tool failures gracefully:

```php
try {
    $result = $agent->ask('Debug request req_unknown');
    echo $result->getContent();
} catch (\Exception $e) {
    // If RequestContextTool fails (no logs for request)
    echo "Request not found. Trying general search...\n";
    
    $result = $agent->ask('Any errors related to req_unknown?');
    echo $result->getContent();
}
```

## Next Steps

- **[LogSearchTool](../tools/log-search-tool.md)**: Deep dive into search capabilities
- **[RequestContextTool](../tools/request-context-tool.md)**: Master request tracing
- **[Chat Interface](chat-interface.md)**: Conversational debugging
- **[Best Practices](../advanced/best-practices.md)**: Production patterns
