# Installation

## Requirements

- **PHP**: 8.4 or higher
- **Composer**: 2.0 or higher
- **AI Platform**: OpenAI API key, Anthropic API key, or Ollama installed locally

## Install via Composer

```bash
composer require hakam/ai-log-inspector-agent
```

The package will automatically install all required dependencies:

- `symfony/ai-agent` - Core AI agent framework
- `symfony/ai-platform` - Platform abstraction layer
- `symfony/ai-store` - Vector store integration
- `symfony/ai-chat` - Conversational interface support
- Platform-specific packages (OpenAI, Anthropic, Ollama)

## Platform Setup

Choose one or more AI platforms to use with the package:

### Option 1: OpenAI (Recommended for Production)

```bash
# Install OpenAI platform
composer require symfony/ai-open-ai-platform

# Set your API key
export OPENAI_API_KEY="sk-your-api-key-here"
```

**Best for**: Production environments, highest quality semantic search, best tool calling support

### Option 2: Anthropic Claude

```bash
# Install Anthropic platform
composer require symfony/ai-anthropic-platform

# Set your API key
export ANTHROPIC_API_KEY="your-api-key-here"
```

**Best for**: Advanced reasoning, long context windows, complex analysis tasks

### Option 3: Ollama (Local/Self-Hosted)

```bash
# Install Ollama on your system
curl -fsSL https://ollama.ai/install.sh | sh

# Pull a model
ollama pull llama3.2:1b  # Lightweight, fast
# OR
ollama pull llama3.2:3b  # Better quality

# Install Ollama platform
composer require symfony/ai-ollama-platform
```

**Best for**: Development, privacy-sensitive environments, no external API costs

## Vector Store Setup

Choose a vector store based on your scale:

### Development: In-Memory Store

Perfect for development and testing:

```bash
# Already included, no additional setup needed
```

```php
use Symfony\AI\Store\Bridge\Local\InMemoryStore;

$store = new InMemoryStore();
```

**Limits**: ~10,000 log entries, resets on restart

### Production: Chroma

For production environments:

```bash
# Install Chroma store bridge
composer require symfony/ai-chroma-store

# Start Chroma server via Docker
docker run -d -p 8000:8000 chromadb/chroma:latest
```

```php
use Symfony\AI\Store\Bridge\Chroma\ChromaStore;

$store = new ChromaStore([
    'url' => 'http://localhost:8000',
    'collection' => 'application_logs'
]);
```

**Scalability**: Millions of log entries, persistent storage

### Production: Pinecone

For cloud-native deployments:

```bash
composer require symfony/ai-pinecone-store
```

```php
use Symfony\AI\Store\Bridge\Pinecone\PineconeStore;

$store = new PineconeStore([
    'api_key' => $_ENV['PINECONE_API_KEY'],
    'environment' => 'us-west1-gcp',
    'index' => 'log-vectors'
]);
```

**Scalability**: Managed service, automatic scaling, production-ready

## Verify Installation

Create a simple test file to verify everything works:

```php
<?php
// test-installation.php

require_once __DIR__ . '/vendor/autoload.php';

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Retriever\LogRetriever;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;

echo "🤖 Testing AI Log Inspector Agent Installation\n\n";

try {
    // Create platform
    $platform = LogDocumentPlatformFactory::create([
        'provider' => 'openai',  // or 'anthropic', 'ollama'
        'api_key' => $_ENV['OPENAI_API_KEY'],
        'model' => ['name' => 'gpt-4o-mini']
    ]);

    echo "✅ Platform created successfully\n";

    // Create vector store
    $store = new VectorLogDocumentStore(new InMemoryStore());
    echo "✅ Vector store created successfully\n";

    // Create retriever
    $retriever = new LogRetriever(
        embeddingPlatform: $platform->getPlatform(),
        model: 'text-embedding-3-small',
        logStore: $store
    );
    echo "✅ Retriever created successfully\n";

    // Create tool
    $tool = new LogSearchTool($store, $retriever, $platform);
    echo "✅ Tool created successfully\n";

    // Create agent
    $agent = new LogInspectorAgent($platform, [$tool]);
    echo "✅ Agent created successfully\n";

    echo "\n🎉 Installation verified! You're ready to use the AI Log Inspector Agent.\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
```

Run the test:

```bash
php test-installation.php
```

Expected output:

```
🤖 Testing AI Log Inspector Agent Installation

✅ Platform created successfully
✅ Vector store created successfully
✅ Retriever created successfully
✅ Tool created successfully
✅ Agent created successfully

🎉 Installation verified! You're ready to use the AI Log Inspector Agent.
```

## Environment Configuration

Create a `.env` file in your project root:

```env
# Choose your AI platform
OPENAI_API_KEY=sk-your-key-here
# OR
ANTHROPIC_API_KEY=your-key-here
# OR
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2:1b

# Vector store (if using external store)
CHROMA_URL=http://localhost:8000
CHROMA_COLLECTION=app_logs

# OR
PINECONE_API_KEY=your-pinecone-key
PINECONE_ENVIRONMENT=us-west1-gcp
PINECONE_INDEX=log-vectors
```

Load it in your application:

```php
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');
```

## Troubleshooting

### OpenAI API Key Invalid

```
Error: Invalid API key
```

**Solution**: Verify your API key at https://platform.openai.com/api-keys

### Ollama Connection Failed

```
Error: Connection refused to localhost:11434
```

**Solution**: Start Ollama service:
```bash
ollama serve
```

### Memory Limit Exceeded

```
Fatal error: Allowed memory size exhausted
```

**Solution**: Increase PHP memory limit:
```bash
php -d memory_limit=512M your-script.php
```

Or in `php.ini`:
```ini
memory_limit = 512M
```

### Composer Conflicts

```
Error: symfony/ai-agent conflicts with...
```

**Solution**: Update all Symfony AI packages:
```bash
composer update symfony/ai-* --with-all-dependencies
```

## Next Steps

Now that installation is complete:

- **[Quick Start Guide](quickstart.md)**: Build your first log inspector
- **[Configuration](configuration.md)**: Advanced configuration options
- **[Core Concepts](../core-concepts/architecture.md)**: Understand the architecture
