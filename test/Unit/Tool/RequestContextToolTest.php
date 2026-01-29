<?php

namespace Hakam\AiLogInspector\Test\Unit\Tool;

use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Tool\RequestContextTool;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\Uid\Uuid;

class RequestContextToolTest extends TestCase
{
    private VectorLogStoreInterface $store;
    private LogDocumentVectorizerInterface $vectorizer;
    private LogDocumentPlatformInterface $platform;
    private RequestContextTool $tool;

    protected function setUp(): void
    {
        $this->store = $this->createMock(VectorLogStoreInterface::class);
        $this->vectorizer = $this->createMock(LogDocumentVectorizerInterface::class);
        $this->platform = $this->createMock(LogDocumentPlatformInterface::class);
        
        $this->tool = new RequestContextTool(
            $this->store,
            $this->vectorizer,
            $this->platform
        );
    }

    public function testInvokeWithEmptyIdentifier(): void
    {
        $result = $this->tool->__invoke('');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('identifier is required', $result['message']);
        $this->assertArrayHasKey('examples', $result);
        $this->assertContains('req_12345', $result['examples']);
        $this->assertContains('trace-abc-def-123', $result['examples']);
        $this->assertContains('session_xyz789', $result['examples']);
    }

    public function testInvokeWithWhitespaceOnlyIdentifier(): void
    {
        $result = $this->tool->__invoke('   ');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('identifier is required', $result['message']);
        $this->assertEquals('none', $result['search_method']);
    }

    public function testSuccessfulVectorBasedSearch(): void
    {
        $identifier = 'req_12345';
        
        // Mock vectorization support test
        $testVectorDoc = $this->createMockVectorDocument('test', 'test content');
        $this->vectorizer
            ->expects($this->exactly(2)) // Once for test, once for actual search
            ->method('vectorizeLogTextDocuments')
            ->willReturn([$testVectorDoc]);

        // Mock the actual search
        $mockResults = [
            $this->createMockVectorDocument('log1', '[2024-01-15 14:23:45] INFO: Processing request req_12345', [
                'timestamp' => '2024-01-15 14:23:45',
                'level' => 'info',
                'source' => 'user-service',
                'tags' => ['request']
            ]),
            $this->createMockVectorDocument('log2', '[2024-01-15 14:23:50] ERROR: Database timeout for req_12345', [
                'timestamp' => '2024-01-15 14:23:50',
                'level' => 'error',
                'source' => 'user-service',
                'tags' => ['database', 'error']
            ])
        ];

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn($mockResults);

        // Mock AI analysis
        $mockAnalysisResult = $this->createMock(ResultInterface::class);
        $mockAnalysisResult
            ->method('getContent')
            ->willReturn('Summary: Request req_12345 failed due to database timeout. Root Cause: Database connection pool exhausted. Confidence: High');

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($mockAnalysisResult);

        $result = $this->tool->__invoke($identifier);

        $this->assertTrue($result['success']);
        $this->assertEquals($identifier, $result['identifier']);
        $this->assertEquals('vector-based', $result['search_method']);
        $this->assertCount(2, $result['evidence_logs']);
        $this->assertEquals(2, $result['total_logs']);
        $this->assertArrayHasKey('request_timeline', $result);
        $this->assertArrayHasKey('services_involved', $result);
        $this->assertArrayHasKey('time_span', $result);
        $this->assertEquals('High', $result['confidence']);
    }

    public function testKeywordBasedSearchFallback(): void
    {
        $identifier = 'session_abc123';
        
        // Mock vectorization failure
        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willThrowException(new \Exception('Vectorization not supported'));

        // Mock keyword search
        $mockResults = [
            $this->createMockVectorDocument('log1', '[2024-01-15 15:00:00] INFO: User login session_abc123', [
                'timestamp' => '2024-01-15 15:00:00',
                'level' => 'info',
                'source' => 'auth-service'
            ])
        ];

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn($mockResults);

        $result = $this->tool->__invoke($identifier);

        $this->assertTrue($result['success']);
        $this->assertEquals('keyword-based', $result['search_method']);
        $this->assertEquals($identifier, $result['identifier']);
    }

    public function testNoResultsFound(): void
    {
        $identifier = 'nonexistent_123';
        
        // Mock empty search results
        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willThrowException(new \Exception('Vectorization not supported'));

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([]);

        $result = $this->tool->__invoke($identifier);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No logs found', $result['reason']);
        $this->assertEquals($identifier, $result['identifier']);
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertContains('Check if the identifier format is correct', $result['suggestions']);
    }

    public function testChronologicalOrdering(): void
    {
        $identifier = 'order_456';
        
        // Mock vectorization failure to test keyword search
        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willThrowException(new \Exception('Vectorization not supported'));

        // Create logs with different timestamps (out of chronological order)
        $mockResults = [
            $this->createMockVectorDocument('log1', '[2024-01-15 14:25:00] INFO: Order order_456 completed', [
                'timestamp' => '2024-01-15 14:25:00',
                'level' => 'info'
            ]),
            $this->createMockVectorDocument('log2', '[2024-01-15 14:23:00] INFO: Processing order_456', [
                'timestamp' => '2024-01-15 14:23:00', 
                'level' => 'info'
            ]),
            $this->createMockVectorDocument('log3', '[2024-01-15 14:24:00] INFO: Validating order_456', [
                'timestamp' => '2024-01-15 14:24:00',
                'level' => 'info'
            ])
        ];

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn($mockResults);

        $result = $this->tool->__invoke($identifier);

        $this->assertTrue($result['success']);
        $evidenceLogs = $result['evidence_logs'];
        
        // Verify chronological ordering
        $this->assertEquals(1, $evidenceLogs[0]['chronological_order']);
        $this->assertEquals(2, $evidenceLogs[1]['chronological_order']);
        $this->assertEquals(3, $evidenceLogs[2]['chronological_order']);
        
        // Verify timestamps are in order
        $this->assertStringContainsString('14:23:00', $evidenceLogs[0]['content']);
        $this->assertStringContainsString('14:24:00', $evidenceLogs[1]['content']);
        $this->assertStringContainsString('14:25:00', $evidenceLogs[2]['content']);
    }

    public function testTimeSpanCalculation(): void
    {
        $identifier = 'trace_789';
        
        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willThrowException(new \Exception('Vectorization not supported'));

        $mockResults = [
            $this->createMockVectorDocument('log1', '[2024-01-15 14:00:00] Start trace_789', [
                'timestamp' => '2024-01-15 14:00:00'
            ]),
            $this->createMockVectorDocument('log2', '[2024-01-15 14:03:30] End trace_789', [
                'timestamp' => '2024-01-15 14:03:30'
            ])
        ];

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn($mockResults);

        $result = $this->tool->__invoke($identifier);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('time_span', $result);
        
        $timeSpan = $result['time_span'];
        $this->assertEquals('2024-01-15 14:00:00', $timeSpan['start_time']);
        $this->assertEquals('2024-01-15 14:03:30', $timeSpan['end_time']);
        $this->assertEquals(210, $timeSpan['duration_seconds']); // 3m 30s = 210s
        $this->assertEquals('3m 30s', $timeSpan['duration_human']);
    }

    public function testServicesInvolvedTracking(): void
    {
        $identifier = 'req_multi_service';
        
        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willThrowException(new \Exception('Vectorization not supported'));

        $mockResults = [
            $this->createMockVectorDocument('log1', 'API Gateway req_multi_service', [
                'source' => 'api-gateway',
                'timestamp' => '2024-01-15 14:00:00'
            ]),
            $this->createMockVectorDocument('log2', 'User Service req_multi_service', [
                'source' => 'user-service',
                'timestamp' => '2024-01-15 14:00:01'
            ]),
            $this->createMockVectorDocument('log3', 'Payment Service req_multi_service', [
                'source' => 'payment-service',
                'timestamp' => '2024-01-15 14:00:02'
            ]),
            $this->createMockVectorDocument('log4', 'User Service req_multi_service again', [
                'source' => 'user-service',
                'timestamp' => '2024-01-15 14:00:03'
            ])
        ];

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn($mockResults);

        $result = $this->tool->__invoke($identifier);

        $this->assertTrue($result['success']);
        
        $servicesInvolved = $result['services_involved'];
        $this->assertEquals(1, $servicesInvolved['api-gateway']);
        $this->assertEquals(2, $servicesInvolved['user-service']);
        $this->assertEquals(1, $servicesInvolved['payment-service']);
    }

    public function testLogLevelsTracking(): void
    {
        $identifier = 'debug_request';
        
        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willThrowException(new \Exception('Vectorization not supported'));

        $mockResults = [
            $this->createMockVectorDocument('log1', 'Info debug_request', ['level' => 'info']),
            $this->createMockVectorDocument('log2', 'Warning debug_request', ['level' => 'warning']),
            $this->createMockVectorDocument('log3', 'Error debug_request', ['level' => 'error']),
            $this->createMockVectorDocument('log4', 'Info again debug_request', ['level' => 'info'])
        ];

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn($mockResults);

        $result = $this->tool->__invoke($identifier);

        $this->assertTrue($result['success']);
        
        $logLevels = $result['log_levels'];
        $this->assertEquals(2, $logLevels['info']);
        $this->assertEquals(1, $logLevels['warning']);
        $this->assertEquals(1, $logLevels['error']);
    }

    public function testExceptionHandling(): void
    {
        $identifier = 'error_test';
        
        // Mock vectorization failure for capability test
        $this->vectorizer
            ->method('vectorizeLogTextDocuments')
            ->willThrowException(new \Exception('Vectorization failed'));

        // Mock store failure for keyword search fallback
        $this->store
            ->method('queryForVector')
            ->willThrowException(new \Exception('Store query failed'));

        $result = $this->tool->__invoke($identifier);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Request context search failed', $result['message']);
        $this->assertEquals($identifier, $result['identifier']);
    }

    private function createMockVectorDocument(string $id, string $content, array $additionalMetadata = []): VectorDocument
    {
        $metadataArray = array_merge([
            'content' => $content,
            'log_id' => $id,
            'timestamp' => null,
            'level' => 'info',
            'source' => 'unknown',
            'tags' => []
        ], $additionalMetadata);

        // Score is cosine distance (lower = more similar)
        $doc = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3, 0.4, 0.5]),
            new Metadata($metadataArray),
            0.1 // Low distance = high relevance
        );
        
        return $doc;
    }
}