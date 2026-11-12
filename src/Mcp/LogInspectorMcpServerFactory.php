<?php

namespace Hakam\AiLogInspector\Mcp;

use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;
use Mcp\Server\Server;
use Psr\Log\LoggerInterface;

/**
 * Builds an MCP server (official PHP MCP SDK) that exposes the log inspector:
 *
 *  tools     log_search, request_context, ask_logs, index_logs
 *  resource  logs://index/stats
 *  prompt    investigate_incident
 */
final class LogInspectorMcpServerFactory
{
    public const NAME = 'ai-log-inspector';
    public const VERSION = '1.0.0';

    public static function create(LogInspectorMcpTools $tools, ?LoggerInterface $logger = null): Server
    {
        return self::builder($tools, $logger)->build();
    }

    public static function builder(LogInspectorMcpTools $tools, ?LoggerInterface $logger = null): Builder
    {
        $readOnly = new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false);

        $builder = Server::builder()
            ->setServerInfo(self::NAME, self::VERSION, 'Chat with your application logs: semantic search, request tracing and root-cause analysis powered by Symfony AI.')
            ->setInstructions(<<<'TXT'
                Use these tools to investigate application logs.
                - log_search: find log entries about a topic or error (semantic search).
                - request_context: rebuild everything that happened to one request_id, trace_id or session_id.
                - ask_logs: let the server-side Symfony AI agent run a full investigation and answer.
                - index_logs: add a log file or a directory of *.log files to the index.
                Always cite log ids from the evidence. Read logs://index/stats to see what is indexed.
                TXT)
            ->addTool([$tools, 'logSearch'], 'log_search', 'Search logs', annotations: $readOnly)
            ->addTool([$tools, 'requestContext'], 'request_context', 'Trace a request', annotations: $readOnly)
            ->addTool([$tools, 'askLogs'], 'ask_logs', 'Ask the log inspector agent', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: false, openWorldHint: false))
            ->addTool([$tools, 'indexLogs'], 'index_logs', 'Index log files', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false))
            ->addResource([$tools, 'stats'], 'logs://index/stats', 'index_stats', 'Index statistics', 'Which log sources are indexed and how many lines each contributed.', 'application/json')
            ->addPrompt([$tools, 'investigateIncident'], 'investigate_incident', 'Investigate an incident');

        if (null !== $logger) {
            $builder->setLogger($logger);
        }

        return $builder;
    }
}
