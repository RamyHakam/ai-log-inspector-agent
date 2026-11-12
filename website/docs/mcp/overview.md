---
sidebar_position: 1
title: MCP Server
---

# MCP Server

The log inspector ships an **MCP server** built on the official PHP MCP SDK
([`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk)). Any MCP client, such as **Claude Code**, **Cursor**,
**Claude Desktop** or the **MCP Inspector**, can then search your logs, trace requests and run a root-cause
investigation without you writing a line of PHP.

```bash
php bin/log-inspector-mcp      # STDIO transport
```

## How it fits together

```text
 MCP client (Claude Code, Cursor, Inspector, bin/log-inspector-demo)
        │  JSON-RPC over STDIO
        ▼
 bin/log-inspector-mcp ── mcp/sdk Server (LogInspectorMcpServerFactory)
        │
        ├── log_search ──────┐
        ├── request_context ─┼──► LogRetriever ──► vector store (Symfony AI Store)
        ├── ask_logs ──► LogInspectorAgent (Symfony AI Agent, tool-calling loop)
        │                     └── calls log_search / request_context itself
        └── index_logs ──► LogLineParser ──► LogDocumentIndexer (embeddings) ──► vector store
```

The server is assembled from env vars by `LogInspectorRuntime` (no framework or config files needed), and
registered with `LogInspectorMcpServerFactory`. Both live in `src/Mcp/`.

## Tools

| Tool | Arguments | Returns |
|---|---|---|
| `log_search` | `query` (string) | `success`, `reason` (AI explanation), `evidence_logs[]` (`id`, `content`, `timestamp`, `level`, `source`, `tags`), `search_method`, `log_count` |
| `request_context` | `identifier` (a `request_id`, `trace_id` or `session_id`) | `success`, `reason`, `root_cause`, `evidence_logs[]` in chronological order, `request_timeline`, `services_involved`, `time_span` |
| `ask_logs` | `question` (string) | `answer`: a report written by the server-side agent (summary, root cause, timeline, impact, evidence ids, actions) |
| `index_logs` | `path` (a log file or a directory of `*.log` files) | `success`, `indexed_files` (lines per file), `total_indexed_lines` |

`log_search`, `request_context` and `ask_logs` are annotated **read-only**; `index_logs` is not.

### `log_search` vs `ask_logs`

- **`log_search` / `request_context`** return the raw evidence. The **client's** model (for example Claude in Claude
  Code) reasons over it. This is the most flexible way to use the tools, with a strong client model.
- **`ask_logs`** runs the full **Symfony AI agent on the server**. It picks its own tools, always checks for
  deployments and config changes before naming a root cause, and returns a finished report. Use it with lightweight
  clients, or when you want the same investigation regardless of which client asks.

## Resource

| URI | Content |
|---|---|
| `logs://index/stats` | JSON: indexed sources and number of lines per source |

## Prompt

| Name | Arguments | Purpose |
|---|---|---|
| `investigate_incident` | `symptom` | A step-by-step playbook that tells the client model to search the symptom, trace affected requests, look for upstream causes and changes, and answer in a fixed format citing log ids |

## Example session

```text
> Use log-inspector to find out why checkout failed this afternoon and which customers need a refund.

  Called log-inspector 4 times

  What happened
  - 14:02–14:04 v2.14.0 went out, and the config change set DATABASE_POOL_MAX from 50 to 5 (deploy_0002)
  - 14:18 onward All 5 connections in use, with up to 41 requests waiting; checkouts failed with 500/504 errors
  - 14:25 Error rate 38%, 412 failed orders (checkout_api_0014)
  - 14:47–14:49 Rolled back to v2.13.4 (deploy_0004, deploy_0005)

  Customers to refund (charged in Stripe, order never confirmed)
  | ORD-55201 | ch_3QxL9 | 249.00 | payment_service_0005 |
  | ORD-55263 | ch_3QxM2 |  59.00 | payment_service_0006 |
```

This is the bundled [incident demo](demo.md). Next: [connect your client](clients.md) and
[configure providers and log paths](configuration.md).

## Limits

- The index is **in memory**. It is built when the server starts (`LOG_INSPECTOR_PATHS`) and extended with
  `index_logs`, and it is gone when the server stops.
- Each file contributes at most 5,000 lines, and every non-empty line becomes one document.
- The transport is STDIO. For a remote setup, run the server in Docker on the machine that has the logs.
