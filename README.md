# 🤖 AI Log Inspector Agent

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Symfony AI](https://img.shields.io/badge/Symfony%20AI-Experimental-orange.svg)](https://symfony.com/doc/current/ai.html)
[![Status](https://img.shields.io/badge/Status-Experimental-red.svg)](#)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

> ⚠️ **EXPERIMENTAL PACKAGE** - This package is built on Symfony AI which is currently experimental. Not recommended for production use. No backward compatibility guarantees.

An intelligent PHP library that provides AI-powered log analysis capabilities through semantic search, pattern matching, and automated incident investigation.

## ✨ Features

🔍 **Semantic Log Search** - Find relevant logs using natural language queries  
🧠 **AI-Powered Analysis** - Get intelligent explanations of errors and incidents  
⚡ **Pattern Matching Fallback** - Reliable analysis even when AI services are unavailable  
🛠️ **Tool-Based Architecture** - Extensible framework built on Symfony AI Agent  
📊 **Vector Store Integration** - Efficient similarity search with relevance filtering  

## 🚀 Quick Start

### Installation

```bash
composer require hakam/ai-log-inspector-agent
```

### Basic Usage

```php
<?php

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Agent\Tool\LogSearchTool;
use Symfony\AI\Platform\InMemoryPlatform;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;

// Setup AI platform and model
$platform = new YourAIPlatform(); // Configure your AI platform
$model = new Model('your-model', [
    Capability::TOOL_CALLING,
    Capability::INPUT_TEXT,
    Capability::OUTPUT_TEXT
]);
$store = new InMemoryStore();

// Create the agent
$agent = new LogInspectorAgent($platform, $model, $store);

// Ask questions about your logs
$result = $agent->ask('Why did the last checkout request fail?');
echo $result->getContent();

// Or use the tool directly
$logSearchTool = new LogSearchTool($store, $platform, $model);
$analysis = $logSearchTool->__invoke('payment gateway timeout error');

print_r($analysis);
// Output:
// [
//     'success' => true,
//     'reason' => 'Payment gateway timeout caused checkout failure...',
//     'evidence_logs' => [
//         ['id' => 'log_001', 'content' => '...', 'level' => 'error', ...]
//     ]
// ]
```


## 📖 Detailed Usage

### Setting Up Log Data

First, populate your vector store with log data:

```php
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\Uid\Uuid;

// Convert your logs to vector documents
$logData = [
    'content' => '[2024-01-15 14:23:45] ERROR: Payment gateway timeout',
    'metadata' => [
        'log_id' => 'payment_001',
        'timestamp' => '2024-01-15T14:23:45Z', 
        'level' => 'error',
        'source' => 'payment-service',
        'tags' => ['payment', 'timeout', 'gateway']
    ]
];

// Generate vector embedding (using your AI platform)
$vector = $platform->embed($logData['content']);

// Store in vector store
$document = new VectorDocument(
    Uuid::v4(),
    new Vector($vector),
    new Metadata($logData['metadata'])
);
$store->add($document);
```

### Advanced Queries

The agent supports sophisticated natural language queries:

```php
// Root cause analysis
$result = $agent->ask('What caused the 500 errors in the payment service?');

// Timeline investigation  
$result = $agent->ask('What happened before the database connection failure?');

// Impact assessment
$result = $agent->ask('How many users were affected by the authentication issues?');

// Pattern discovery
$result = $agent->ask('Are there any recurring memory leak patterns?');
```

### Direct Tool Usage

For programmatic access, use the LogSearchTool directly:

```php
$tool = new LogSearchTool($store, $platform, $model);

// Simple error search
$result = $tool->__invoke('database connection timeout');

// Complex incident analysis
$result = $tool->__invoke('analyze the cascade of failures starting at 14:23');

// Security investigation
$result = $tool->__invoke('suspicious authentication patterns from IP 192.168.1.100');
```

### Response Format

All tool responses follow a consistent structure:

```php
[
    'success' => true,                    // Whether relevant logs were found
    'reason' => 'Payment gateway timeout caused...',  // AI analysis explanation
    'evidence_logs' => [                  // Supporting log entries
        [
            'id' => 'log_001',
            'content' => '[2024-01-15] ERROR: ...',
            'timestamp' => '2024-01-15T14:23:45Z',
            'level' => 'error',
            'source' => 'payment-service', 
            'tags' => ['payment', 'timeout']
        ]
    ]
]
```

## 🧪 Testing

The package includes comprehensive test coverage:

```bash
# Install dependencies
composer install

# Run all tests
vendor/bin/phpunit

# Run specific test suites
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

## ⚙️ Configuration

### AI Platform Setup

The agent requires an AI platform that supports:
- **Text embeddings** for semantic search
- **Text generation** for analysis  
- **Tool calling** capability for agent orchestration

Example platform configurations:

```php
// OpenAI Platform
use Symfony\AI\Platform\OpenAI\OpenAIPlatform;
$platform = new OpenAIPlatform($apiKey);

// Anthropic Platform  
use Symfony\AI\Platform\Anthropic\AnthropicPlatform;
$platform = new AnthropicPlatform($apiKey);

// Custom Platform
class CustomPlatform implements PlatformInterface {
    // Implement your platform integration
}
```

### Model Configuration

```php
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Capability;

$model = new Model('gpt-4', [
    Capability::TOOL_CALLING,     // Required for agent functionality
    Capability::INPUT_TEXT,       // Text input processing
    Capability::OUTPUT_TEXT,      // Text response generation
]);
```

### Vector Store Options

```php
// In-memory store (for testing/small datasets)
$store = new InMemoryStore();

// Persistent stores (for larger datasets)
use Symfony\AI\Store\Bridge\Chroma\ChromaStore;
use Symfony\AI\Store\Bridge\Pinecone\PineconeStore;

$store = new ChromaStore($config);
$store = new PineconeStore($config);
```

## 🔧 Customization

### Custom System Prompts

```php
$customPrompt = 'You are a specialized security log analyzer. Focus on threat detection and security incidents.';

$agent = new LogInspectorAgent(
    $platform,
    $model, 
    $store,
    $customPrompt
);
```



## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes
4. Commit your changes: `git commit -m 'Add amazing feature'`
5. Push to the branch: `git push origin feature/amazing-feature`
6. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built on the experimental [Symfony AI](https://symfony.com/doc/current/ai.html) framework
- Inspired by modern observability and incident response practices  
- **Note**: This is an experimental package for research and development purposes

## 📞 Support

- 🐛 **Issues**: [GitHub Issues](https://github.com/hakam/ai-log-inspector-agent/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/hakam/ai-log-inspector-agent/discussions)

---

**Made with ❤️ for experimental AI-powered log analysis**