---
sidebar_position: 4
title: Incident Demo
---

# Incident Demo

`examples/incident-demo/logs` contains the logs of a simulated production incident across four services:

| Time | What happened | Where |
|---|---|---|
| 14:02 | `checkout-api` v2.14.0 deployed, **`DATABASE_POOL_MAX` changed from 50 to 5** | `deploy.log` |
| 14:18 | Connection pool near capacity | `checkout-api.log` |
| 14:20 | Checkouts fail: `Timed out waiting for a free connection` | `checkout-api.log` |
| 14:21 | Stripe charges a customer, but saving the order hits a lock wait timeout: **charged, no order** | `payment-service.log` |
| 14:25 | Error rate 38%, 412 failed orders | `checkout-api.log` |
| 14:33 | Unrelated brute-force attempt (a distractor) | `security.log` |
| 14:47 | Rollback to v2.13.4, pool back to 50 | `deploy.log` |

The answer requires correlating the deploy, the database and the payment service. The two charged-but-unconfirmed
orders (ORD-55201, ORD-55263) are only visible in the payment service logs.

## Run it

`bin/log-inspector-demo` is an MCP **client** (built on `mcp/sdk`). It starts the MCP server, lists its tools, reads
the stats resource, calls `log_search` and `request_context`, and finally asks `ask_logs` your question.

```bash
# Docker
echo "OPENAI_API_KEY=sk-..." > .env
docker compose run --rm demo

# Local PHP 8.4
composer install
php bin/log-inspector-demo "Were any customers charged without getting an order?"

```

A run with OpenAI takes 20 to 40 seconds.

## Fully local (no API key)


```bash
ollama pull llama3.2 && ollama pull nomic-embed-text
LOG_INSPECTOR_PROVIDER=ollama php bin/log-inspector-demo
# or from Docker, using the Ollama on your machine:
LOG_INSPECTOR_PROVIDER=ollama OLLAMA_HOST=http://host.docker.internal:11434 docker compose run --rm demo
```

Everything in Docker also works, but Docker on macOS has no GPU, so expect several minutes per answer:

```bash
docker compose --profile local up -d --wait ollama   # first start pulls llama3.2 + nomic-embed-text (~2.3 GB)
LOG_INSPECTOR_PROVIDER=ollama docker compose run --rm demo
```

Small local models give shorter, less precise answers than hosted ones. With 8 GB or more of memory, try
`LOG_INSPECTOR_MODEL=llama3.1` (pull it first).
