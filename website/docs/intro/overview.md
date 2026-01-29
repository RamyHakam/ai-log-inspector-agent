# Overview

The **AI Log Inspector Agent** is a production-ready toolkit for building intelligent log analysis systems in PHP applications. Whether you're debugging production incidents, conducting security investigations, or performing root cause analysis, this package gives you AI-powered semantic search, conversational debugging, and automated log insights.

## Why This Package Matters

### 🧠 **Intelligent Log Analysis**
Stop writing complex grep commands or crafting intricate Elasticsearch queries just ask your logs natural questions in plain English and get instant, context-aware answers.

### ⚡ **Semantic Search & Vector Store**
Advanced vector-based similarity search understands the meaning behind your queries, not just keyword matches. Find related errors even when they use different terminology.

### 💬 **Conversational Debugging**
Multi-turn conversations that maintain context across questions. Ask follow-ups, drill down into details, and build a complete picture of what happened just like talking to a colleague.

### 🔍 **Request Tracing**
Track complete request lifecycles across distributed systems with automatic correlation using request IDs, trace IDs, or session IDs.

### 🛠️ **Tool-Based Architecture**
Extensible tool system powered by Symfony AI. The agent automatically selects the right tool for each query whether it's semantic search, request tracing, or pattern analysis.

### 🎯 **Multi-Platform Support**
Works seamlessly with OpenAI, Anthropic Claude, Ollama (local models), and any Symfony AI-compatible platform. Switch providers without changing your code.

## Key Benefits

| Benefit | Impact                                                              |
|---------|---------------------------------------------------------------------|
| **Natural Language Queries** | Ask "Why did payments fail?" instead of writing complex queries     |
| **Semantic Understanding** | Finds relevant logs even with different keywords or phrasing        |
| **Context-Aware Conversations** | Multi-turn debugging sessions that remember previous context        |
| **Request Lifecycle Tracing** | Complete visibility across microservices and distributed systems    |
| **Root Cause Analysis** | AI explains *why* errors occurred, not just *what* happened         |
| **Multiple AI Platforms** | OpenAI, Anthropic, Ollama choose what works for your infrastructure |

## Real-World Use Cases

### 🚨 **Production Incident Response**
```php
$chat = new LogInspectorChat($agent);
$chat->startInvestigation('Payment outage - Jan 29, 2024');

// Conversational debugging
$response1 = $chat->ask('What payment errors occurred between 2-3 PM?');
$response2 = $chat->followUp('Were there any database issues around that time?');
$response3 = $chat->ask('What was the root cause?');
$summary = $chat->summarize(); // Get complete incident report
```

### 🔍 **Request Debugging**
```php
$agent = new LogInspectorAgent($platform, $tools, $store);

// Trace a single request across all services
$result = $agent->ask('Show me everything that happened to request req_12345');
// AI automatically correlates logs across API gateway, auth service, payment service, etc.
```

### 🛡️ **Security Monitoring**
```php
// Detect security threats
$result = $agent->ask('Are there any suspicious login attempts in the last hour?');
// → "Detected brute force attack from IP 192.168.1.100. 156 failed attempts..."
```

### 📊 **Performance Analysis**
```php
// Investigate performance degradation
$result = $agent->ask('What caused the API slowdown at 3 PM?');
// → "Memory leak in Redis connection causing 2.5s delays..."
```

## Architecture Highlights

The package is built on a modular, tool-based architecture:

```
┌─────────────────────────────────────────────────┐
│          LogInspectorAgent                      │
│  (Orchestrates AI + Tools + Context)            │
└───────────────┬─────────────────────────────────┘
                │
        ┌───────┴────────┐
        │                │
    ┌───▼────┐     ┌────▼────────┐
    │ Tools  │     │ Vector      │
    │        │     │ Store       │
    └───┬────┘     └─────────────┘
        │
┌───────┴──────────────────────────────┐
│ • LogSearchTool (semantic search)    │
│ • RequestContextTool (tracing)       │
│ • Custom tools (extensible)          │
└──────────────────────────────────────┘
```

### **Core Components**

1. **LogInspectorAgent**: Main orchestrator that handles user queries and tool coordination
2. **LogSearchTool**: Semantic log search with AI-powered root cause analysis
3. **RequestContextTool**: Request lifecycle tracing for distributed systems
4. **LogInspectorChat**: Conversational interface with context memory
5. **Vector Store**: Semantic similarity search using embeddings
6. **Platform Abstraction**: Works with OpenAI, Anthropic, Ollama, and custom platforms

## What Makes It Different

### Traditional Log Analysis
- Manual grep commands through log files
- Complex Elasticsearch/Kibana DSL queries
- Time-consuming correlation across services
- No understanding of context or meaning
- Hours spent finding root causes

### AI Log Inspector Agent
- Natural language questions: "Why did X fail?"
- Semantic search understands intent
- Automatic correlation across services
- AI explains root causes with evidence
- Instant answers with supporting log citations

## Production-Ready Features

✅ **Comprehensive Testing**: 104+ assertions across unit, functional, and integration tests  
✅ **Multiple AI Platforms**: OpenAI, Anthropic, Ollama (local), and custom  
✅ **Fallback Strategies**: Keyword search when vectorization unavailable  
✅ **Real Log Examples**: Tested with Laravel, Kubernetes, microservices logs  
✅ **PHP 8.4+ Ready**: Modern PHP with strict types and full IDE support  
✅ **Symfony AI Framework**: Built on battle-tested Symfony components

## Quick Glimpse

```php
<?php
use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;

// Setup (one-time configuration)
$platform = LogDocumentPlatformFactory::create([
    'provider' => 'openai',
    'api_key' => $_ENV['OPENAI_API_KEY'],
    'model' => ['name' => 'gpt-4o-mini']
]);

$agent = new LogInspectorAgent($platform, [$logSearchTool, $requestContextTool]);

// Ask anything!
$result = $agent->ask('Why did the checkout fail for user 12345?');
echo $result->getContent();
// → "The checkout failed because the payment gateway timed out after 30 seconds.
//    This was caused by network connectivity issues to the Stripe API.
//    Evidence: [payment_001], [gateway_003]"
```

## Next Steps

Ready to transform your log debugging experience?

- **[Installation](../getting-started/installation.md)**: Install and configure the package
- **[Quick Start](../getting-started/quickstart.md)**: Build your first log inspector in 5 minutes
- **[Core Concepts](../core-concepts/architecture.md)**: Understand the architecture and design
- **[Usage Examples](../examples/basic-usage.md)**: Real-world examples and patterns

---

**Made with ❤️ for developers who hate digging through logs manually!**
