# Quick Start

Get up and running with the AI Log Inspector Agent in 5 minutes!

## Step 1: Install the Package

```bash
composer require hakam/ai-log-inspector-agent
```

## Step 2: Set Up Your AI Platform

Choose your preferred AI platform and set the API key:

```bash
# For OpenAI
export OPENAI_API_KEY="sk-your-api-key-here"

# OR for Anthropic
export ANTHROPIC_API_KEY="your-api-key-here"

# OR for Ollama (local)
ollama pull llama3.2:1b
```

## Step 3: Create Your First Log Inspector

Create a file named `inspector.php`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Agent\LogInspectorAgentFactory;
use Hakam\AiLogInspector\Document\LogDocumentFactory;
use Symfony\Component\Uid\Uuid;

// 1. Create the agent using the factory
$agent = LogInspectorAgentFactory::createWithOpenAI(
    apiKey: $_ENV['OPENAI_API_KEY'],
    model: 'gpt-4o-mini'
);

// 2. Load some example logs
$logs = [
    '[2024-01-29 14:23:45] ERROR: Payment gateway timeout for order #12345',
    '[2024-01-29 14:23:46] ERROR: Stripe API returned 504 Gateway Timeout',
    '[2024-01-29 14:23:47] WARNING: Retrying payment attempt 1/3',
    '[2024-01-29 14:23:50] ERROR: Payment failed after 3 retries',
];

// 3. Index the logs
foreach ($logs as $i => $log) {
    $doc = LogDocumentFactory::createFromString(
        content: $log,
        metadata: [
            'log_id' => 'log_' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
            'timestamp' => '2024-01-29T14:23:' . (45 + $i) . 'Z',
            'level' => 'error',
            'source' => 'payment-service'
        ]
    );
    
    $agent->indexLog($doc);
}

// 4. Ask questions!
$result = $agent->ask('Why did the payment fail for order 12345?');

echo "AI Response:\n";
echo $result->getContent() . "\n";
```

Run it:

```bash
php inspector.php
```

## Expected Output

```
AI Response:
The payment failed for order #12345 due to a payment gateway timeout. 
The Stripe API returned a 504 Gateway Timeout error, and despite 3 retry 
attempts, the payment could not be completed. This indicates either network 
connectivity issues or the Stripe service experiencing problems at that time.

Evidence:
- log_000: Payment gateway timeout
- log_001: Stripe API 504 error
- log_003: Failed after retries
```

## Step 4: Try Conversational Debugging

Create `chat-inspector.php`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hakam\AiLogInspector\Chat\LogInspectorChat;
use Hakam\AiLogInspector\Chat\LogInspectorChatFactory;

// Create conversational agent
$chat = LogInspectorChatFactory::createWithOpenAI(
    apiKey: $_ENV['OPENAI_API_KEY'],
    model: 'gpt-4o-mini'
);

// Load logs (same as before)
// ... index logs here ...

// Start an investigation
$chat->startInvestigation('Payment failure investigation - Jan 29, 2024');

// Ask multiple related questions
echo "Question 1:\n";
$response1 = $chat->ask('What payment errors occurred?');
echo $response1->getContent() . "\n\n";

echo "Question 2:\n";
$response2 = $chat->followUp('How many retry attempts were made?');
echo $response2->getContent() . "\n\n";

echo "Question 3:\n";
$response3 = $chat->ask('What should we do to prevent this?');
echo $response3->getContent() . "\n\n";

// Get a summary
echo "Summary:\n";
$summary = $chat->summarize();
echo $summary->getContent() . "\n";
```

## Real-World Example: Load Logs from Files

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hakam\AiLogInspector\Agent\LogInspectorAgentFactory;
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Document\CachedLogsDocumentLoader;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;

// Create platform and store
$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
$store = new VectorLogDocumentStore();

// Create a loader pointing to your logs directory
$loader = new CachedLogsDocumentLoader('/var/log/app');

// Create indexer with the loader
$indexer = new LogFileIndexer(
    embeddingPlatform: $platform,
    model: 'text-embedding-3-small',
    loader: $loader,
    logStore: $store,
    chunkSize: 500,      // Characters per chunk
    chunkOverlap: 100    // Overlap between chunks
);

// Index a specific log file
$indexer->indexLogFile('production.log');

// Or index multiple files
$indexer->indexLogFiles(['production.log', 'errors.log']);

// Or index all .log files in the directory
$indexer->indexAllLogs();

// Create agent and query
$agent = LogInspectorAgentFactory::createWithOpenAI(
    apiKey: $_ENV['OPENAI_API_KEY']
);

$result = $agent->ask('Show me all errors from the last hour');
echo $result->getContent();
```

## Common Use Cases

### 1. Debug a Specific Request

```php
$result = $agent->ask('Show me all logs for request ID req_12345');
```

### 2. Find Error Patterns

```php
$result = $agent->ask('What are the most common errors in the logs?');
```

### 3. Performance Investigation

```php
$result = $agent->ask('What caused the slowdown at 3 PM?');
```

### 4. Security Analysis

```php
$result = $agent->ask('Are there any suspicious login attempts?');
```

## Configuration Options

### Using Different AI Platforms

```php
// OpenAI
$agent = LogInspectorAgentFactory::createWithOpenAI(
    apiKey: $_ENV['OPENAI_API_KEY'],
    model: 'gpt-4o-mini'
);

// Anthropic Claude
$agent = LogInspectorAgentFactory::createWithAnthropic(
    apiKey: $_ENV['ANTHROPIC_API_KEY'],
    model: 'claude-3-5-sonnet-20241022'
);

// Ollama (local)
$agent = LogInspectorAgentFactory::createWithOllama(
    host: 'http://localhost:11434',
    model: 'llama3.2:1b'
);
```

### Custom System Prompt

```php
$customPrompt = <<<'PROMPT'
You are a security-focused log analyzer.
Focus on detecting threats, vulnerabilities, and suspicious patterns.
Always highlight potential security risks.
PROMPT;

$agent = new LogInspectorAgent(
    $platform,
    [$logSearchTool],
    systemPrompt: $customPrompt
);
```

### Using Production Vector Stores

```php
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Symfony\AI\Store\Bridge\Chroma\ChromaStore;

// Chroma for production
$chromaStore = new ChromaStore([
    'url' => 'http://localhost:8000',
    'collection' => 'production_logs'
]);

$store = new VectorLogDocumentStore($chromaStore);

// Use in agent
$agent = new LogInspectorAgent($platform, [$tool], store: $store);
```

## Next Steps

Now that you have a working log inspector:

- **[Architecture](../core-concepts/architecture.md)**: Understand how it works under the hood
- **[Tools](../tools/log-search-tool.md)**: Learn about available tools
- **[Conversational Interface](../usage/chat-interface.md)**: Deep dive into chat capabilities
- **[Best Practices](../advanced/best-practices.md)**: Production deployment patterns
- **[Examples](../examples/basic-usage.md)**: More real-world examples

## Troubleshooting

### No Results Found

If the agent returns "No relevant logs found":

1. Verify logs are indexed: Check that `indexLog()` is called
2. Try more specific queries: "payment errors" vs "errors"
3. Check log metadata: Ensure timestamps and tags are set

### Slow Performance

If queries are slow:

1. Use a production vector store (Chroma, Pinecone)
2. Limit indexed logs to relevant time ranges
3. Consider using Ollama for local deployment

### API Rate Limits

If you hit rate limits:

1. Switch to Ollama for unlimited local queries
2. Implement caching for common queries
3. Batch multiple questions in conversations

Need more help? Check the [FAQ](../getting-started/faq.md) or open an issue on GitHub.
