<?php

namespace Hakam\AiLogInspector\Test\Unit\Retriever;

use Hakam\AiLogInspector\Retriever\LogRetriever;
use Hakam\AiLogInspector\Retriever\LogRetrieverInterface;
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
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Uid\Uuid;

class LogRetrieverTest extends TestCase
{
    private PlatformInterface $platform;
    private VectorLogDocumentStore $store;

    protected function setUp(): void
    {
        $this->platform = $this->createMockPlatform();
        $this->store = new VectorLogDocumentStore();
    }

    public function testImplementsLogRetrieverInterface(): void
    {
        $retriever = new LogRetriever(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            logStore: $this->store,
        );

        $this->assertInstanceOf(LogRetrieverInterface::class, $retriever);
    }

    public function testConstructorWithDifferentModels(): void
    {
        $models = [
            'text-embedding-ada-002',
            'text-embedding-3-large',
            'nomic-embed-text',
            'mxbai-embed-large',
        ];

        foreach ($models as $model) {
            $retriever = new LogRetriever(
                embeddingPlatform: $this->platform,
                model: $model,
                logStore: $this->store,
            );

            $this->assertInstanceOf(
                LogRetrieverInterface::class,
                $retriever,
                "Should accept model: $model"
            );
        }
    }

    public function testRetrieveReturnsIterable(): void
    {
        $retriever = new LogRetriever(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            logStore: $this->store,
        );

        $result = $retriever->retrieve('test query');

        $this->assertIsIterable($result);
    }

    public function testRetrieveWithEmptyStore(): void
    {
        $retriever = new LogRetriever(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            logStore: $this->store,
        );

        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertEmpty($results);
    }

    public function testRetrieveWithOptionsForwarding(): void
    {
        $retriever = new LogRetriever(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            logStore: $this->store,
        );

        // Should not throw when passing options
        $results = iterator_to_array($retriever->retrieve('test query', ['maxItems' => 5]));

        $this->assertIsArray($results);
    }

    public function testRetrieveDelegatesToSymfonyRetriever(): void
    {
        // Pre-populate store with a document using the same vector the mock returns
        $vector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
        $metadata = new Metadata([
            'content' => 'Payment gateway timeout error',
            'log_id' => 'log_001',
            'level' => 'error',
        ]);

        $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
        $this->store->saveLogVectorDocuments([$document]);

        $retriever = new LogRetriever(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            logStore: $this->store,
        );

        $results = iterator_to_array($retriever->retrieve('payment error'));

        // The store should return the document (in-memory store returns all documents)
        $this->assertNotEmpty($results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
    }

    public function testRetrieveWithCustomStore(): void
    {
        $customStore = new VectorLogDocumentStore();

        $retriever = new LogRetriever(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            logStore: $customStore,
        );

        $this->assertInstanceOf(LogRetrieverInterface::class, $retriever);

        $results = iterator_to_array($retriever->retrieve('test'));
        $this->assertEmpty($results);
    }

    /**
     * Create a mock platform that returns embeddings via proper DeferredResult.
     */
    private function createMockPlatform(): PlatformInterface
    {
        return new class implements PlatformInterface {
            public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
            {
                // Create a Vector from the input
                $vector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
                $vectorResult = new VectorResult($vector);

                // Create proper ResultConverterInterface and RawResultInterface
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
