<?php

namespace Hakam\AiLogInspector\Test\Support;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Indexer\LogDocumentIndexer;
use Hakam\AiLogInspector\Mcp\LogInspectorMcpTools;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Retriever\LogRetriever;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\TextResult;

/**
 * Real store, retriever, indexer and tools on a deterministic embedding platform;
 * only the chat model and the agent are test doubles.
 */
final class McpToolsFactory
{
    public static function create(TestCase $test, LogInspectorAgent $agent): LogInspectorMcpTools
    {
        $embeddings = FakeEmbeddingPlatform::create();
        $store = new VectorLogDocumentStore();
        $retriever = new LogRetriever($embeddings, 'fake-embeddings', $store);

        $brain = (new \ReflectionMethod($test, 'createStub'))->invoke($test, LogDocumentPlatformInterface::class);
        $brain->method('__invoke')->willReturn(new TextResult('Database connection pool exhausted after the deploy.'));

        return new LogInspectorMcpTools(
            new LogSearchTool($store, $retriever, $brain),
            new RequestContextTool($store, $retriever, $brain),
            $agent,
            new LogDocumentIndexer($embeddings, 'fake-embeddings', logStore: $store),
        );
    }

    public static function incidentLogs(): string
    {
        return \dirname(__DIR__, 2).'/examples/incident-demo/logs';
    }
}
