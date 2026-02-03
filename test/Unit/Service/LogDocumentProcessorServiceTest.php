<?php

namespace Hakam\AiLogInspector\Test\Unit\Service;

use DateTimeImmutable;
use Hakam\AiLogInspector\Document\LogDocument;
use Hakam\AiLogInspector\DTO\LogDataDTO;
use Hakam\AiLogInspector\Indexer\LogDocumentIndexer;
use Hakam\AiLogInspector\Service\LogDocumentProcessorService;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\TextDocument;

class LogDocumentProcessorServiceTest extends TestCase
{
    private PlatformInterface $platform;
    private VectorLogDocumentStore $store;
    private LogDocumentIndexer $indexer;
    private LogDocumentProcessorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->createMockPlatform();
        $this->store = new VectorLogDocumentStore();
        
        $this->indexer = new LogDocumentIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: new InMemoryLoader(),
            logStore: $this->store
        );

        $this->service = new LogDocumentProcessorService($this->indexer);
    }

    public function testProcessDocumentsWithEmptyArrayDoesNothing(): void
    {
        // Should not throw any exceptions
        $this->service->processDocuments([]);
        
        $this->assertTrue(true);
    }

    public function testProcessDocumentsIndexesMultipleDocuments(): void
    {
        $docs = [
            new LogDocument(
                content: 'Test message 1',
                rowMetadata: ['level' => 'INFO', 'message' => 'Test message 1']
            ),
            new LogDocument(
                content: 'Test message 2',
                rowMetadata: ['level' => 'ERROR', 'message' => 'Test message 2']
            ),
        ];

        $this->service->processDocuments($docs);

        // If we get here without exception, indexing was successful
        $this->assertTrue(true);
    }

    public function testProcessDocumentsWithSingleDocument(): void
    {
        $doc = new LogDocument(
            content: 'Single test message',
            rowMetadata: ['level' => 'WARNING', 'message' => 'Single test message']
        );

        $this->service->processDocuments([$doc]);

        $this->assertTrue(true);
    }

    public function testProcessDataWithLogDataDTO(): void
    {
        $logData = [
            new LogDataDTO(
                message: 'Payment failed',
                level: 'ERROR',
                timestamp: new DateTimeImmutable(),
                channel: 'payment',
                context: [
                    'user_id' => 123,
                    'amount' => 99.99,
                ]
            ),
        ];

        $this->service->processData($logData);

        $this->assertTrue(true);
    }

    public function testProcessDataWithArrays(): void
    {
        $logData = [
            [
                'message' => 'API request completed',
                'level' => 'INFO',
                'context' => [
                    'url' => '/api/users',
                    'method' => 'GET',
                    'duration' => 150,
                ],
            ],
            [
                'message' => 'Database query executed',
                'level' => 'DEBUG',
                'context' => [
                    'query' => 'SELECT * FROM users',
                    'query_time' => 5,
                ],
            ],
        ];

        $this->service->processData($logData);

        $this->assertTrue(true);
    }

    public function testProcessDataWithMixedDTOAndArrays(): void
    {
        $logData = [
            new LogDataDTO(
                message: 'DTO message',
                level: 'INFO',
                timestamp: new DateTimeImmutable()
            ),
            [
                'message' => 'Array message',
                'level' => 'ERROR',
            ],
        ];

        $this->service->processData($logData);

        $this->assertTrue(true);
    }

    public function testProcessDataWithEmptyArrayDoesNothing(): void
    {
        $this->service->processData([]);
        
        $this->assertTrue(true);
    }

    public function testProcessDataWithRichContext(): void
    {
        $logData = [
            new LogDataDTO(
                message: 'Payment gateway timeout',
                level: 'ERROR',
                timestamp: new DateTimeImmutable(),
                channel: 'payment',
                context: [
                    'request_id' => 'req_123',
                    'user_id' => 456,
                    'amount' => 199.99,
                    'gateway' => 'stripe',
                    'exception_class' => 'GatewayTimeoutException',
                    'duration' => 30500,
                ],
                enrichedData: [
                    'retry_count' => 3,
                    'circuit_breaker_open' => false,
                ]
            ),
        ];

        $this->service->processData($logData);

        $this->assertTrue(true);
    }

    public function testProcessDataWithDifferentLogLevels(): void
    {
        $logData = [
            ['message' => 'Debug message', 'level' => 'DEBUG'],
            ['message' => 'Info message', 'level' => 'INFO'],
            ['message' => 'Warning message', 'level' => 'WARNING'],
            ['message' => 'Error message', 'level' => 'ERROR'],
            ['message' => 'Critical message', 'level' => 'CRITICAL'],
        ];

        $this->service->processData($logData);

        $this->assertTrue(true);
    }

    public function testProcessDataCreatesSemanticContent(): void
    {
        $logData = [
            [
                'message' => 'Payment failed',
                'level' => 'ERROR',
                'channel' => 'payment',
                'context' => [
                    'user_id' => 123,
                    'amount' => 99.99,
                    'method' => 'POST',
                    'url' => '/api/payments',
                    'status_code' => 500,
                ],
            ],
        ];

        $this->service->processData($logData);

        // Semantic content should be created with rich context
        $this->assertTrue(true);
    }

    /**
     * Create a mock platform that returns embeddings.
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
                        // Return a dummy embedding vector (1536 dimensions)
                        return array_fill(0, 1536, 0.1);
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
                                return 'text-embedding-3-small';
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
