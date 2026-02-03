# Architecture

The AI Log Inspector Agent follows a modular, tool-based architecture built on top of Symfony AI components. This design enables flexible AI-powered log analysis with semantic search, conversational debugging, and extensible tool integration.

## High-Level Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    Application Layer                      │
│   (Your Code: Controllers, Commands, Services)            │
└──────────────┬───────────────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────────────┐
│              LogInspectorAgent / LogInspectorChat         │
│   Orchestrates AI interactions, tool selection, context   │
└───────┬──────────────────────────────────┬───────────────┘
        │                                  │
        ▼                                  ▼
┌─────────────────┐              ┌──────────────────────┐
│   Tool Layer    │              │   Platform Layer     │
│                 │              │                      │
│ • LogSearchTool │              │ • OpenAI Platform    │
│ • RequestTool   │◄─────────────│ • Anthropic Platform │
│ • Custom Tools  │              │ • Ollama Platform    │
└────────┬────────┘              └──────────────────────┘
         │
         ▼
┌──────────────────────────────────────────────────────────┐
│                   Retriever Layer                         │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ LogRetriever (wraps Symfony Retriever)              │ │
│  │  • Vectorizes query → Searches store → Returns     │ │
│  └─────────────────────────────────────────────────────┘ │
└───────────────────────────┬──────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────┐
│                    Indexer Layer                          │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ VectorLogDocumentIndexer                            │ │
│  │  • Loader → Transformers → Vectorizer → Store      │ │
│  └─────────────────────────────────────────────────────┘ │
└───────────────────────────┬──────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────┐
│                    Storage Layer                          │
│                                                           │
│  ┌────────────────┐         ┌──────────────────────┐    │
│  │ Vector Store   │         │  Message Store       │    │
│  │ • InMemory     │         │  (Chat History)      │    │
│  │ • Chroma       │         │                      │    │
│  │ • Pinecone     │         └──────────────────────┘    │
│  └────────────────┘                                      │
└──────────────────────────────────────────────────────────┘
```

## Core Components

### 1. LogInspectorAgent

The main orchestrator that handles user queries and coordinates tools and AI platforms.

```php
use Hakam\AiLogInspector\Agent\LogInspectorAgent;

$agent = new LogInspectorAgent(
    platform: $platform,        // AI platform (OpenAI, Anthropic, etc.)
    tools: [$logSearchTool],   // Tools the agent can use
    systemPrompt: $customPrompt // Optional custom behavior
);

$result = $agent->ask('Why did payment fail?');
```

**Responsibilities:**
- Parse and understand user questions
- Select appropriate tools based on query
- Coordinate multi-tool workflows
- Format and return results

### 2. Tool System

Tools are specialized components that the agent can invoke to perform specific tasks.

#### LogSearchTool
Semantic search across log entries with AI-powered root cause analysis.

```php
#[AsTool(
    name: 'log_search',
    description: 'Search logs for relevant entries'
)]
class LogSearchTool
{
    public function __invoke(string $query): array
    {
        // 1. Vectorize query
        // 2. Search vector store
        // 3. Filter by relevance
        // 4. AI analysis
        // 5. Return structured results
    }
}
```

#### RequestContextTool
Trace complete request lifecycles across distributed systems.

```php
#[AsTool(
    name: 'request_context',
    description: 'Fetch all logs related to a request_id or trace_id'
)]
class RequestContextTool
{
    public function __invoke(string $requestId): array
    {
        // 1. Find all logs with matching request ID
        // 2. Sort chronologically
        // 3. Build timeline
        // 4. Return complete context
    }
}
```

### 3. Platform Abstraction

Platform-agnostic AI integration supporting multiple providers.

```php
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;

// OpenAI
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'openai',
    'api_key' => $apiKey,
    'model' => ['name' => 'gpt-4o-mini']
]);

// Anthropic
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'anthropic',
    'api_key' => $apiKey,
    'model' => ['name' => 'claude-3-5-sonnet-20241022']
]);

// Ollama (local)
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'ollama',
    'host' => 'http://localhost:11434',
    'model' => ['name' => 'llama3.2:1b']
]);
```

**Features:**
- Unified interface across providers
- Model capability detection
- Automatic fallbacks
- Configuration abstraction

### 4. Vector Store

Semantic similarity search using embeddings.

```php
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;

$store = new VectorLogDocumentStore($internalStore);

// Add documents
$store->add($vectorDocument);

// Query by similarity
$results = $store->queryForVector($queryVector, ['maxItems' => 10]);
```

**Supported Backends:**
- **InMemoryStore**: Development/testing
- **ChromaStore**: Production-ready, self-hosted
- **PineconeStore**: Managed cloud service

### 5. Indexer Pipeline

The `VectorLogDocumentIndexer` provides a complete pipeline for loading, transforming, vectorizing, and storing log documents.

```php
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Document\CachedLogsDocumentLoader;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;

// Create a loader for your log files
$loader = new CachedLogsDocumentLoader('/var/log/app');

// Create the indexer
$indexer = new LogFileIndexer(
    embeddingPlatform: $platform,
    model: 'text-embedding-3-small',
    loader: $loader,
    logStore: new VectorLogDocumentStore(),
    chunkSize: 500,      // Characters per text chunk
    chunkOverlap: 100    // Overlap between chunks for context
);

// Index methods
$indexer->indexLogFile('app.log');           // Single file
$indexer->indexLogFiles(['a.log', 'b.log']); // Multiple files
$indexer->indexAllLogs();                    // All .log files
```

**Process:**
1. Loader reads log files from disk
2. TextSplitTransformer chunks large documents
3. Vectorizer converts chunks to embeddings via AI API
4. VectorDocuments stored in Vector Store

### 6. Retriever

The `LogRetriever` handles vectorization and search in a single step, wrapping Symfony's `Retriever` internally:

```php
use Hakam\AiLogInspector\Retriever\LogRetriever;

// The retriever handles vectorization + search in one step
$retriever = new LogRetriever(
    embeddingPlatform: $platform->getPlatform(),
    model: 'text-embedding-3-small',
    logStore: $store
);

// Retrieve semantically similar logs
$results = $retriever->retrieve('payment timeout errors', ['maxItems' => 15]);
```

The `LogRetriever` implements `LogRetrieverInterface` and is used by both `LogSearchTool` and `RequestContextTool` for semantic search. The Vectorizer is still used internally by the Indexer pipeline for document ingestion.

## Data Flow

### Indexing Flow

```
Log Files (*.log)
    ├─▶ CachedLogsDocumentLoader
    │       └─▶ Reads files from disk
    │
    ├─▶ TextSplitTransformer
    │       └─▶ Chunks large documents (500 chars, 100 overlap)
    │
    ├─▶ Vectorizer
    │       └─▶ Calls AI embedding API (e.g., text-embedding-3-small)
    │
    └─▶ VectorLogDocumentStore
            └─▶ Stores VectorDocuments for similarity search
```

### Single Question Flow

```
User Query
    ├─▶ LogInspectorAgent
    │       ├─▶ Selects Tool (LogSearchTool)
    │       │       ├─▶ Retrieve via LogRetriever
    │       │       ├─▶ Filter Results by Relevance
    │       │       └─▶ AI Analysis
    │       └─▶ Format Response
    └─▶ Return to User
```

### Conversational Flow

```
Investigation Start
    ├─▶ LogInspectorChat
    │       ├─▶ Initialize with System Prompt
    │       └─▶ Store in Message Store
    │
User Question 1
    ├─▶ Add to Message History
    ├─▶ LogInspectorAgent (with context)
    ├─▶ Store Response
    │
User Question 2 (Follow-up)
    ├─▶ Add to Message History
    ├─▶ LogInspectorAgent (with full context)
    └─▶ Store Response with correlation
```

## Semantic Search Explained

### How Vector Similarity Works

```php
// Example: Query vs Log Comparison

// Query: "payment failed"
$queryVector = [0.2, 0.8, 0.1, 0.9, 0.3, ...]; // 1536 dimensions

// Log 1: "PaymentGatewayException: timeout"
$log1Vector = [0.2, 0.7, 0.1, 0.9, 0.4, ...]; // Similar!

// Log 2: "User logged in successfully"
$log2Vector = [0.9, 0.1, 0.8, 0.2, 0.1, ...]; // Different!

// Cosine similarity calculation
$similarity1 = cosineSimilarity($queryVector, $log1Vector); // 0.94 ✅
$similarity2 = cosineSimilarity($queryVector, $log2Vector); // 0.23 ❌

// Only keep similarity > 0.7
$relevantLogs = [$log1]; // Log 1 passes threshold
```

### Advantages Over Keyword Search

| Keyword Search | Semantic Search |
|----------------|-----------------|
| "payment" only matches exact word | Understands "transaction", "checkout", "billing" |
| No context understanding | Knows "failed" relates to "error", "exception" |
| Case-sensitive issues | Case-insensitive by nature |
| No synonym handling | Automatic synonym matching |
| Boolean operators required | Natural language queries |

## Extensibility

### Custom Tools

Create your own tools to extend functionality:

```php
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'security_analyzer',
    description: 'Detect security threats in logs'
)]
class SecurityAnalyzerTool implements LogInspectorToolInterface
{
    public function __invoke(string $timeRange): array
    {
        // Custom security analysis logic
        return [
            'threats' => [...],
            'severity' => 'high',
            'recommendations' => [...]
        ];
    }
}

// Register with agent
$agent = new LogInspectorAgent(
    $platform,
    [$logSearchTool, $requestContextTool, new SecurityAnalyzerTool()]
);
```

### Custom Platforms

Integrate with your own AI infrastructure:

```php
use Symfony\AI\Platform\PlatformInterface;

class CustomAIPlatform implements PlatformInterface
{
    public function complete(Message $message, array $options = []): ResultInterface
    {
        // Your custom AI implementation
    }
    
    public function embed(string $text, string $model): Vector
    {
        // Your embedding logic
    }
}

$platform = new LogDocumentPlatform(
    new CustomAIPlatform(),
    new Model('your-model', [Capability::TEXT, Capability::EMBEDDING])
);
```

## Performance Considerations

### Vector Store Scaling

| Store Type | Capacity | Query Speed | Use Case |
|------------|----------|-------------|----------|
| InMemory | ~10K logs | < 10ms | Development |
| Chroma | Millions | ~50ms | Production (self-hosted) |
| Pinecone | Billions | ~100ms | Enterprise (managed) |

### Token Optimization

```php
// Only send relevant logs to AI (reduces costs)
$relevantLogs = array_filter($allLogs, fn($log) => $log->similarity > 0.7);

// Truncate very long log entries
$truncatedContent = substr($log->content, 0, 1000);

// Batch questions in conversations
$chat->ask('Question 1'); // Reuses context
$chat->ask('Question 2'); // No re-indexing
$chat->ask('Question 3'); // Efficient!
```

### Caching Strategies

```php
// Cache vectorized queries
$cacheKey = hash('sha256', $query);
if ($cached = $cache->get($cacheKey)) {
    return $cached;
}

// Cache frequent log patterns
$frequentPatterns = $cache->remember('frequent_errors', 3600, function() {
    return $this->analyzePatterns();
});
```

## Security Considerations

### API Key Management

```php
// ❌ Never hardcode keys
$apiKey = 'sk-abc123...'; // BAD!

// ✅ Use environment variables
$apiKey = $_ENV['OPENAI_API_KEY'];

// ✅ Or secrets manager
$apiKey = $secretsManager->get('openai-api-key');
```

### Log Data Privacy

```php
// Sanitize sensitive data before indexing
$sanitizer = new LogSanitizer();
$cleanLog = $sanitizer->removePII($rawLog); // Remove emails, IPs, etc.

// Encrypt sensitive fields
$metadata['user_id'] = encrypt($userId);
```

### Access Control

```php
// Restrict agent access per user
if (!$user->can('view-production-logs')) {
    throw new AccessDeniedException();
}

// Filtered vector store
$store = new FilteredVectorStore($baseStore, $user->getPermissions());
```

## Next Steps

- **[Tools](../tools/log-search-tool.md)**: Deep dive into available tools
- **[Vector Stores](vector-stores.md)**: Choose the right storage backend
- **[Best Practices](../advanced/best-practices.md)**: Production deployment patterns
- **[Performance Tuning](../advanced/performance.md)**: Optimize for scale
