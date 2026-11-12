---
sidebar_position: 3
title: Configuration
---

# MCP Server Configuration

The server reads environment variables, plus a `.env` file in the package root if one exists
(copy `.env.dist`).

| Variable | Default | Description |
|---|---|---|
| `LOG_INSPECTOR_PROVIDER` | `openai` | Chat model provider: `openai`, `anthropic` or `ollama` |
| `LOG_INSPECTOR_MODEL` | `gpt-5.4-mini` / `claude-sonnet-5-5` / `llama3.1` | Chat model used by the agent and the tool explanations |
| `LOG_INSPECTOR_EMBEDDING_PROVIDER` | the chat provider (`ollama` when it is `anthropic`) | `openai` or `ollama` (Anthropic has no embedding models) |
| `LOG_INSPECTOR_EMBEDDING_MODEL` | `text-embedding-3-small` / `nomic-embed-text` | Embedding model for indexing and search |
| `LOG_INSPECTOR_PATHS` | | Comma-separated log files or directories (`*.log`) indexed at startup |
| `OPENAI_API_KEY` | | Required for `openai` |
| `ANTHROPIC_API_KEY` | | Required for `anthropic` |
| `OLLAMA_HOST` | `http://localhost:11434` | Ollama endpoint |

### Typical setups

```bash
# OpenAI for everything
OPENAI_API_KEY=sk-... php bin/log-inspector-mcp

# Claude for reasoning, local embeddings
LOG_INSPECTOR_PROVIDER=anthropic ANTHROPIC_API_KEY=sk-ant-... \
LOG_INSPECTOR_EMBEDDING_PROVIDER=ollama php bin/log-inspector-mcp

# Fully local, no API key
ollama pull llama3.1 && ollama pull nomic-embed-text
LOG_INSPECTOR_PROVIDER=ollama php bin/log-inspector-mcp
```

Configuration errors are written to STDERR and the server exits with code 1. STDOUT stays reserved for the
MCP protocol.

## Supported log formats

`LogLineParser` turns each line into searchable metadata:

| Format | Example |
|---|---|
| Monolog / Symfony / Laravel | `[2026-11-12 14:20:16] request.ERROR: Checkout failed {"request_id":"req_c1042"} []` |
| Level only | `[2025-01-15 14:15:23] WARN: Circuit breaker opened {"trace_id":"trace_001"}` |
| PHP `error_log` | `[06-Feb-2026 14:22:31 UTC] PHP Fatal error:  Uncaught Error: ...` |
| Anything else | indexed as-is (level `info`, no timestamp), still searchable |

Extracted fields: `log_id` (`<file>_<line>`, used as the evidence id), `level`, `channel`, `timestamp` (ISO 8601),
`message`, and from the JSON context `request_id`, `trace_id`, `session_id`, `span_id` and `service`.
