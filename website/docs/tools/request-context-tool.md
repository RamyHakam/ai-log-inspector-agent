# RequestContextTool

The `RequestContextTool` enables complete request lifecycle tracing across distributed systems. It automatically correlates all logs related to a specific request, trace, or session ID, providing a chronological timeline of events.

## Overview

```php
use Hakam\AiLogInspector\Tool\RequestContextTool;

#[AsTool(
    name: 'request_context',
    description: 'Fetch all logs related to a request_id or trace_id'
)]
class RequestContextTool implements LogInspectorToolInterface
```

## Features

✅ **Request Lifecycle Tracking**: Complete visibility from request start to finish  
✅ **Distributed Tracing**: Correlate logs across multiple services  
✅ **Chronological Timeline**: Automatic time-based sorting  
✅ **Service Correlation**: See how requests flow through your system  
✅ **Session Tracking**: Group all requests for a user session

## Use Cases

### 1. Debug Failed Requests

```php
$agent = new LogInspectorAgent($platform, [$requestContextTool]);

// Trace a specific failed request
$result = $agent->ask('Show me everything that happened to request req_12345');

// AI returns complete timeline:
// 1. API Gateway received request
// 2. Auth service validated user
// 3. Payment service timeout
// 4. Retry attempt failed
// 5. Error returned to client
```

### 2. Microservices Debugging

```php
// Trace request across services
$result = $agent->ask('Debug request req_67890');

// Timeline shows:
// [14:00:00] API Gateway - Incoming request
// [14:00:01] User Service - Auth validated
// [14:00:02] Payment Service - Processing payment
// [14:00:05] Payment Service - Gateway timeout
// [14:00:06] API Gateway - Returning 504 error
```

### 3. Performance Analysis

```php
// Analyze slow requests
$result = $agent->ask('What happened during trace trace_abc123?');

// AI identifies bottlenecks:
// - Database query took 3.2s
// - External API call timeout
// - Retry logic added 5s delay
```

### 4. Session Investigation

```php
// Track user session
$result = $agent->ask('Show all activity for session sess_xyz789');

// Returns all requests in session:
// - Login request
// - Browse products
// - Add to cart
// - Checkout attempt (failed)
```

## How It Works

### 1. Identifier Detection

The tool automatically detects request identifiers:

```php
// Common patterns recognized:
$patterns = [
    'request_id' => '/req_[a-zA-Z0-9]+/',
    'trace_id' => '/trace[_-][a-zA-Z0-9]+/',
    'session_id' => '/sess[_-][a-zA-Z0-9]+/',
    'correlation_id' => '/corr[_-][a-zA-Z0-9]+/',
];
```

### 2. Log Correlation

```php
// Searches metadata for matching IDs
foreach ($allLogs as $log) {
    if (str_contains($log->metadata['request_id'], $requestId)) {
        $matchingLogs[] = $log;
    }
}
```

### 3. Timeline Construction

```php
// Sorts logs chronologically
usort($matchingLogs, fn($a, $b) => 
    strtotime($a->timestamp) <=> strtotime($b->timestamp)
);
```

### 4. Service Grouping

```php
// Groups by service for clarity
$timeline = [
    'api-gateway' => [...],
    'auth-service' => [...],
    'payment-service' => [...],
];
```

## Response Structure

```php
[
    'success' => true,
    'request_id' => 'req_12345',
    'timeline' => [
        [
            'timestamp' => '2024-01-30T14:00:00Z',
            'service' => 'api-gateway',
            'level' => 'info',
            'message' => 'Incoming request to /api/checkout',
            'log_id' => 'api_001'
        ],
        [
            'timestamp' => '2024-01-30T14:00:01Z',
            'service' => 'auth-service',
            'level' => 'info',
            'message' => 'User authenticated successfully',
            'log_id' => 'auth_001'
        ],
        [
            'timestamp' => '2024-01-30T14:00:02Z',
            'service' => 'payment-service',
            'level' => 'error',
            'message' => 'Payment gateway timeout',
            'log_id' => 'payment_001'
        ],
    ],
    'total_logs' => 3,
    'duration_ms' => 2000,
    'services_involved' => ['api-gateway', 'auth-service', 'payment-service'],
    'status' => 'failed'
]
```

## Configuration

### Indexing Logs with Request IDs

When indexing logs, ensure request IDs are included in metadata:

```php
use Hakam\AiLogInspector\Document\TextDocumentFactory;

$doc = TextDocumentFactory::createFromString(
    content: '[2024-01-30] ERROR: Payment failed',
    metadata: [
        'log_id' => 'payment_001',
        'timestamp' => '2024-01-30T14:00:02Z',
        'level' => 'error',
        'source' => 'payment-service',
        'request_id' => 'req_12345',      // ← Required
        'trace_id' => 'trace_abc',        // ← Optional
        'session_id' => 'sess_xyz',       // ← Optional
        'user_id' => 'user_456',
        'tags' => ['payment', 'timeout']
    ]
);

$indexer->indexLog($doc);
```

### Multi-Tool Setup

Combine with LogSearchTool for comprehensive debugging:

```php
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;

$agent = new LogInspectorAgent(
    $platform,
    [
        new LogSearchTool($store, $vectorizer, $platform),
        new RequestContextTool($store, $vectorizer, $platform)
    ]
);

// AI automatically chooses the right tool:
$agent->ask('What payment errors occurred?');        // Uses LogSearchTool
$agent->ask('Debug request req_12345');              // Uses RequestContextTool
```

## Real-World Examples

### E-Commerce Checkout Flow

```php
// Index logs from distributed system
$logs = [
    // API Gateway
    '[2024-01-30 14:00:00] [req_12345] INFO: Incoming POST /api/checkout',
    
    // Auth Service
    '[2024-01-30 14:00:01] [req_12345] INFO: Validating JWT token',
    '[2024-01-30 14:00:01] [req_12345] INFO: User user_456 authenticated',
    
    // Cart Service
    '[2024-01-30 14:00:02] [req_12345] INFO: Loading cart for user_456',
    '[2024-01-30 14:00:02] [req_12345] INFO: Cart contains 3 items, total $299.99',
    
    // Payment Service
    '[2024-01-30 14:00:03] [req_12345] INFO: Processing payment via Stripe',
    '[2024-01-30 14:00:05] [req_12345] ERROR: Stripe API timeout after 2s',
    '[2024-01-30 14:00:06] [req_12345] WARN: Retrying payment (attempt 1/3)',
    '[2024-01-30 14:00:08] [req_12345] ERROR: Payment failed after retries',
    
    // API Gateway
    '[2024-01-30 14:00:09] [req_12345] ERROR: Returning 504 Gateway Timeout',
];

// Query
$result = $agent->ask('What happened to request req_12345?');

// AI Response:
// "Request req_12345 failed due to a Stripe payment gateway timeout. The request
//  started at 14:00:00, proceeded through authentication and cart loading successfully,
//  but failed at the payment stage after 2 seconds. Three retry attempts were made
//  over 6 seconds, all failing. The client received a 504 Gateway Timeout at 14:00:09.
//  Total request duration: 9 seconds across 5 services."
```

### Microservices Cascade Failure

```php
$result = $agent->ask('Debug the cascade failure in trace trace_fail_001');

// Timeline reveals:
// 1. Database connection pool exhausted
// 2. User service starts timing out
// 3. Dependent services begin failing
// 4. Circuit breakers activate
// 5. System enters degraded state

// AI identifies root cause and impact
```

## Best Practices

### 1. Consistent ID Format

Use consistent request ID formats across services:

```php
// ✅ Good: Consistent format
$requestId = 'req_' . uniqid();
$traceId = 'trace_' . bin2hex(random_bytes(8));

// ❌ Bad: Inconsistent formats
$requestId = rand(1000, 9999);  // Different format per service
```

### 2. Include IDs in All Logs

Ensure every log entry includes the request ID:

```php
// ✅ Good: ID in every log
$logger->info("Processing payment", [
    'request_id' => $requestId,
    'user_id' => $userId,
    'amount' => $amount
]);

// ❌ Bad: Missing request ID
$logger->info("Processing payment");  // Can't correlate
```

### 3. Propagate IDs Across Services

Pass request IDs in headers:

```php
// Service A
$client->post('/api/service-b', [
    'headers' => [
        'X-Request-ID' => $requestId,
        'X-Trace-ID' => $traceId,
    ],
]);

// Service B
$requestId = $request->header('X-Request-ID');
$logger->info("Service B processing", ['request_id' => $requestId]);
```

### 4. Structured Logging

Use structured log formats:

```php
// ✅ Good: Structured JSON
$logger->info('Payment processed', [
    'request_id' => 'req_12345',
    'user_id' => 'user_456',
    'amount' => 299.99,
    'status' => 'success',
    'duration_ms' => 234
]);

// Easier to parse and correlate
```

## Integration Examples

### With Laravel

```php
// Middleware to add request ID
class AddRequestIdMiddleware
{
    public function handle($request, Closure $next)
    {
        $requestId = 'req_' . uniqid();
        $request->attributes->set('request_id', $requestId);
        
        Log::shareContext(['request_id' => $requestId]);
        
        return $next($request);
    }
}

// Now all logs automatically include request_id
Log::info('Processing checkout');  // Includes request_id
```

### With Symfony

```php
// Event subscriber
class RequestIdSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        $requestId = 'req_' . uniqid();
        $request->attributes->set('request_id', $requestId);
    }
    
    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }
}
```

### With Monolog Processor

```php
use Monolog\Processor\ProcessorInterface;

class RequestIdProcessor implements ProcessorInterface
{
    public function __invoke(array $record): array
    {
        if ($requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null) {
            $record['extra']['request_id'] = $requestId;
        }
        
        return $record;
    }
}

// Add to logger
$logger->pushProcessor(new RequestIdProcessor());
```

## Performance Tips

### Optimize Metadata Indexing

```php
// Index only necessary fields
$metadata = [
    'request_id' => $requestId,  // Primary identifier
    'trace_id' => $traceId,      // Secondary identifier
    'timestamp' => $timestamp,    // For sorting
    'service' => $serviceName,   // For grouping
];

// Avoid indexing large payloads in metadata
```

### Use Filters for Large Datasets

```php
// Filter by time range before correlating
$recentLogs = array_filter($logs, function($log) {
    return strtotime($log->timestamp) > strtotime('-1 hour');
});
```

## Troubleshooting

### No Logs Found for Request ID

**Problem**: `No logs found for request req_12345`

**Solutions**:
1. Verify request ID format matches your pattern
2. Check that logs include request_id in metadata
3. Ensure logs are indexed with correct metadata
4. Verify request ID wasn't truncated or modified

### Incomplete Timeline

**Problem**: Some services missing from timeline

**Solutions**:
1. Check all services propagate request IDs
2. Verify clock synchronization across services
3. Ensure all services log with request IDs
4. Check for services that don't forward headers

### Wrong Order in Timeline

**Problem**: Timeline events out of order

**Solutions**:
1. Verify timestamps are in ISO 8601 format
2. Check timezone consistency across services
3. Ensure NTP sync on all servers
4. Use UTC for all timestamps

## Next Steps

- **[LogSearchTool](log-search-tool.md)**: General log search
- **[Multi-Tool Usage](../examples/multi-tool-usage.md)**: Combining tools
- **[Best Practices](../advanced/best-practices.md)**: Production patterns
- **[Distributed Tracing](../advanced/distributed-tracing.md)**: Advanced tracing
