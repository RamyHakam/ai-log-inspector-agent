<?php

namespace Hakam\AiLogInspector\Test\Unit\Mcp;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Mcp\LogInspectorMcpTools;
use Hakam\AiLogInspector\Test\Support\McpToolsFactory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\TextResult;

final class LogInspectorMcpToolsTest extends TestCase
{
    private LogInspectorAgent $agent;
    private LogInspectorMcpTools $tools;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(LogInspectorAgent::class);
        $this->tools = McpToolsFactory::create($this, $this->agent);
    }

    public function testIndexLogsIndexesEveryLogFileInDirectory(): void
    {
        $result = $this->tools->indexLogs(McpToolsFactory::incidentLogs());

        $this->assertTrue($result['success']);
        $this->assertCount(4, $result['indexed_files']);
        $this->assertSame(33, $result['total_indexed_lines']);
        $this->assertSame(33, $this->tools->stats()['total_indexed_lines']);
    }

    public function testIndexLogsReportsMissingPath(): void
    {
        $result = $this->tools->indexLogs('/does/not/exist');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('/does/not/exist', $result['message']);
    }

    public function testLogSearchReturnsEvidenceWithParsedMetadata(): void
    {
        $this->tools->indexLogs(McpToolsFactory::incidentLogs());

        $result = $this->tools->logSearch('Timed out waiting for a free connection');

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['evidence_logs']);
        $ids = array_column($result['evidence_logs'], 'id');
        $this->assertContains('checkout_api_0006', $ids, 'parsed log ids are used as evidence ids');
    }

    public function testRequestContextRebuildsTraceInChronologicalOrder(): void
    {
        $this->tools->indexLogs(McpToolsFactory::incidentLogs());

        $result = $this->tools->requestContext('trace_pay_9001');

        $this->assertTrue($result['success']);
        $contents = array_column($result['logs'] ?? $result['evidence_logs'], 'content');
        $this->assertGreaterThanOrEqual(6, \count($contents));
        foreach ($contents as $content) {
            $this->assertStringContainsString('trace_pay_9001', $content);
        }
        $this->assertNotEmpty(array_filter($contents, static fn (string $c) => str_contains($c, 'Customer charged but order not confirmed')));

        $timestamps = array_map(static fn (string $c) => substr($c, 1, 19), $contents);
        $sorted = $timestamps;
        sort($sorted);
        $this->assertSame($sorted, $timestamps, 'trace is returned in chronological order');
    }

    public function testAskLogsReturnsTheAgentAnswer(): void
    {
        $this->agent->expects($this->once())
            ->method('ask')
            ->with('Why did checkout fail?')
            ->willReturn(new TextResult('**Root Cause**: DATABASE_POOL_MAX lowered from 50 to 5.'));

        $this->assertSame(
            ['answer' => '**Root Cause**: DATABASE_POOL_MAX lowered from 50 to 5.'],
            $this->tools->askLogs('Why did checkout fail?'),
        );
    }

    public function testInvestigateIncidentPromptEmbedsTheSymptom(): void
    {
        $messages = $this->tools->investigateIncident('checkout returns 500');

        $this->assertSame('user', $messages[0]['role']);
        $this->assertStringContainsString('checkout returns 500', $messages[0]['content']);
        $this->assertStringContainsString('request_context', $messages[0]['content']);
    }
}
