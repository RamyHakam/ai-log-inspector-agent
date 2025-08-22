<?php

namespace Hakam\AiLogInspector\Test\Unit\Tool;

use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizerInterface;
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
    private LogDocumentVectorizerInterface $vectorizer;
    private LogDocumentPlatformInterface $platform;

    protected function setUp(): void
    {
        $this->store = $this->createMock(VectorLogStoreInterface::class);
        $this->vectorizer = $this->createMock(LogDocumentVectorizerInterface::class);
        $this->platform = $this->createMock(LogDocumentPlatformInterface::class);

        $this->tool = new LogSearchTool(
            $this->store,
            $this->vectorizer,
            $this->platform
        );
    }

    public function testInvokeWithEmptyQuery(): void
    {
        $result = $this->tool->__invoke('');

        $this->assertFalse($result['success']);
        $this->assertEquals('Query cannot be empty', $result['message']);
        $this->assertEmpty($result['logs']);
    }

    public function testInvokeWithWhitespaceOnlyQuery(): void
    {
        $result = $this->tool->__invoke('   ');

        $this->assertFalse($result['success']);
        $this->assertEquals('Query cannot be empty', $result['message']);
        $this->assertEmpty($result['logs']);
    }

    public function testSuccessfulSearchWithAIAnalysis(): void
    {
        $query = 'why did the checkout fail with 500 error';
        $logContent = 'Database connection timeout during payment processing';
        $analysisResult = 'Payment gateway timeout caused database connection failure during checkout';

        // Create vector and vector document
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata([]),
            null
        );

        // Mock vectorizer to return vector document
        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willReturn([$vectorDocument]);

        // Mock platform analysis
        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->stringContains('Analyze these log entries'))
            ->willReturn($platformResult);

        // Mock store search results
        $metadata = new Metadata([
            'log_id' => 'log_12345',
            'content' => $logContent,
            'timestamp' => '2024-01-01 14:23:45',
            'level' => 'error',
            'source' => 'payment-service',
            'tags' => ['checkout', 'payment']
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            $metadata,
            0.85
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->with($vector, ['maxItems' => 10])
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        $this->assertEquals($analysisResult, $result['reason']);
        $this->assertCount(1, $result['evidence_logs']);
        $this->assertEquals('log_12345', $result['evidence_logs'][0]['id']);
        $this->assertEquals($logContent, $result['evidence_logs'][0]['content']);
        $this->assertEquals('error', $result['evidence_logs'][0]['level']);
        $this->assertEquals('payment-service', $result['evidence_logs'][0]['source']);
        $this->assertEquals(['checkout', 'payment'], $result['evidence_logs'][0]['tags']);
    }

    public function testSuccessfulSearchWithPatternMatchingFallback(): void
    {
        $query = 'database connection issues';
        $logContent = 'Database connection failed to establish';

        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata([]),
            null
        );

        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willReturn([$vectorDocument]);

        // Mock platform to throw exception, triggering fallback
        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \Exception('AI analysis failed'));

        $metadata = new Metadata([
            'log_id' => 'log_db_001',
            'content' => $logContent,
            'timestamp' => '2024-01-01 14:23:45',
            'level' => 'error',
            'source' => 'database-service'
        ]);

        $resultDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            $metadata,
            0.9
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        $this->assertEquals('Database connection failure', $result['reason']);
        $this->assertCount(1, $result['evidence_logs']);
    }

    public function testSearchWithNoResults(): void
    {
        $query = 'authentication issues';

        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata([]),
            null
        );

        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willReturn([$vectorDocument]);

        // Mock empty search results
        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->with($vector, ['maxItems' => 10])
            ->willReturn([]);

        $result = $this->tool->__invoke($query);

        $this->assertFalse($result['success']);
        $this->assertEquals('No relevant log entries found to determine the cause of the issue.', $result['reason']);
        $this->assertEmpty($result['evidence_logs']);
    }

    public function testSearchWithLowRelevanceScores(): void
    {
        $query = 'database errors';

        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata([]),
            null
        );

        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willReturn([$vectorDocument]);

        // Mock low relevance document
        $metadata = new Metadata(['content' => 'Some unrelated log entry']);
        $resultDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            $metadata,
            0.5 // Below threshold of 0.7
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertFalse($result['success']);
        $this->assertEquals('No relevant log entries found to determine the cause of the issue.', $result['reason']);
    }

    public function testMultipleLogEntriesWithAIAnalysis(): void
    {
        $query = 'payment processing error';
        $analysisResult = 'Multiple payment gateway timeouts led to transaction failures across the system';

        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata([]),
            null
        );

        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willReturn([$vectorDocument]);

        // Mock platform analysis
        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->stringContains('Payment gateway timeout error 1'))
            ->willReturn($platformResult);

        // Create multiple documents with payment-related content
        $documents = [];
        for ($i = 1; $i <= 3; $i++) {
            $metadata = new Metadata([
                'log_id' => "log_$i",
                'content' => "Payment gateway timeout error $i",
                'timestamp' => "2024-01-01 14:23:4$i",
                'level' => 'error',
                'source' => 'payment-service',
                'tags' => []
            ]);

            $documents[] = new VectorDocument(
                Uuid::v4(),
                $vector,
                $metadata,
                0.8 + ($i * 0.05)
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

        // Verify all logs are included
        for ($i = 1; $i <= 3; $i++) {
            $this->assertEquals("log_$i", $result['evidence_logs'][$i - 1]['id']);
            $this->assertEquals("Payment gateway timeout error $i", $result['evidence_logs'][$i - 1]['content']);
            $this->assertEquals('error', $result['evidence_logs'][$i - 1]['level']);
            $this->assertEquals('payment-service', $result['evidence_logs'][$i - 1]['source']);
            $this->assertEquals([], $result['evidence_logs'][$i - 1]['tags']);
        }
    }

    public function testDocumentWithoutMetadataUsesAIAnalysis(): void
    {
        $query = 'test query';
        $analysisResult = 'Analysis of minimal log data shows no clear error pattern';

        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata([]),
            null
        );

        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willReturn([$vectorDocument]);

        // Mock platform analysis
        $platformResult = $this->createMock(ResultInterface::class);
        $platformResult->method('getContent')->willReturn($analysisResult);

        $this->platform
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($platformResult);

        // Document with minimal metadata
        $uuid = Uuid::v4();
        $resultDocument = new VectorDocument(
            $uuid,
            $vector,
            new Metadata([]), // Empty metadata
            0.8
        );

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willReturn([$resultDocument]);

        $result = $this->tool->__invoke($query);

        $this->assertTrue($result['success']);
        $this->assertEquals($analysisResult, $result['reason']);
        $this->assertCount(1, $result['evidence_logs']);

        // Check fallback values
        $log = $result['evidence_logs'][0];
        $this->assertEquals($uuid->toString(), $log['id']);
        $this->assertEquals('No content available', $log['content']);
        $this->assertNull($log['timestamp']);
        $this->assertEquals('unknown', $log['level']);
        $this->assertEquals('unknown', $log['source']);
        $this->assertEquals([], $log['tags']);
    }

    public function testVectorizerException(): void
    {
        $query = 'test query';

        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willThrowException(new \Exception('Vectorization failed'));

        $result = $this->tool->__invoke($query);

        $this->assertFalse($result['success']);
        $this->assertEquals('Search failed: Vectorization failed', $result['message']);
        $this->assertEmpty($result['logs']);
    }

    public function testStoreException(): void
    {
        $query = 'test query';

        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata([]),
            null
        );

        $this->vectorizer
            ->expects($this->once())
            ->method('vectorizeLogTextDocuments')
            ->willReturn([$vectorDocument]);

        $this->store
            ->expects($this->once())
            ->method('queryForVector')
            ->willThrowException(new \Exception('Store query failed'));

        $result = $this->tool->__invoke($query);

        $this->assertFalse($result['success']);
        $this->assertEquals('Search failed: Store query failed', $result['message']);
        $this->assertEmpty($result['logs']);
    }

    public function testAllPatternMatchingFallback(): void
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
            '500 internal server error' => 'Internal server error occurred'
        ];

        foreach ($patterns as $logContent => $expectedReason) {
            $query = 'error analysis';

            $vector = new Vector([0.1, 0.2, 0.3]);
            $vectorDocument = new VectorDocument(
                Uuid::v4(),
                $vector,
                new Metadata([]),
                null
            );

            $this->vectorizer
                ->expects($this->once())
                ->method('vectorizeLogTextDocuments')
                ->willReturn([$vectorDocument]);

            // Mock platform to throw exception, triggering pattern matching fallback
            $this->platform
                ->expects($this->once())
                ->method('__invoke')
                ->willThrowException(new \Exception('AI analysis failed'));

            $metadata = new Metadata([
                'log_id' => 'test_log',
                'content' => $logContent
            ]);

            $resultDocument = new VectorDocument(
                Uuid::v4(),
                $vector,
                $metadata,
                0.8
            );

            $this->store
                ->expects($this->once())
                ->method('queryForVector')
                ->willReturn([$resultDocument]);

            $result = $this->tool->__invoke($query);

            $this->assertTrue($result['success'], "Failed for pattern: $logContent");
            $this->assertEquals($expectedReason, $result['reason'], "Wrong reason for pattern: $logContent");

            // Reset mocks for next iteration
            $this->setUp();
        }
    }
}