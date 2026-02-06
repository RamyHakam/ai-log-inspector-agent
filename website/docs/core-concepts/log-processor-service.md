# LogProcessorService

The `LogProcessorService` is a flexible service for processing and indexing log data from various sources. It provides a unified interface for working with different log formats and indexing strategies.

## Architecture Overview

### Abstract Base Class: `AbstractLogIndexer`

All indexers extend this base class which provides:
- Common constructor with embedding platform, model, loader, store, and chunking parameters
- Validation of embedding support
- Initialization of Symfony AI indexer
- Configurable text transformers
- Helper methods for accessing components

### Concrete Indexers

1. **LogDocumentIndexer** - For in-memory documents
   - Uses `InMemoryLoader` by default
   - Best for processing documents already in memory
   - Methods: `indexLogDocument()`, `indexLogDocuments()`

2. **LogFileIndexer** - For filesystem-based logs
   - Requires a filesystem-aware loader (e.g., `FileLoader`, `DirectoryLoader`)
   - Best for indexing log files from disk
   - Methods: `indexLogFile()`, `indexLogFiles()`, `indexAllLogs()`

## Quick Start

### Basic Usage with LogDataDTO

```php
<?php

use Hakam\AiLogInspector\Service\LogProcessorService;
use Hakam\AiLogInspector\DTO\LogDataDTO;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;

$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
$store = new VectorLogDocumentStore();

$service = new LogProcessorService(
    platform: $platform,
    embeddingModel: 'text-embedding-3-small',
    store: $store
);

// Process log data (DTO or arrays)
$logData = [
    new LogDataDTO(
        message: 'Payment failed',
        level: 'ERROR',
        timestamp: new DateTimeImmutable()
    ),
    [
        'message' => 'User logged in',
        'level' => 'INFO',
        'timestamp' => new DateTimeImmutable(),
    ],
];

$service->processData($logData);
```

## Usage Examples

### 1. Process LogDocuments

Process LogDocument instances directly:

```php
<?php

use Hakam\AiLogInspector\Document\LogDocumentFactory;

// Multiple documents from arrays
$docs = [
    LogDocumentFactory::createFromData([
        'message' => 'Payment failed',
        'level' => 'ERROR',
        'context' => ['transaction_id' => 'txn_123'],
    ]),
    LogDocumentFactory::createFromData([
        'message' => 'User logged in',
        'level' => 'INFO',
        'context' => ['user_id' => 456],
    ]),
];

$service->processDocuments($docs);

// Single document
$doc = LogDocumentFactory::createFromData([
    'message' => 'Database timeout',
    'level' => 'ERROR',
]);
$service->processSingleDocument($doc);
```

### 2. Process LogDataDTO or Arrays

Use the new DTO-based approach for structured log data with rich semantic content:

```php
<?php

use Hakam\AiLogInspector\DTO\LogDataDTO;

$logData = [
    new LogDataDTO(
        message: 'Payment gateway error',
        level: 'ERROR',
        timestamp: new DateTimeImmutable(),
        channel: 'payment',
        context: [
            'user_id' => 123,
            'amount' => 99.99,
            'gateway' => 'stripe',
            'error_code' => 'card_declined',
        ]
    ),
    // Or use plain arrays
    [
        'message' => 'API request completed',
        'level' => 'INFO',
        'timestamp' => new DateTimeImmutable(),
        'context' => [
            'url' => '/api/users',
            'method' => 'GET',
            'duration' => 150,
            'status_code' => 200,
        ],
    ],
];

$service->processData($logData);

// Single data entry
$service->processSingleData([
    'message' => 'Cache miss',
    'level' => 'DEBUG',
    'context' => ['key' => 'user:123'],
]);
```

### 3. Use Custom LogDocumentIndexer

Create a custom indexer with specific configuration:

```php
<?php

use Hakam\AiLogInspector\Indexer\LogDocumentIndexer;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;

// Create custom indexer with specific settings
$customIndexer = new LogDocumentIndexer(
    embeddingPlatform: $platform,
    model: 'text-embedding-3-large',  // Different model
    loader: new InMemoryLoader(),
    logStore: $store,
    chunkSize: 1000,     // Larger chunks
    chunkOverlap: 200    // More overlap
);

// Pass to service
$service = new LogProcessorService(
    platform: $platform,
    embeddingModel: 'text-embedding-3-small',
    store: $store,
    indexer: $customIndexer
);

$service->processWithCustomIndexer($customIndexer);
```

### 4. Use LogFileIndexer

Index log files from the filesystem:

```php
<?php

use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Symfony\AI\Store\Document\Loader\DirectoryLoader;

// Create file loader for log directory
$fileLoader = new DirectoryLoader('/var/log/app');

// Create file indexer
$fileIndexer = new LogFileIndexer(
    embeddingPlatform: $platform,
    model: 'text-embedding-3-small',
    loader: $fileLoader,
    logStore: $store
);

// Index specific file
$fileIndexer->indexLogFile('app.log');

// Index multiple files
$fileIndexer->indexLogFiles(['app.log', 'error.log']);

// Index all logs in directory
$fileIndexer->indexAllLogs([
    'pattern' => '*.log',
    'recursive' => true
]);
```

### 5. Message Queue Consumer

Use in a message consumer (e.g., RabbitMQ, Kafka):

```php
<?php

class LogIndexConsumer
{
    private LogProcessorService $logProcessor;

    public function __construct(
        PlatformInterface $platform,
        VectorLogStoreInterface $store
    ) {
        $this->logProcessor = new LogProcessorService(
            platform: $platform,
            embeddingModel: 'text-embedding-3-small',
            store: $store,
            chunkSize: 800,
            chunkOverlap: 150
        );
    }

    public function consume(array $message): void
    {
        // Convert message to LogDataDTO
        $logData = [
            'message' => $message['text'],
            'level' => $message['severity'],
            'timestamp' => new DateTimeImmutable($message['timestamp']),
            'context' => $message['metadata'] ?? [],
            'enriched_data' => [
                'consumer' => 'rabbitmq',
                'queue' => $message['queue_name'],
            ],
        ];

        // Index with rich semantic content
        $this->logProcessor->processSingleData($logData);
    }
}
```

### 6. Batch Processing with Filters

Process only specific log levels:

```php
<?php

$allLogs = [
    ['message' => 'Info message', 'level' => 'INFO'],
    ['message' => 'Error message', 'level' => 'ERROR'],
    ['message' => 'Warning message', 'level' => 'WARNING'],
];

// Process only ERROR level logs
$service->processDataWithLevel($allLogs, 'ERROR');
```

## Semantic Content Generation

The service uses `LogDocumentFactory::createSemanticContent()` which generates rich, human-readable text optimized for vector search.

### Example Output

```
Log Message: Payment gateway timeout | Severity: ERROR | Channel: payment | 
Timestamp: 2025-01-15 10:30:00 UTC | HTTP Request: POST /api/payments/charge 
Status: 500 | Request ID: req_abc123 | User ID: user_789 | Roles: customer, premium | 
Exception: GatewayTimeoutException | Message: Request timed out after 30s | 
Location: PaymentService.php:156 | Has stack trace | Performance: Duration: 30500ms
```

### Benefits

This semantic content:
- **Gets vectorized** for similarity search
- **Includes contextual labels** for better matching
- **Captures key information** from all sources (message, context, enriched data)
- **Optimized for natural queries** like "payment timeout errors"

### Content vs Metadata

In Symfony AI Store's `TextDocument`:

**Content** (vectorized):
- The actual text that gets converted to embeddings
- Used for semantic similarity search
- Should be rich, descriptive text

**Metadata** (not vectorized):
- Stored as structured data
- Used for filtering after search
- Contains IDs, timestamps, boolean flags, etc.

## API Reference

### Constructor

```php
public function __construct(
    PlatformInterface $platform,
    string $embeddingModel,
    VectorLogStoreInterface $store,
    int $chunkSize = 500,
    int $chunkOverlap = 100,
    ?LogIndexerInterface $indexer = null,
)
```

**Parameters:**
- `platform` - The AI platform for generating embeddings
- `embeddingModel` - Model name (e.g., 'text-embedding-3-small')
- `store` - Vector store for storing embeddings
- `chunkSize` - Size of text chunks for splitting (default: 500)
- `chunkOverlap` - Overlap between chunks (default: 100)
- `indexer` - Optional custom indexer instance

### Methods

#### `processDocuments(array $documents, array $options = []): void`

Process and index an array of LogDocuments.

#### `processSingleDocument(LogDocument $document, array $options = []): void`

Process a single LogDocument.

#### `processData(array $logData, array $options = []): void`

Process log data from DTOs or arrays with rich semantic content.

#### `processSingleData(LogDataDTO|array $logData, array $options = []): void`

Process a single log data entry (DTO or array).

#### `processDataWithLevel(array $logData, string $level, array $options = []): void`

Process log data filtered by log level.

#### `processWithCustomIndexer(LogIndexerInterface $customIndexer, array $options = []): void`

Process with a custom indexer instance.

#### `getIndexer(): ?LogIndexerInterface`

Get the indexer instance (returns null if not provided).

## Best Practices

### 1. Use DTOs for Rich Semantic Content

```php
// ✅ Good - Rich semantic content
$logData = new LogDataDTO(
    message: 'Payment processing failed',
    level: 'ERROR',
    context: [
        'user_id' => 123,
        'amount' => 99.99,
        'exception_class' => 'PaymentGatewayException',
    ]
);

// ❌ Less optimal - Missing context
$logData = ['message' => 'Error', 'level' => 'ERROR'];
```

### 2. Batch Processing

```php
// ✅ Good - Process in batches
$service->processData($logDataBatch);

// ❌ Less efficient - Individual processing in loop
foreach ($logDataBatch as $logData) {
    $service->processSingleData($logData);
}
```

### 3. Choose Appropriate Chunk Sizes

```php
// For short log messages (< 200 chars)
$service = new LogProcessorService(
    platform: $platform,
    embeddingModel: 'text-embedding-3-small',
    store: $store,
    chunkSize: 200,
    chunkOverlap: 50
);

// For long log messages with stack traces
$service = new LogProcessorService(
    platform: $platform,
    embeddingModel: 'text-embedding-3-small',
    store: $store,
    chunkSize: 1000,
    chunkOverlap: 200
);
```

## Benefits of Single Service Approach

1. **Flexibility** - Consumers can inject their own dependencies and configuration
2. **DRY** - No code duplication between file and document processors
3. **Extensibility** - Easy to add support for new data sources (DTO, arrays, custom formats)
4. **Consistency** - Same interface regardless of data source
5. **Testability** - Single service to mock/test

## Related Documentation

- [Architecture Overview](./architecture.md)
- [LogSearchTool](../tools/log-search-tool.md)
- [Basic Usage Examples](../examples/basic-usage.md)
