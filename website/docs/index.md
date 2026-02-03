# AI Log Inspector Agent Documentation

Welcome to the complete documentation for the **AI Log Inspector Agent** - a production-ready PHP package for intelligent log analysis powered by AI.

## 🚀 Quick Links

- **[Overview](intro/overview.md)** - What is this package and why use it?
- **[Installation](getting-started/installation.md)** - Get started in minutes
- **[Quick Start](getting-started/quickstart.md)** - Your first log inspector in 5 minutes
- **[Architecture](core-concepts/architecture.md)** - How it works under the hood

## 📚 Documentation Sections

### Introduction
Get familiar with the package and its capabilities.

- **[Overview](intro/overview.md)** - Package introduction and key benefits
- **[Why AI Log Inspector?](intro/overview.md#why-this-package-matters)** - See what makes it unique
- **[Real-World Use Cases](intro/overview.md#real-world-use-cases)** - Production examples

### Getting Started
Install and configure your first log inspector.

- **[Installation](getting-started/installation.md)** - System requirements and setup
- **[Quick Start](getting-started/quickstart.md)** - 5-minute tutorial
- **[Configuration](getting-started/configuration.md)** - Advanced configuration options

### Core Concepts
Understand the architecture and design principles.

- **[Architecture](core-concepts/architecture.md)** - System design and components
- **[Vector Stores](core-concepts/vector-stores.md)** - Storage backends
- **[Semantic Search](core-concepts/architecture.md#semantic-search-explained)** - How vector search works
- **[Platform Abstraction](core-concepts/architecture.md#platform-abstraction)** - Multi-platform support

### Tools
Deep dive into available tools and how to use them.

- **[LogSearchTool](tools/log-search-tool.md)** - Semantic log search
- **[RequestContextTool](tools/request-context-tool.md)** - Request tracing
- **[Custom Tools](advanced/custom-tools.md)** - Build your own tools

### Usage & Features
Learn how to use the package effectively.

- **[Basic Usage](usage/basic-usage.md)** - Common patterns and examples
- **[Chat Interface](usage/chat-interface.md)** - Conversational debugging
- **[Log Indexing](usage/log-indexing.md)** - Load and process logs
- **[Multi-Platform](usage/multi-platform.md)** - Using different AI providers

### Advanced Topics
Production deployment and optimization.

- **[Best Practices](advanced/best-practices.md)** - Production patterns
- **[Performance Tuning](advanced/performance.md)** - Optimize for scale
- **[Security](advanced/security.md)** - Secure deployment
- **[Custom Tools](advanced/custom-tools.md)** - Extensibility guide

### API Reference
Complete API documentation.

- **[LogInspectorAgent](api-reference/log-inspector-agent.md)** - Main agent class
- **[LogInspectorChat](api-reference/log-inspector-chat.md)** - Chat interface
- **[Tools](api-reference/tools.md)** - Tool interfaces
- **[Factories](api-reference/factories.md)** - Factory classes

### Examples
Real-world code examples and patterns.

- **[Basic Usage](examples/basic-usage.md)** - Simple examples
- **[Production Setup](examples/production-setup.md)** - Full production example
- **[Laravel Integration](examples/laravel-integration.md)** - Laravel-specific examples
- **[Symfony Integration](examples/symfony-integration.md)** - Symfony-specific examples

## 🎯 Common Tasks

### How do I...

**...install the package?**  
→ See [Installation](getting-started/installation.md)

**...create my first agent?**  
→ Follow the [Quick Start](getting-started/quickstart.md) guide

**...search logs semantically?**  
→ Use [LogSearchTool](tools/log-search-tool.md)

**...trace a request across services?**  
→ Use [RequestContextTool](tools/request-context-tool.md)

**...use conversations instead of single questions?**  
→ Check out the [Chat Interface](usage/chat-interface.md)

**...switch AI providers (OpenAI/Anthropic/Ollama)?**  
→ Read [Multi-Platform Usage](usage/multi-platform.md)

**...deploy to production?**  
→ Follow [Best Practices](advanced/best-practices.md)

**...create custom tools?**  
→ See [Custom Tools Guide](advanced/custom-tools.md)

## 💡 Key Features

### 🧠 **Intelligent Analysis**
Ask natural language questions and get AI-powered answers with evidence citations.

```php
$agent->ask('Why did the payment fail for order #12345?');
// → AI explains root cause with supporting log entries
```

### 💬 **Conversational Debugging**
Multi-turn conversations that remember context.

```php
$chat->ask('What errors occurred?');
$chat->followUp('Were there database issues?');
$chat->ask('What was the root cause?');
```

### 🔍 **Semantic Search**
Understands meaning, not just keywords.

```php
// Finds "transaction timeout", "payment gateway error", etc.
$agent->ask('payment problems');
```

### 🛠️ **Extensible Tools**
Build your own tools for custom analysis.

```php
$agent = new LogInspectorAgent($platform, [
    $logSearchTool,
    $requestContextTool,
    $customSecurityTool // Your custom tool
]);
```

### 🎯 **Multi-Platform**
Works with OpenAI, Anthropic, Ollama, and custom platforms.

```php
// Switch providers without changing your code
$agent = LogInspectorAgentFactory::createWithOpenAI($apiKey);
// OR
$agent = LogInspectorAgentFactory::createWithAnthropic($apiKey);
// OR
$agent = LogInspectorAgentFactory::createWithOllama('llama3.2:1b');
```

## 🧪 Production Ready

✅ **13/13 tests passing** with real AI integrations  
✅ **104+ assertions** across unit, functional, and integration tests  
✅ **Real log examples** from Laravel, Kubernetes, microservices  
✅ **PHP 8.4+ compatible** with modern type system  
✅ **Symfony AI powered** using battle-tested components

## 📖 Learn by Example

### Simple Query

```php
require 'vendor/autoload.php';

$agent = LogInspectorAgentFactory::createWithOpenAI($_ENV['OPENAI_API_KEY']);

// Load logs
foreach ($logs as $log) {
    $agent->indexLog(TextDocumentFactory::createFromString($log));
}

// Ask questions
$result = $agent->ask('Show me all payment errors');
echo $result->getContent();
```

### Conversational Investigation

```php
$chat = LogInspectorChatFactory::createWithOpenAI($_ENV['OPENAI_API_KEY']);
$chat->startInvestigation('Payment incident - Jan 29');

echo $chat->ask('What payment errors occurred?')->getContent();
echo $chat->followUp('What was the root cause?')->getContent();
echo $chat->summarize()->getContent();
```

### Request Tracing

```php
$agent = new LogInspectorAgent($platform, [
    new LogSearchTool($store, $retriever, $platform),
    new RequestContextTool($store, $retriever, $platform)
]);

// Trace complete request lifecycle
$result = $agent->ask('Debug request req_12345');
// → Shows all logs across all services for that request
```

## 🤝 Contributing

This is an open-source project. Contributions are welcome!

- **Report bugs**: [GitHub Issues](https://github.com/RamyHakam/ai-log-inspector-agent/issues)
- **Suggest features**: [GitHub Discussions](https://github.com/RamyHakam/ai-log-inspector-agent/discussions)
- **Submit PRs**: Fork and create pull requests

## 📝 License

MIT License - see [LICENSE](../LICENSE) file for details.

## 🆘 Need Help?

- **Documentation**: You're reading it!
- **Examples**: Check the [examples/](examples/basic-usage.md) section
- **Issues**: [GitHub Issues](https://github.com/RamyHakam/ai-log-inspector-agent/issues)
- **Email**: ramy.hakam@gmail.com

---

**Ready to get started?** → Begin with the [Quick Start Guide](getting-started/quickstart.md)

**Want to understand the architecture?** → Read the [Architecture Overview](core-concepts/architecture.md)

**Looking for examples?** → Browse the [Examples](examples/basic-usage.md) section
