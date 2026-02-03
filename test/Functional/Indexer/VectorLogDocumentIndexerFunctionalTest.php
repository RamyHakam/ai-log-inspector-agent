<?php

namespace Hakam\AiLogInspector\Test\Functional\Indexer;

use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Functional tests for LogFileIndexer with real log files.
 *
 * These tests use actual log files from the fixtures directory to verify
 * end-to-end indexing functionality.
 */
class VectorLogDocumentIndexerFunctionalTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesDir = __DIR__.'/../../fixtures/logs';
    }

    public function testIndexSingleLogFile(): void
    {
        $store = new VectorLogDocumentStore();
        $platform = $this->createMockPlatformWithVectorizer();

        $indexer = new LogFileIndexer(
            embeddingPlatform: $platform,
            model: 'text-embedding-3-small',
            logStore: $store,
            chunkSize: 500,
            chunkOverlap: 100
        );

        // Index a single log file using full path
        $indexer->indexLogFile($this->fixturesDir.'/application-errors.log');

        // Verify documents were added to store
        $documents = iterator_to_array($store->queryForVector(new Vector([0.1, 0.2, 0.3, 0.4, 0.5])));
        $this->assertNotEmpty($documents, 'Store should contain indexed documents');
    }

    public function testIndexMultipleLogFiles(): void
    {
        $store = new VectorLogDocumentStore();
        $platform = $this->createMockPlatformWithVectorizer();

        $indexer = new LogFileIndexer(
            embeddingPlatform: $platform,
            model: 'text-embedding-3-small',
            logStore: $store
        );

        // Index multiple log files using full paths
        $indexer->indexLogFiles([
            $this->fixturesDir.'/security-errors.log',
            $this->fixturesDir.'/payment-errors.log',
        ]);

        // Verify documents were added
        $documents = iterator_to_array($store->queryForVector(new Vector([0.1, 0.2, 0.3, 0.4, 0.5])));
        $this->assertNotEmpty($documents, 'Store should contain documents from multiple files');
    }

    public function testIndexAllLogs(): void
    {
        $store = new VectorLogDocumentStore();
        $platform = $this->createMockPlatformWithVectorizer();

        $indexer = new LogFileIndexer(
            embeddingPlatform: $platform,
            model: 'text-embedding-3-small',
            logStore: $store,
            chunkSize: 300,
            chunkOverlap: 50
        );

        // Index all log files by listing them explicitly
        $logFiles = glob($this->fixturesDir.'/*.log');
        $this->assertNotEmpty($logFiles, 'Fixture log files should exist');

        $indexer->indexLogFiles($logFiles);

        // Verify documents were added from all files
        $documents = iterator_to_array($store->queryForVector(new Vector([0.1, 0.2, 0.3, 0.4, 0.5])));
        $this->assertNotEmpty($documents, 'Store should contain documents from all log files');
    }

    public function testIndexingWithCustomChunkSize(): void
    {
        $store = new VectorLogDocumentStore();
        $platform = $this->createMockPlatformWithVectorizer();

        $indexer = new LogFileIndexer(
            embeddingPlatform: $platform,
            model: 'text-embedding-3-small',
            logStore: $store,
            chunkSize: 200,  // Smaller chunks
            chunkOverlap: 50
        );

        $indexer->indexLogFile($this->fixturesDir.'/application-errors.log', [
            'chunk_size' => 10,  // Process in small batches
        ]);

        $documents = iterator_to_array($store->queryForVector(new Vector([0.1, 0.2, 0.3, 0.4, 0.5])));
        $this->assertNotEmpty($documents);
    }

    public function testIndexingSecurityLogs(): void
    {
        $store = new VectorLogDocumentStore();
        $platform = $this->createMockPlatformWithVectorizer();

        $indexer = new LogFileIndexer(
            embeddingPlatform: $platform,
            model: 'text-embedding-3-small',
            logStore: $store
        );

        // Index security logs specifically
        $indexer->indexLogFile($this->fixturesDir.'/security-errors.log');

        $documents = iterator_to_array($store->queryForVector(new Vector([0.1, 0.9, 0.0, 0.3, 0.7])));
        $this->assertNotEmpty($documents);

        // Verify the content contains security-related terms
        $hasSecurityContent = false;
        foreach ($documents as $document) {
            $text = $document->metadata->getText() ?? '';
            if (str_contains($text, 'security')
                || str_contains($text, 'Authentication')
                || str_contains($text, 'SQL injection')) {
                $hasSecurityContent = true;

                break;
            }
        }

        $this->assertTrue($hasSecurityContent, 'Indexed documents should contain security-related content');
    }

    public function testIndexingPaymentLogs(): void
    {
        $store = new VectorLogDocumentStore();
        $platform = $this->createMockPlatformWithVectorizer();

        $indexer = new LogFileIndexer(
            embeddingPlatform: $platform,
            model: 'text-embedding-3-small',
            logStore: $store
        );

        // Index payment logs
        $indexer->indexLogFile($this->fixturesDir.'/payment-errors.log');

        $documents = iterator_to_array($store->queryForVector(new Vector([0.9, 0.1, 0.2, 0.8, 0.3])));
        $this->assertNotEmpty($documents);

        // Verify payment-related content
        $hasPaymentContent = false;
        foreach ($documents as $document) {
            $text = $document->metadata->getText() ?? '';
            if (str_contains($text, 'payment')
                || str_contains($text, 'Stripe')
                || str_contains($text, 'PayPal')) {
                $hasPaymentContent = true;

                break;
            }
        }

        $this->assertTrue($hasPaymentContent, 'Indexed documents should contain payment-related content');
    }

    public function testIndexingNonExistentFileThrowsException(): void
    {
        $store = new VectorLogDocumentStore();
        $platform = $this->createMockPlatformWithVectorizer();

        $indexer = new LogFileIndexer(
            embeddingPlatform: $platform,
            model: 'text-embedding-3-small',
            logStore: $store
        );

        $this->expectException(\Symfony\AI\Store\Exception\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $indexer->indexLogFile($this->fixturesDir.'/non-existent-file.log');
    }

    /**
     * Create a mock platform that returns simple vectors for testing.
     *
     * Uses proper DeferredResult with ResultConverterInterface and RawResultInterface
     * since DeferredResult is a final class and cannot be extended.
     */
    private function createMockPlatformWithVectorizer(): PlatformInterface
    {
        return new class implements PlatformInterface {
            public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
            {
                // Generate a simple 5-dimensional vector based on input
                $vector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
                $vectorResult = new VectorResult($vector);

                $resultConverter = new class($vectorResult) implements ResultConverterInterface {
                    public function __construct(private readonly VectorResult $result)
                    {
                    }

                    public function supports(Model $model): bool
                    {
                        return true;
                    }

                    public function convert(RawResultInterface $result, array $options = []): \Symfony\AI\Platform\Result\ResultInterface
                    {
                        return $this->result;
                    }

                    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
                    {
                        return null;
                    }
                };

                $rawResult = new class implements RawResultInterface {
                    public function getData(): array
                    {
                        return [];
                    }

                    public function getDataStream(): iterable
                    {
                        return [];
                    }

                    public function getObject(): object
                    {
                        return new \stdClass();
                    }
                };

                return new DeferredResult($resultConverter, $rawResult);
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                return new class implements ModelCatalogInterface {
                    public function getModel(string $modelName): Model
                    {
                        return new Model($modelName, [Capability::EMBEDDINGS]);
                    }

                    public function getModels(): array
                    {
                        return [];
                    }
                };
            }
        };
    }
}
