<?php

namespace Hakam\AiLogInspector\Agent\Test\Unit\Tool;

use Hakam\AiLogInspector\Agent\Tool\LogSearchTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

class LogSearchToolTest extends TestCase
{
    private StoreInterface $store;
    private PlatformInterface $platform;
    private Model $model;
    private LogSearchTool $tool;

    protected function setUp(): void
    {
        $this->store = $this->createMock(StoreInterface::class);
        $this->platform = $this->createMock(PlatformInterface::class);
        $this->model = $this->createMock(Model::class);
        
        $this->tool = new LogSearchTool(
            $this->store,
            $this->platform,
            $this->model
        );
    }

    private function createVectorPromise(Vector $vector): ResultPromise
    {
        $promise = $this->createMock(ResultPromise::class);
        $promise->method('asVectors')->willReturn([$vector]);
        return $promise;
    }

    private function createTextPromise(string $text): ResultPromise
    {
        $promise = $this->createMock(ResultPromise::class);
        $promise->method('asText')->willReturn($text);
        return $promise;
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

    public function testSuccessfulSearchWithAnalysis(): void
    {
        $query = 'why did the checkout fail with 500 error';
        $logContent = 'Database connection timeout during payment processing';
        $analysisResult = 'Payment gateway timeout caused database connection failure';
        
        // Create actual vector and results
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorPromise = $this->createVectorPromise($vector);
        $textPromise = $this->createTextPromise($analysisResult);
        
        $this->platform
            ->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls($vectorPromise, $textPromise);

        // Mock store search
        $metadata = new Metadata([
            'log_id' => 'log_12345',
            'content' => $logContent,
            'timestamp' => '2024-01-01 14:23:45',
            'level' => 'error',
            'source' => 'payment-service'
        ]);
        
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            $metadata,
            0.85
        );
        
        $this->store
            ->expects($this->once())
            ->method('query')
            ->with($vector, ['maxItems' => 10])
            ->willReturn([$vectorDocument]);

        $result = $this->tool->__invoke($query);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($analysisResult, $result['reason']);
        $this->assertCount(1, $result['evidence_logs']);
        $this->assertEquals('log_12345', $result['evidence_logs'][0]['id']);
        $this->assertEquals($logContent, $result['evidence_logs'][0]['content']);
        $this->assertEquals('error', $result['evidence_logs'][0]['level']);
    }

    public function testSearchWithNoResults(): void
    {
        $query = 'authentication issues';
        
        // Create actual vector and result
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorPromise = $this->createVectorPromise($vector);
        
        $this->platform
            ->expects($this->once())
            ->method('invoke')
            ->with($this->model, $query)
            ->willReturn($vectorPromise);

        // Mock empty search results
        $this->store
            ->expects($this->once())
            ->method('query')
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
        
        // Create actual vector and result
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorPromise = $this->createVectorPromise($vector);
        
        $this->platform
            ->expects($this->once())
            ->method('invoke')
            ->with($this->model, $query)
            ->willReturn($vectorPromise);

        // Mock low relevance document
        $metadata = new Metadata(['content' => 'Some unrelated log entry']);
        $vectorDocument = new VectorDocument(
            Uuid::v4(),
            $vector,
            $metadata,
            0.5 // Below threshold of 0.7
        );
        
        $this->store
            ->expects($this->once())
            ->method('query')
            ->willReturn([$vectorDocument]);

        $result = $this->tool->__invoke($query);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('No relevant log entries found to determine the cause of the issue.', $result['reason']);
    }

    public function testMultipleLogEntriesAnalysis(): void
    {
        $query = 'payment processing error';
        $analysisResult = 'Multiple payment gateway timeouts led to transaction failures';
        
        // Create actual vector and results
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorPromise = $this->createVectorPromise($vector);
        $textPromise = $this->createTextPromise($analysisResult);
        
        $this->platform
            ->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls($vectorPromise, $textPromise);

        // Create multiple documents
        $documents = [];
        for ($i = 1; $i <= 3; $i++) {
            $metadata = new Metadata([
                'log_id' => "log_$i",
                'content' => "Payment error $i occurred",
                'timestamp' => "2024-01-01 14:23:4$i",
                'level' => 'error'
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
            ->method('query')
            ->willReturn($documents);

        $result = $this->tool->__invoke($query);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($analysisResult, $result['reason']);
        $this->assertCount(3, $result['evidence_logs']);
        
        // Verify all logs are included
        for ($i = 1; $i <= 3; $i++) {
            $this->assertEquals("log_$i", $result['evidence_logs'][$i-1]['id']);
            $this->assertEquals("Payment error $i occurred", $result['evidence_logs'][$i-1]['content']);
        }
    }

    public function testDocumentWithoutMetadata(): void
    {
        $query = 'test query';
        
        // Create actual vector and results
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorPromise = $this->createVectorPromise($vector);
        $textPromise = $this->createTextPromise('Analysis of minimal log data');
        
        $this->platform
            ->method('invoke')
            ->willReturnOnConsecutiveCalls($vectorPromise, $textPromise);

        // Document with minimal metadata
        $uuid = Uuid::v4();
        $vectorDocument = new VectorDocument(
            $uuid,
            $vector,
            new Metadata([]), // Empty metadata
            0.8
        );
        
        $this->store
            ->expects($this->once())
            ->method('query')
            ->willReturn([$vectorDocument]);

        $result = $this->tool->__invoke($query);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Analysis of minimal log data', $result['reason']);
        $this->assertCount(1, $result['evidence_logs']);
        
        // Check fallback values
        $log = $result['evidence_logs'][0];
        $this->assertEquals($uuid->toString(), $log['id']);
        $this->assertEquals('No content available', $log['content']);
        $this->assertEquals('unknown', $log['level']);
        $this->assertEquals('unknown', $log['source']);
        $this->assertEmpty($log['tags']);
    }
}