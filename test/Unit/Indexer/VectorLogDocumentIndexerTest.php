<?php

namespace Hakam\AiLogInspector\Test\Unit\Indexer;

use Hakam\AiLogInspector\Indexer\VectorLogDocumentIndexer;
use Hakam\AiLogInspector\Indexer\VectorLogIndexerInterface;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Store\Document\LoaderInterface;

class VectorLogDocumentIndexerTest extends TestCase
{
    private PlatformInterface $platform;
    private LoaderInterface $loader;
    private VectorLogDocumentStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->createMockPlatform();
        $this->loader = $this->createStub(LoaderInterface::class);
        $this->store = new VectorLogDocumentStore();
    }

    public function testConstructorWithValidParameters(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store
        );

        $this->assertInstanceOf(VectorLogDocumentIndexer::class, $indexer);
    }

    public function testConstructorWithCustomChunkSettings(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store,
            chunkSize: 1000,
            chunkOverlap: 200
        );

        $this->assertInstanceOf(VectorLogDocumentIndexer::class, $indexer);
    }

    public function testConstructorUsesDefaultStore(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader
        );

        $this->assertInstanceOf(VectorLogDocumentIndexer::class, $indexer);
    }

    public function testCheckForEmbeddingSupportReturnsTrue(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store
        );

        // Currently always returns true due to Symfony AI limitation
        $this->assertTrue($indexer->checkForEmbeddingSupport());
    }

    public function testConstructorWithDifferentEmbeddingModels(): void
    {
        $models = [
            'text-embedding-ada-002',
            'text-embedding-3-large',
            'nomic-embed-text',
            'mxbai-embed-large',
        ];

        foreach ($models as $model) {
            $indexer = new VectorLogDocumentIndexer(
                embeddingPlatform: $this->platform,
                model: $model,
                loader: $this->loader,
                logStore: $this->store
            );

            $this->assertInstanceOf(
                VectorLogDocumentIndexer::class,
                $indexer,
                "Should accept model: $model"
            );
        }
    }

    public function testHasIndexLogFileMethod(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store
        );

        $this->assertTrue(
            method_exists($indexer, 'indexLogFile'),
            'VectorLogDocumentIndexer should have indexLogFile method'
        );
    }

    public function testHasIndexLogFilesMethod(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store
        );

        $this->assertTrue(
            method_exists($indexer, 'indexLogFiles'),
            'VectorLogDocumentIndexer should have indexLogFiles method'
        );
    }

    public function testHasIndexAllLogsMethod(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store
        );

        $this->assertTrue(
            method_exists($indexer, 'indexAllLogs'),
            'VectorLogDocumentIndexer should have indexAllLogs method'
        );
    }

    public function testConstructorWithZeroChunkOverlap(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store,
            chunkSize: 500,
            chunkOverlap: 0
        );

        $this->assertInstanceOf(VectorLogDocumentIndexer::class, $indexer);
    }

    public function testConstructorWithLargeChunkSize(): void
    {
        $indexer = new VectorLogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store,
            chunkSize: 10000,
            chunkOverlap: 500
        );

        $this->assertInstanceOf(VectorLogDocumentIndexer::class, $indexer);
    }

    /**
     * Create a mock platform that returns embeddings
     */
    private function createMockPlatform(): PlatformInterface
    {
        return new class implements PlatformInterface {
            public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
            {
                $mockResult = new class($input) implements ResultInterface {
                    public function __construct(private array|string|object $input) {}

                    public function getContent(): mixed
                    {
                        $inputs = is_array($this->input) ? $this->input : [$this->input];
                        $embeddings = [];

                        foreach ($inputs as $text) {
                            $content = is_string($text) ? $text : (string) $text;
                            $embeddings[] = [
                                strlen($content) % 100 / 100,
                                0.2,
                                0.3,
                                0.4,
                                0.5
                            ];
                        }

                        return count($embeddings) === 1 ? $embeddings[0] : $embeddings;
                    }
                };

                return new DeferredResult(
                    fn() => $mockResult->getContent(),
                    fn($content) => $mockResult
                );
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                return new class implements ModelCatalogInterface {
                    public function has(string $name): bool
                    {
                        return true;
                    }

                    public function get(string $name): object
                    {
                        return new class {
                            public function getName(): string
                            {
                                return 'test-model';
                            }
                        };
                    }

                    public function register(object $model): void
                    {
                    }
                };
            }
        };
    }
}
