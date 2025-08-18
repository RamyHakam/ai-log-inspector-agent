# 🤖 AI Log Inspector Agent

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Symfony AI](https://img.shields.io/badge/Symfony%20AI-Experimental-orange.svg)](https://symfony.com/doc/current/ai.html)
[![Status](https://img.shields.io/badge/Status-Experimental-red.svg)](#)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
<span style="font-size: 2em;">🇵🇸</span> **Free Palestine**

<div align="center">

### 🤖💬 Chat With Your Logs Using Smart AI 💬🤖

**Transform debugging from tedious to effortless!** 🔍 → ⚡  
Stop digging through dashboards and complex queries. Just **ask your logs directly** in plain English.

</div>

---

## 🐌 Traditional vs ⚡ AI-Powered

<table>
<tr>
<td width="50%">

### 🐌 Traditional Way
- Open **Kibana/ElasticSearch** dashboards
- Write complex **Datadog queries**
- Manual `grep` through log files
- Filter thousands of log entries
- Correlate timestamps & request IDs by hand
- Spend hours finding root causes

**Result:** Hours of manual work 😩

</td>
<td width="50%">

### ⚡ AI Agent Way
```php
// Just ask naturally!
$agent->ask("Why did payments fail?");
$agent->ask("What caused the 3 PM outage?");
$agent->ask("Show me timeout patterns");
$agent->ask("How many users affected?");
```

**Result:** Instant intelligent answers 🧠

</td>
</tr>
</table>

---

## 💬 Real Examples - Ask Anything!

```php
$agent = new LogInspectorAgent($platform, $model, $store);

// 🚨 Checkout Issues
$result = $agent->ask('Why did the last checkout request fail?');
// → "Payment gateway timeout after 30 seconds. The last 3 checkout attempts 
//    all failed with 'gateway_timeout' errors between 14:23-14:25."

// 🔍 Database Problems  
$result = $agent->ask('Show me all database errors from the last hour');
// → "Found 12 database connection failures. Pattern shows connection pool 
//    exhaustion starting at 15:30, affecting user authentication service."

// 🌊 Performance Issues
$result = $agent->ask('What caused the sudden spike in API response times?');
// → "Memory leak in Redis connection causing 2.5s delays. Started after 
//    deployment at 13:45, affecting 847 requests per minute."

// 🔐 Security Monitoring
$result = $agent->ask('Are there any suspicious login attempts?');
// → "Detected brute force attack from IP 192.168.1.100. 156 failed login 
//    attempts in 5 minutes targeting admin accounts."

// 📊 Impact Assessment
$result = $agent->ask('How many users were affected by the outage?');
// → "Based on error logs, approximately 2,341 unique users experienced 
//    service disruption between 14:15-14:32 during the database incident."
```

> ⚠️ **EXPERIMENTAL** - Built on Symfony AI (experimental). Not for production use yet.

---

## ✨ What Makes It Special

🔍 **Semantic Search** - Understands context, not just keywords  
🧠 **AI Analysis** - Explains what happened and why  
⚡ **Lightning Fast** - Get answers in seconds, not hours  
🛠️ **Tool-Based** - Extensible architecture with Symfony AI  
📊 **Vector Powered** - Smart similarity matching  
🔄 **Fallback Ready** - Works even when AI is unavailable

---

## 🚀 Quick Start

### Install
```bash
composer require hakam/ai-log-inspector-agent
```

### Setup & Use
```php
<?php
use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;

// Configure your AI platform
$platform = new YourAIPlatform(); 
$model = new Model('your-model', [
    Capability::TOOL_CALLING,
    Capability::INPUT_TEXT,
    Capability::OUTPUT_TEXT
]);
$store = new InMemoryStore();

// Create the agent
$agent = new LogInspectorAgent($platform, $model, $store);

// Start asking questions!
$result = $agent->ask('Why did the checkout fail?');
echo $result->getContent();
```
---

### Advanced Questions
```php
// Root cause analysis
$agent->ask('What caused the 500 errors in payment service?');

// Timeline investigation  
$agent->ask('What happened before the database failure?');

// Pattern discovery
$agent->ask('Are there recurring memory leak patterns?');

// Security investigation
$agent->ask('Suspicious auth patterns from IP 192.168.1.100?');
```

### Response Structure
```php
[
    'success' => true,                    // Found relevant logs?
    'reason' => 'Payment gateway timeout caused...',  // AI explanation
    'evidence_logs' => [                  // Supporting evidence
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

---

## ⚙️ Configuration

### AI Platform Options
```php
// OpenAI
use Symfony\AI\Platform\OpenAI\OpenAIPlatform;
$platform = new OpenAIPlatform($apiKey);

// Anthropic
use Symfony\AI\Platform\Anthropic\AnthropicPlatform;
$platform = new AnthropicPlatform($apiKey);

// Custom Platform
class CustomPlatform implements PlatformInterface {
    // Your implementation
}
```

### Vector Store Options
```php
// Memory (testing)
$store = new InMemoryStore();

// Production stores
use Symfony\AI\Store\Bridge\Chroma\ChromaStore;
use Symfony\AI\Store\Bridge\Pinecone\PineconeStore;

$store = new ChromaStore($config);
$store = new PineconeStore($config);
```

### Custom System Prompts
```php
$customPrompt = 'You are a security log analyzer. Focus on threats and incidents.';

$agent = new LogInspectorAgent($platform, $model, $store, $customPrompt);
```

---

## 🤝 Contributing

1. **Fork** the repository
2. **Create** feature branch: `git checkout -b feature/amazing-feature`
3. **Commit** changes: `git commit -m 'Add amazing feature'`
4. **Push** to branch: `git push origin feature/amazing-feature`
5. **Open** Pull Request

---

<div align="center">

**Made with ❤️ for developers who hate digging through logs!**

*Transform your debugging experience today* 🚀

</div>