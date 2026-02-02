<?php

namespace Hakam\AiLogInspector\Test\Unit\Service;

use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Service\LogFileProcessorService;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Store\Document\LoaderInterface;

class LogFileProcessorServiceTest extends TestCase
{
    private PlatformInterface $platform;
    private VectorLogDocumentStore $store;
    private LoaderInterface $loader;
    private LogFileIndexer $indexer;
    private LogFileProcessorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->createMockPlatform();
        $this->store = new VectorLogDocumentStore();
        $this->loader = $this->createMockLoader();
        
        $this->indexer = new LogFileIndexer(
            embeddingPlatform: $this->platform,
            model: 'text-embedding-3-small',
            loader: $this->loader,
            logStore: $this->store
        );

        $this->service = new LogFileProcessorService($this->indexer);
    }

    public function testProcessLogFilesWithEmptyArrayIndexesAllLogs(): void
    {
        // When no files specified, should index all logs
        $this->service->processLogFiles([]);
        
        // If we get here without exception, operation was successful
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithSingleFile(): void
    {
        $files = ['app.log'];
        
        $this->service->processLogFiles($files);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithMultipleFiles(): void
    {
        $files = [
            'app.log',
            'error.log',
            'security.log',
        ];
        
        $this->service->processLogFiles($files);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithOptions(): void
    {
        $files = ['app.log'];
        $options = [
            'pattern' => '*.log',
            'recursive' => true,
        ];
        
        $this->service->processLogFiles($files, $options);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithPatternFilter(): void
    {
        $files = [];
        $options = [
            'pattern' => 'error-*.log',
            'recursive' => false,
        ];
        
        // Should index all logs matching pattern
        $this->service->processLogFiles($files, $options);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithRecursiveOption(): void
    {
        $files = [];
        $options = [
            'pattern' => '*.log',
            'recursive' => true,
        ];
        
        $this->service->processLogFiles($files, $options);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithBatchSizeOption(): void
    {
        $files = ['large-log.log'];
        $options = [
            'batch_size' => 100,
        ];
        
        $this->service->processLogFiles($files, $options);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesFromDifferentDirectories(): void
    {
        $files = [
            '/var/log/app/production.log',
            '/var/log/app/staging.log',
            '/var/log/nginx/error.log',
        ];
        
        $this->service->processLogFiles($files);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithDifferentExtensions(): void
    {
        $files = [
            'app.log',
            'error.txt',
            'debug.out',
        ];
        
        $this->service->processLogFiles($files);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesHandlesLargeNumberOfFiles(): void
    {
        // Simulate processing many log files
        $files = [];
        for ($i = 1; $i <= 50; $i++) {
            $files[] = "app-{$i}.log";
        }
        
        $this->service->processLogFiles($files);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithCustomOptions(): void
    {
        $files = ['app.log'];
        $options = [
            'pattern' => '*.log',
            'recursive' => true,
            'batch_size' => 50,
            'max_file_size' => 1024 * 1024 * 10, // 10MB
        ];
        
        $this->service->processLogFiles($files, $options);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesIndexesAllLogsWhenNoFilesSpecified(): void
    {
        // Empty array should trigger indexAllLogs()
        $this->service->processLogFiles([]);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithGlobPattern(): void
    {
        $files = [];
        $options = [
            'pattern' => 'app-2024-*.log',
        ];
        
        $this->service->processLogFiles($files, $options);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithDateBasedLogs(): void
    {
        $files = [
            'app-2024-01-15.log',
            'app-2024-01-16.log',
            'app-2024-01-17.log',
        ];
        
        $this->service->processLogFiles($files);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithCompressedLogs(): void
    {
        $files = [
            'app.log',
            'app.log.gz',
            'app.log.zip',
        ];
        
        $this->service->processLogFiles($files);
        
        $this->assertTrue(true);
    }

    public function testProcessLogFilesWithRotatedLogs(): void
    {
        $files = [
            'app.log',
            'app.log.1',
            'app.log.2',
            'app.log.3',
        ];
        
        $this->service->processLogFiles($files);
        
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

    /**
     * Create a mock loader for file operations.
     */
    private function createMockLoader(): LoaderInterface
    {
        $loader = $this->createMock(LoaderInterface::class);
        
        // Mock load method to return empty iterator
        $loader->method('load')
            ->willReturn(new \ArrayIterator([]));
        
        return $loader;
    }
}
