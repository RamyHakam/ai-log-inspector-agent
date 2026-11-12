---
title: Upgrading to Symfony AI 1.0
---

# Upgrading to Symfony AI 1.0

The package now requires **Symfony AI 1.0** (currently `1.0.0-BETA1`). Until 1.0 is stable, allow beta packages in your
project:

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require hakam/ai-log-inspector-agent
```

## What changes for you

### Chat replies are `AssistantMessage`s

`LogInspectorChat::ask()`, `followUp()`, `summarize()` and the other helpers still return an `AssistantMessage`. In
1.0 its `getContent()` returns the content **parts**, so use `asText()` for the text:

```diff
 $response = $chat->ask('What payment errors occurred?');
-echo $response->getContent();
+echo $response->asText();
```

`LogInspectorAgent::ask()` is unchanged. It returns a result whose `getContent()` is a string.

### `symfony/ai-chat` is gone

Symfony AI 1.0 removed its Chat component. `LogInspectorChat` keeps the conversation itself and stores it through
the package's own `Hakam\AiLogInspector\Chat\ConversationStoreInterface` (`setup()`, `save()`, `load()`, `drop()`).
`SessionMessageStore` implements it, so `LogInspectorChatFactory::create()` and `createSession()` work as before.
If you wrote a custom chat store, implement `ConversationStoreInterface` instead of
`Symfony\AI\Chat\MessageStoreInterface`.

`LogInspectorChat` no longer implements `Symfony\AI\Chat\ChatInterface`. Its methods (`ask`, `followUp`, `submit`,
`initiate`, ...) are unchanged, and `getMessages()` is new.

### `LogInspectorAgent::call()`

It implements the 1.0 `AgentInterface`: it accepts a string, a `UserMessage` or a `MessageBag`, and returns an
`ExecutionInterface`:

```diff
-$result = $agent->call($messages);
+$result = $agent->call($messages)->getResult();
```

### Symfony AI class moves (only if you use them directly)

| Before | After |
|---|---|
| `Symfony\AI\Store\Bridge\Local\InMemoryStore` | `Symfony\AI\Store\InMemory\Store` |
| `Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory::create()` | `Symfony\AI\Platform\Bridge\OpenAi\Factory::createPlatform()` (same for Anthropic and Ollama) |
| `$store->query($vector)` | `$store->query(new \Symfony\AI\Store\Query\VectorQuery($vector))` (the package's `queryForVector()` is unchanged) |
| `$document->metadata`, `->id`, `->score` | `getMetadata()`, `getId()`, `getScore()` |
| `Symfony\AI\Store\Document\EmbeddableDocumentInterface` | `Symfony\AI\Store\Document\DocumentInterface` |

### Ollama

The Symfony AI 1.0 Ollama catalog asks the running server for a model's capabilities. The package falls back to a
local model description when the server is not reachable, so you can build an agent (for example in tests) without
Ollama running. The actual model calls still need the server.
