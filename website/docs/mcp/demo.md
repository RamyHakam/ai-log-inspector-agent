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

# Fully local with Ollama (no API key)
docker compose --profile local up -d ollama
LOG_INSPECTOR_PROVIDER=ollama docker compose run --rm demo
```

A run with OpenAI takes about 20 seconds. Small local models give shorter, less precise answers than hosted ones.
