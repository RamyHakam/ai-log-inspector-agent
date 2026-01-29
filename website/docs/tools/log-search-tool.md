# LogSearchTool

The `LogSearchTool` is the primary tool for semantic log searching with AI-powered root cause analysis. It combines vector similarity search with intelligent AI analysis to find and explain relevant log entries.

## Overview

```php
use Hakam\AiLogInspector\Tool\LogSearchTool;

#[AsTool(
    name: 'log_search',
    description: 'Search logs for relevant entries using semantic search'
)]
class LogSearchTool implements LogInspectorToolInterface
```

## Features

✅ **Semantic Search**: Understands meaning, not just keywords  
✅ **AI Analysis**: Automatically analyzes and explains findings  
✅ **Relevance Filtering**: Only returns logs above similarity threshold  
✅ **Fallback Strategy**: Keyword search when vectorization unavailable  
✅ **Structured Response**: Formatted with evidence citations

## How It Works

### 1. Vectorization

When you search for "payment errors", the tool:

```php
// Step 1: Convert query to vector
$queryDocument = TextDocumentFactory::createFromString('payment errors');
$queryVector = $vectorizer->vectorize($queryDocument); 
// Result: [0.2, 0.8, 0.1, ...] (1536 dimensions)
```

### 2. Vector Search

```php
// Step 2: Find similar logs
$results = $store->queryForVector($queryVector, ['maxItems' => 15]);

// Results sorted by similarity:
// - log_001: 0.94 ✅ (highly relevant)
// - log_005: 0.82 ✅ (relevant)
// - log_012: 0.45 ❌ (below threshold)
```

### 3. Filtering

```php
// Step 3: Filter by relevance threshold (0.7)
$relevantLogs = array_filter($results, function($log) {
    return $log->similarity >= 0.7;
});
```

### 4. AI Analysis

```php
// Step 4: AI analyzes the relevant logs
$analysis = $platform->complete("
    Analyze these log entries and explain the root cause:
    " . implode("\n", $logContents) . "
");
```

## Usage Examples

### Basic Search

```php
$agent = new LogInspectorAgent($platform, [$logSearchTool]);

$result = $agent->ask('Show me payment errors');

// Returns:
// {
//     'success' => true,
//     'reason' => 'Payment gateway timeouts occurred...',
//     'evidence_logs' => [...]
// }
```

### Specific Issue Investigation

```php
$result = $agent->ask('Why did the checkout fail at 3 PM?');

// AI automatically:
// 1. Searches for checkout-related logs around 3 PM
// 2. Filters by relevance
// 3. Analyzes root cause
// 4. Returns explanation with evidence
```

### Pattern Detection

```php
$result = $agent->ask('What database errors are happening?');

// Finds all database-related errors even if they use different wording:
// - "DB connection timeout"
// - "MySQL server has gone away"
// - "Connection pool exhausted"
// - "Query execution failed"
```

## Response Structure

The tool returns a structured array:

```php
[
    'success' => bool,              // Were relevant logs found?
    'reason' => string,             // AI-generated explanation
    'evidence_logs' => [            // Supporting log entries
        [
            'id' => 'log_001',
            'content' => '[2024-01-29] ERROR: Payment failed...',
            'timestamp' => '2024-01-29T14:23:45Z',
            'level' => 'error',
            'source' => 'payment-service',
            'tags' => ['payment', 'error']
        ],
        // ... more logs
    ],
    'search_method' => 'semantic',  // or 'keyword-based'
    'log_count' => 3,
    'query' => 'payment errors'
]
```

## Fallback Strategy

When vectorization is unavailable (e.g., Ollama without embedding support), the tool automatically falls back to intelligent keyword search:

```php
// Automatic fallback to keyword search
if (!$this->supportsVectorization) {
    return $this->performKeywordSearch($query);
}
```

### Keyword Search Features

- **Direct matching**: Exact query in log content
- **Category matching**: Matches log categories
- **Tag matching**: Searches log tags
- **Level matching**: Filters by log level (error, warning, etc.)
- **Semantic keywords**: Maps related terms (e.g., "payment" → "stripe", "transaction")

### Scoring System

```php
$score = 0;
$score += str_contains($content, $query) ? 10 : 0;      // Direct match
$score += str_contains($category, $query) ? 8 : 0;      // Category match
$score += str_contains($level, $query) ? 5 : 0;         // Level match
$score += count($tagMatches) * 6;                       // Tag matches
$score += count($semanticMatches) * 3;                  // Semantic matches
```

## Configuration

### Relevance Threshold

Adjust how strict the similarity filter is:

```php
class LogSearchTool
{
    private const RELEVANCE_THRESHOLD = 0.7; // Default: 70% similarity

    // Lower = more results (less precise)
    // Higher = fewer results (more precise)
}
```

### Maximum Results

Control how many logs are returned:

```php
class LogSearchTool
{
    private const MAX_RESULTS = 15; // Default: top 15 results
}
```

## Best Practices

### 1. Specific Queries

❌ **Too vague**: "errors"  
✅ **Better**: "payment errors in the last hour"  
✅ **Best**: "payment gateway timeout errors between 2-3 PM"

### 2. Natural Language

❌ **Boolean operators**: `(payment OR transaction) AND (error OR failure)`  
✅ **Natural language**: "What payment or transaction errors occurred?"

### 3. Context Matters

❌ **No context**: "What happened?"  
✅ **With context**: "Why did order #12345 fail?"

### 4. Use Timestamps

✅ "Show me errors from 3 PM today"  
✅ "What happened between 14:00-15:00?"  
✅ "Recent database problems"

## Performance Tips

### Optimize Vector Store

```php
// Use production vector store for large datasets
$chromaStore = new ChromaStore([
    'url' => 'http://localhost:8000',
    'collection' => 'logs'
]);

$store = new VectorLogDocumentStore($chromaStore);
```

### Limit Time Ranges

```php
// Filter logs before indexing
$recentLogs = array_filter($logs, function($log) {
    return $log->timestamp > strtotime('-24 hours');
});
```

### Cache Common Queries

```php
// Cache vectorized queries
$cacheKey = 'query_vector_' . md5($query);
$queryVector = $cache->remember($cacheKey, 3600, function() use ($query) {
    return $this->vectorizer->vectorize($query);
});
```

## Troubleshooting

### No Results Found

**Problem**: Agent returns "No relevant logs found"

**Solutions**:
1. Check logs are indexed: `$store->count()` should return > 0
2. Lower relevance threshold temporarily
3. Try more general queries
4. Verify log metadata is set correctly

### Irrelevant Results

**Problem**: Results don't match the query

**Solutions**:
1. Increase relevance threshold (0.7 → 0.8)
2. Add more specific keywords
3. Include timestamps or filters
4. Check log content quality

### Slow Performance

**Problem**: Queries take too long

**Solutions**:
1. Use Chroma or Pinecone instead of InMemory
2. Index fewer logs or use time filters
3. Reduce MAX_RESULTS
4. Enable query caching

## Integration Examples

### With Symfony Console

```php
use Symfony\Component\Console\Command\Command;

class InspectLogsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $query = $input->getArgument('query');
        $result = $this->agent->ask($query);
        
        $output->writeln('<info>' . $result->getContent() . '</info>');
        
        return Command::SUCCESS;
    }
}
```

### With API Endpoint

```php
#[Route('/api/logs/search', methods: ['POST'])]
public function search(Request $request): JsonResponse
{
    $query = $request->request->get('query');
    $result = $this->agent->ask($query);
    
    return $this->json([
        'success' => true,
        'result' => $result->getContent(),
        'logs' => $result->getEvidenceLogs()
    ]);
}
```

### With Background Jobs

```php
class LogAnalysisJob
{
    public function handle(): void
    {
        $result = $this->agent->ask('What errors occurred in the last hour?');
        
        if ($result->hasErrors()) {
            $this->sendAlert($result);
        }
    }
}
```

## Next Steps

- **[RequestContextTool](request-context-tool.md)**: Trace request lifecycles
- **[Custom Tools](../advanced/custom-tools.md)**: Build your own tools
- **[Chat Interface](../usage/chat-interface.md)**: Use conversational debugging
