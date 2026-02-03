<?php

namespace Hakam\AiLogInspector\Test\Unit\Tool;

use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Retriever\LogRetrieverInterface;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Uid\Uuid;

class LogSearchToolTest extends TestCase
{
    private VectorLogStoreInterface $store;
    private LogSearchTool $tool;
    private LogRetrieverInterface $retriever;
    private LogDocumentPlatformInterface $platform;

    protected function setUp(): void
    {
        $this->store = $this->createMock(VectorLogStoreInterface::class);
        $this->retriever = $this->createMock(LogRetrieverInterface::class);
        $this->platform = $this->createMock(LogDocumentPlatformInterface::class);

        // By default, retriever fails so keyword search is used
        $this->retriever->method('retrieve')
            ->willThrowException(new \RuntimeException('Retrieval not supported'));

        $this->tool = new LogSearchTool(
            $this->store,
            $this->retriever,
            $this->platform
        );
    }

    /**
     * Helper to create a tool with working retriever (semantic search).
     */
    private function createToolWithWorkingRetriever(): void
    {
        $this->retriever = $this->createMock(LogRetrieverInterface::class);
        $this->platform = $this->createMock(LogDocumentPlatformInterface::class);

        $this->tool = new LogSearchTool(
            $this->store,
            $this->retriever,
            $this->platform
        );
    }

    public function testInvokeWithEmptyQuery(): void
    {
        $result = $this->tool->__invoke('');

        $this->assertFalse($result['success']);
        $this->assertEquals('Query parameter is required and cannot be empty. Please provide a search term to find relevant log entries.', $result['message']);
        $this->assertEmpty($result['logs']);
    }

    public function testInvokeWithWhitespaceOnlyQuery(): void
    {
        $result = $this->tool->__invoke('   ');

        $this->assertFalse($result['success']);
        $this->assertEquals('Query parameter is required and cannot be empty. Please provide a search term to find relevant log entries.', $result['message']);
        $this->assertEmpty($result['logs']);
    }

    /**
     * Test keyword-based search with AI analysis (when retriever fails).
     */
    public function testKeywordSearchWithAIAnalysis(): void
    {
        $query = 'payment error';
        $logContent = 'Payment gateway timeout during checkout';
        $analysisResult = 'Payment gateway timeout caused transaction failure';

        // Mock platform analysis
        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->stringContains('Analyze these log entries'))
            ->willReturn($platformResult);

        // Mock store search results with matching content
        $metadata = new Metadata([
            'log_id' => 'pay_001',
            'content' => $logContent,
            'message' => $logContent,
            'timestamp' => '2026-01-01 14:23:45',
            'level' => 'error',
            'category' => 'payment',
            'source' => 'payment-service',
            'tags' => ['checkout', 'payment'],
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
            $metadata,
            null
        );

        // Store is queried once for keyword search
        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        $this->assertEquals($analysisResult, $result['reason']);
        $this->assertCount(1, $result['evidence_logs']);
        $this->assertEquals('pay_001', $result['evidence_logs'][0]['id']);
        $this->assertEquals('keyword-based', $result['search_method']);
    }

    /**
     * Test semantic search when retriever works.
     */
    public function testSemanticSearchWithWorkingRetriever(): void
    {
        $this->createToolWithWorkingRetriever();

        $query = 'payment errors';
        $logContent = 'Payment gateway timeout';
        $analysisResult = 'Payment gateway timeout caused the error';

        $vector = new Vector([0.1, 0.2, 0.3]);

        // Mock platform analysis
        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($platformResult);

        // Mock store search results
        $metadata = new Metadata([
            'log_id' => 'log_12345',
            'content' => $logContent,
            'timestamp' => '2026-01-01 14:23:45',
            'level' => 'error',
            'source' => 'payment-service',
            'tags' => ['checkout', 'payment'],
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            $metadata,
            0.1
        );

        // Retriever is called for semantic search
        $this->retriever
            ->expects($this->once())
            ->method('retrieve')
            ->with($query, ['maxItems' => 15])
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        $this->assertEquals($analysisResult, $result['reason']);
        $this->assertEquals('semantic', $result['search_method']);
    }

    /**
     * Test that AI analysis failure falls back to pattern matching.
     */
    public function testAIAnalysisFailureFallsBackToPatternMatching(): void
    {
        $query = 'database connection';
        $logContent = 'Database connection failed to establish';

        // Mock platform to throw exception, triggering fallback
        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \Exception('AI analysis failed'));

        $metadata = new Metadata([
            'log_id' => 'db_001',
            'content' => $logContent,
            'message' => $logContent,
            'timestamp' => '2026-01-01 14:23:45',
            'level' => 'error',
            'category' => 'database',
            'source' => 'database-service',
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
            $metadata,
            null
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        // Pattern matching should detect "database connection failed"
        $this->assertEquals('Database connection failure', $result['reason']);
        $this->assertCount(1, $result['evidence_logs']);
        $this->assertEquals('keyword-based', $result['search_method']);
    }

    /**
     * Test search with no matching results.
     */
    public function testSearchWithNoResults(): void
    {
        $query = 'xyznonexistent123abc';

        // Return empty results from store
        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([]);

        $result = $this->tool->__invoke($query);

        // No results from store
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString('No relevant log entries found', $result['reason']);
        $this->assertEmpty($result['evidence_logs']);
        $this->assertEquals('keyword-based', $result['search_method']);
    }

    /**
     * Test multiple log entries with AI analysis.
     */
    public function testMultipleLogEntriesWithAIAnalysis(): void
    {
        $query = 'payment error';
        $analysisResult = 'Multiple payment gateway timeouts led to transaction failures';

        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($platformResult);

        // Create multiple documents with payment-related content
        $documents = [];
        for ($i = 1; $i <= 3; ++$i) {
            $metadata = new Metadata([
                'log_id' => "pay_00$i",
                'content' => "Payment gateway timeout error $i",
                'message' => "Payment gateway timeout error $i",
                'timestamp' => "2026-01-01 14:23:4$i",
                'level' => 'error',
                'category' => 'payment',
                'source' => 'payment-service',
                'tags' => [],
            ]);

            $documents[] = new VectorDocument(
                Uuid::v4(),
                new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
                $metadata,
                null
            );
        }

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn($documents);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        $this->assertEquals($analysisResult, $result['reason']);
        $this->assertCount(3, $result['evidence_logs']);
        $this->assertEquals('keyword-based', $result['search_method']);
    }

    /**
     * Test handling of documents with missing metadata fields.
     */
    public function testDocumentWithMinimalMetadata(): void
    {
        $query = 'test query';
        $analysisResult = 'Analysis of minimal log data';

        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($platformResult);

        // Document with minimal metadata but matching content
        $uuid = Uuid::v4();
        $metadata = new Metadata([
            'content' => 'test query related content',
        ]);

        $resultDocument = new VectorDocument(
            $uuid,
            new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
            $metadata,
            null
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['evidence_logs']);

        // Check fallback values for missing fields
        $log = $result['evidence_logs'][0];
        $this->assertEquals($uuid->toString(), $log['id']);
        $this->assertNull($log['timestamp']);
        $this->assertEquals('unknown', $log['level']);
        $this->assertEquals('unknown', $log['source']);
        $this->assertEquals([], $log['tags']);
    }

    /**
     * Test that tags field handles string values (converts to array).
     */
    public function testTagsStringConvertedToArray(): void
    {
        $query = 'payment';
        $analysisResult = 'Payment issue detected';

        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($platformResult);

        // Document with tags as string (edge case)
        $metadata = new Metadata([
            'log_id' => 'pay_001',
            'content' => 'Payment processing failed',
            'message' => 'Payment processing failed',
            'category' => 'payment',
            'tags' => 'payment-tag', // String instead of array
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
            $metadata,
            null
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        // Tags should be converted to array
        $this->assertIsArray($result['evidence_logs'][0]['tags']);
        $this->assertEquals(['payment-tag'], $result['evidence_logs'][0]['tags']);
    }

    /**
     * Test store exception handling.
     */
    public function testStoreExceptionHandling(): void
    {
        $query = 'test query';

        // Store fails
        $this->store
            ->expects($this->atLeast(1))
            ->method('queryForVector')
            ->willThrowException(new \Exception('Store query failed'));

        $result = $this->tool->__invoke($query);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Search failed:', $result['message']);
    }

    /**
     * Test retriever exception falls back to keyword search.
     */
    public function testRetrieverExceptionFallsBackToKeywordSearch(): void
    {
        $this->createToolWithWorkingRetriever();

        $query = 'test query';

        // Retriever fails
        $this->retriever
            ->expects($this->once())
            ->method('retrieve')
            ->willThrowException(new \Exception('Retrieval failed'));

        // Should fall back to keyword search
        $metadata = new Metadata([
            'log_id' => 'log_001',
            'content' => 'test query content',
            'message' => 'test query content',
            'category' => 'general',
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
            $metadata,
            null
        );

        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn('Analysis result');

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($platformResult);

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        // Should succeed with keyword search fallback
        $this->assertTrue($result['success']);
        $this->assertEquals('keyword-based', $result['search_method']);
    }

    /**
     * Test all pattern matching fallback patterns.
     */
    public function testPatternMatchingFallbackPatterns(): void
    {
        $patterns = [
            'Database connection failed' => 'Database connection failure',
            'Payment gateway timeout' => 'Payment gateway timeout',
            'Request timeout occurred' => 'Request timeout occurred',
            'Authentication failed' => 'Authentication failure',
            'Permission denied' => 'Insufficient permissions',
            'Out of memory error' => 'System ran out of memory',
            'Disk full error' => 'Disk space exhausted',
            'Invalid request format' => 'Invalid request format or parameters',
            'Service unavailable' => 'External service unavailable',
            '500 internal server error' => 'Internal server error occurred',
        ];

        foreach ($patterns as $logContent => $expectedReason) {
            // Reset mocks for each iteration
            $this->setUp();

            $query = 'error analysis';

            // Mock platform to throw exception, triggering pattern matching fallback
            $this->platform
                ->expects($this->once())
                ->method('__invoke')
                ->willThrowException(new \Exception('AI analysis failed'));

            $metadata = new Metadata([
                'log_id' => 'test_log',
                'content' => $logContent,
                'message' => $logContent,
                'category' => 'general',
            ]);

            $resultDocument = new VectorDocument(
                Uuid::v4(),
                new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
                $metadata,
                null
            );

            $this->store
                ->expects($this->once())
                ->method('queryForVector')
                ->willReturn([$resultDocument]);

            $result = $this->tool->__invoke($query);

            $this->assertTrue($result['success'], "Failed for pattern: $logContent");
            $this->assertEquals($expectedReason, $result['reason'], "Wrong reason for pattern: $logContent");
        }
    }

    /**
     * Test semantic keyword matching in keyword search.
     */
    public function testSemanticKeywordMatching(): void
    {
        $query = 'payment issues'; // 'payment' should match 'stripe', 'paypal', etc.
        $analysisResult = 'Stripe API connection issue';

        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($platformResult);

        // Document with 'stripe' (semantic match for 'payment')
        $metadata = new Metadata([
            'log_id' => 'pay_001',
            'content' => 'Stripe API connection timeout',
            'message' => 'Stripe API connection timeout',
            'category' => 'payment',
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
            $metadata,
            null
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        // Should match because 'stripe' is a semantic match for 'payment'
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['evidence_logs']);
    }

    /**
     * Test category-based matching in keyword search.
     */
    public function testCategoryBasedMatching(): void
    {
        $query = 'security';
        $analysisResult = 'Security incident detected';

        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($platformResult);

        // Document with security category
        $metadata = new Metadata([
            'log_id' => 'sec_001',
            'content' => 'Authentication attempt from unknown IP',
            'message' => 'Authentication attempt from unknown IP',
            'category' => 'security',
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            new Vector([0.5, 0.5, 0.5, 0.5, 0.5]),
            $metadata,
            null
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        // Should match because category matches query
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['evidence_logs']);
    }
}
