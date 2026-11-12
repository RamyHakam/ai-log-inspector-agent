---
sidebar_position: 2
title: Connect a Client
---

# Connect an MCP Client

All clients start the same STDIO server, `bin/log-inspector-mcp`. Configuration is passed as environment variables
(see [Configuration](configuration.md)).

## Claude Code

The repository contains an `.mcp.json`, so opening a clone in Claude Code is enough. Approve the `log-inspector`
server when Claude Code asks. It indexes `examples/incident-demo/logs` and reads your API key from `.env`.

To use it with **your own** application's logs from any project:

```bash
claude mcp add log-inspector \
  -e OPENAI_API_KEY=sk-... \
  -e LOG_INSPECTOR_PATHS=/path/to/your-app/var/log \
  -- php /path/to/ai-log-inspector-agent/bin/log-inspector-mcp
```

Then ask, for example:

- *"Use log-inspector to find out why checkout failed this afternoon."*
- *"Trace req_c1057 with log-inspector and tell me where it got stuck."*
- *"Index /var/log/myapp/worker.log with log-inspector and look for memory problems."*

## Cursor / Claude Desktop / other clients

Add the server to the client's MCP configuration (for example `.cursor/mcp.json` or `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "log-inspector": {
      "command": "php",
      "args": ["/path/to/ai-log-inspector-agent/bin/log-inspector-mcp"],
      "env": {
        "OPENAI_API_KEY": "sk-...",
        "LOG_INSPECTOR_PATHS": "/path/to/your-app/var/log"
      }
    }
  }
}
```

## Docker (no local PHP)

Build the image once with `docker compose build`, then point the client to `docker run -i`. Mount the log directory
read-only:

```json
{
  "mcpServers": {
    "log-inspector": {
      "command": "docker",
      "args": ["run", "-i", "--rm", "-e", "OPENAI_API_KEY",
               "-e", "LOG_INSPECTOR_PATHS=/logs",
               "-v", "/path/to/your-app/var/log:/logs:ro", "ai-log-inspector"]
    }
  }
}
```

## MCP Inspector

To browse the tools, resource and prompt interactively:

```bash
npx @modelcontextprotocol/inspector php bin/log-inspector-mcp
```

## From PHP (MCP client)

`bin/log-inspector-demo` shows how to drive the server from PHP with the SDK's client:

```php
use Mcp\Client\Client;
use Mcp\Client\Transport\StdioTransport;

$client = Client::builder()->setClientInfo('my-app', '1.0.0')->setRequestTimeout(300)->build();
$client->connect(new StdioTransport('php', ['bin/log-inspector-mcp'], getcwd(), [
    'OPENAI_API_KEY' => getenv('OPENAI_API_KEY'),
    'LOG_INSPECTOR_PATHS' => '/var/log/myapp',
]));

$result = $client->callTool('ask_logs', ['question' => 'What failed in the last deploy?']);
echo $result->content[0]->text;

$client->disconnect();
```
