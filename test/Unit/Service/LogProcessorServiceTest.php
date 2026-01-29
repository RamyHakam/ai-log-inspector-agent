<?php

namespace Hakam\AiLogInspector\Test\Unit\Service;

use DateTimeImmutable;
use Hakam\AiLogInspector\Document\VirtualLogDocument;
use Hakam\AiLogInspector\Indexer\VectorLogIndexerInterface;
use Hakam\AiLogInspector\Service\LogProcessorService;
use PHPUnit\Framework\TestCase;

class LogProcessorServiceTest extends TestCase
{
    public function testProcessTransformsAndIndexesDocuments(): void
    {
        $indexer = $this->createMock(VectorLogIndexerInterface::class);

        $indexer->expects($this->once())
            ->method('indexAndSaveLogs')
            ->with($this->callback(function (array $textDocs) {
                $this->assertCount(2, $textDocs);
                $this->assertSame('Log Message: Test message 1 | Severity Level: INFO | Timestamp: 2025-08-20 11:46:32 UTC | Day: Wednesday | Hour: 11:46 | Log Channel: app', $textDocs[0]->getContent());
                $this->assertSame('Log Message: Test message 2 | Severity Level: ERROR | Timestamp: 2025-08-20 11:46:32 UTC | Day: Wednesday | Hour: 11:46 | Log Channel: app', $textDocs[1]->getContent());
                return true;
            }));

        $service = new LogProcessorService($indexer);
        $timestamp = new DateTimeImmutable('2025-08-20 11:46:32 UTC');

        $docs = [
            new VirtualLogDocument('Test message 1', 'INFO', $timestamp),
            new VirtualLogDocument('Test message 2', 'ERROR', $timestamp),
        ];

        $service->process($docs);
    }

    public function testProcessWithEmptyArrayDoesNotCallIndexer(): void
    {
        $indexer = $this->createMock(VectorLogIndexerInterface::class);

        $indexer->expects($this->never())->method('indexAndSaveLogs');

        $service = new LogProcessorService($indexer);

        $service->process([]);
    }
}