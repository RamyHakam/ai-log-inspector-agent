<?php

namespace Hakam\AiLogInspector\Test\Integration\Mcp;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Mcp\LogInspectorMcpServerFactory;
use Hakam\AiLogInspector\Mcp\LogInspectorMcpTools;
use Hakam\AiLogInspector\Test\Support\McpToolsFactory;
use Mcp\Server\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\TextResult;

/**
 * Speaks JSON-RPC to the real MCP server built by LogInspectorMcpServerFactory.
 */
final class LogInspectorMcpServerTest extends TestCase
{
    private LogInspectorMcpTools $tools;

    protected function setUp(): void
    {
        $agent = $this->createStub(LogInspectorAgent::class);
        $agent->method('ask')->willReturn(new TextResult('Root cause: DATABASE_POOL_MAX lowered from 50 to 5 in v2.14.0.'));

        $this->tools = McpToolsFactory::create($this, $agent);
        $this->tools->indexLogs(McpToolsFactory::incidentLogs());
    }

    public function testServerAdvertisesToolsResourceAndPrompt(): void
    {
        $responses = $this->exchange(
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'resources/list'],
            ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'prompts/list'],
        );

        $this->assertSame('ai-log-inspector', $responses[1]['result']['serverInfo']['name']);

        $tools = array_column($responses[2]['result']['tools'], null, 'name');
        $this->assertSame(['log_search', 'request_context', 'ask_logs', 'index_logs'], array_keys($tools));
        $this->assertSame(['query'], $tools['log_search']['inputSchema']['required']);
        $this->assertSame(['identifier'], $tools['request_context']['inputSchema']['required']);
        $this->assertTrue($tools['log_search']['annotations']['readOnlyHint']);
        $this->assertFalse($tools['index_logs']['annotations']['readOnlyHint']);

        $this->assertSame('logs://index/stats', $responses[3]['result']['resources'][0]['uri']);
        $this->assertSame('investigate_incident', $responses[4]['result']['prompts'][0]['name']);
    }

    public function testCallingToolsOverTheProtocol(): void
    {
        $responses = $this->exchange(
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'request_context', 'arguments' => ['identifier' => 'req_c1042']]],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'ask_logs', 'arguments' => ['question' => 'Why did checkout fail?']]],
            ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'resources/read', 'params' => ['uri' => 'logs://index/stats']],
            ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'prompts/get', 'params' => ['name' => 'investigate_incident', 'arguments' => ['symptom' => 'checkout 500s']]],
        );

        $trace = $responses[2]['result']['structuredContent'] ?? json_decode($responses[2]['result']['content'][0]['text'], true);
        $this->assertTrue($trace['success']);
        $this->assertStringContainsString('req_c1042', json_encode($trace));

        $answer = $responses[3]['result']['structuredContent'] ?? json_decode($responses[3]['result']['content'][0]['text'], true);
        $this->assertStringContainsString('DATABASE_POOL_MAX', $answer['answer']);

        $stats = json_decode($responses[4]['result']['contents'][0]['text'], true);
        $this->assertSame(33, $stats['total_indexed_lines']);

        $this->assertStringContainsString('checkout 500s', $responses[5]['result']['messages'][0]['content']['text']);
    }

    /**
     * Runs initialize + the given requests through the server and returns the responses keyed by id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exchange(array ...$requests): array
    {
        $messages = array_map('json_encode', [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ]],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ...$requests,
        ]);

        $transport = new class($messages) extends InMemoryTransport {
            /** @var list<string> */
            public array $sent = [];

            public function send(string $data, array $context): void
            {
                $this->sent[] = $data;
                parent::send($data, $context);
            }
        };

        LogInspectorMcpServerFactory::create($this->tools)->run($transport);

        $responses = [];
        foreach ($transport->sent as $data) {
            $message = json_decode($data, true);
            if (isset($message['id'])) {
                $this->assertArrayNotHasKey('error', $message, json_encode($message['error'] ?? null));
                $responses[$message['id']] = $message;
            }
        }

        return $responses;
    }
}
