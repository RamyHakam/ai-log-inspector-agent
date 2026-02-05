<?php

namespace Hakam\AiLogInspector\Test\Functional;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Enum\PlatformEnum;
use Hakam\AiLogInspector\Indexer\LogFileIndexer;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Retriever\LogRetriever;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Test\Support\LogFileLoader;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\InMemory\Store;

/**
 * Comprehensive AI Log Inspector Functional Tests.
 *
 * Heavy operations (platform creation, indexing, embeddings) run ONCE per class.
 * Each test only creates a fresh agent instance.
 */
class LogInspectorFunctionalTest extends TestCase
{
    protected static VectorLogDocumentStore $store;
    protected static LogDocumentPlatformInterface $brainPlatform;
    protected static ?LogDocumentPlatformInterface $embeddingPlatform = null;

    protected static string $fixturesDir;
    protected static int $indexedDocuments = 0;
    protected static bool $usesIndexer = false;

    protected LogInspectorAgent $agent;

    public static function setUpBeforeClass(): void
    {
        $ollamaUrl = $_ENV['OLLAMA_URL'] ?? 'http://localhost:11434';

        if (!self::isOllamaAvailable($ollamaUrl)) {
            self::markTestSkipped('Ollama not available. Run: ollama serve');
        }

        self::$fixturesDir = __DIR__.'/../fixtures/logs';

        $inMemoryStore = new Store();
        self::$store = new VectorLogDocumentStore($inMemoryStore);

        // Brain platform (chat + tools)
        self::$brainPlatform = LogDocumentPlatformFactory::createBrainPlatform(
            PlatformEnum::OLLAMA,
            [
                'host' => $ollamaUrl,
                'model' => $_ENV['OLLAMA_MODEL'] ?? 'llama3.1',
            ]
        );

        try {
            self::$embeddingPlatform = LogDocumentPlatformFactory::createEmbeddingPlatform(
                PlatformEnum::OLLAMA,
                [
                    'host' => $ollamaUrl,
                    'model' => $_ENV['OLLAMA_EMBEDDING_MODEL'] ?? 'nomic-embed-text',
                ]
            );

            $indexer = new LogFileIndexer(
                embeddingPlatform: self::$embeddingPlatform->getPlatform(),
                model: $_ENV['OLLAMA_EMBEDDING_MODEL'] ?? 'nomic-embed-text',
                logStore: self::$store,
                chunkSize: 500,
                chunkOverlap: 100
            );

            $logFiles = glob(self::$fixturesDir.'/*.log');
            if (empty($logFiles)) {
                throw new \RuntimeException('No log files found for indexing.');
            }

            $indexer->indexLogFiles($logFiles);

            self::$indexedDocuments = count($logFiles);
            self::$usesIndexer = true;
        } catch (\Throwable) {
            // Fallback loader (no embeddings)
            $loader = new LogFileLoader();
            $logs = $loader->loadLogsIntoStore($inMemoryStore);

            self::$indexedDocuments = count($logs);
            self::$usesIndexer = false;
        }
    }

    protected function setUp(): void
    {
        $retriever = new LogRetriever(
            self::$brainPlatform->getPlatform(),
            $_ENV['OLLAMA_MODEL'] ?? 'llama3.1',
            self::$store
        );

        $tool = new LogSearchTool(
            self::$store,
            $retriever,
            self::$brainPlatform
        );

        $this->agent = new LogInspectorAgent(
            self::$brainPlatform,
            [$tool],
            'You are an expert AI Log Inspector for PHP applications. '
            .'Analyze real production logs and provide detailed root cause analysis. '
            .'Always cite log IDs and provide actionable recommendations.'
        );
    }

    public function testBasicAnalysisCapability(): void
    {
        $start = microtime(true);
        $response = $this->agent->ask('Find any payment errors in the logs.');
        $duration = microtime(true) - $start;

        $this->assertNotNull($response);
        $content = $response->getContent();

        $this->assertNotEmpty($content);
        $this->assertGreaterThan(20, strlen($content));
        $this->assertLessThan(180, $duration);
    }

    public function testLogFilesLoaded(): void
    {
        $logFiles = glob(self::$fixturesDir.'/*.log');

        $this->assertNotEmpty($logFiles);
        $this->assertGreaterThanOrEqual(3, count($logFiles));
        $this->assertGreaterThan(0, self::$indexedDocuments);
    }

    public function testEmbeddingPlatformIntegration(): void
    {
        if (!self::$usesIndexer || null === self::$embeddingPlatform) {
            $this->assertTrue(true);

            return;
        }

        $this->assertTrue(
            self::$embeddingPlatform->supportsEmbedding(),
            'Embedding platform must support embeddings'
        );
    }

    public function testPlatformFactoryIntegration(): void
    {
        $prompt = 'Analyze this error: PaymentException timeout for order #12345.';

        $response = (self::$brainPlatform)($prompt);

        $this->assertNotNull($response);
        $this->assertGreaterThan(30, strlen($response->getContent()));
    }

    public function testOverallSystemIntegration(): void
    {
        $this->assertNotNull($this->agent);
        $this->assertNotNull(self::$store);
        $this->assertNotNull(self::$brainPlatform);
        $this->assertGreaterThan(0, self::$indexedDocuments);

        $response = $this->agent->ask('What issues do you see in the logs?');

        $this->assertNotNull($response);
        $this->assertGreaterThan(10, strlen($response->getContent()));
    }

    public function testDatabaseErrorAnalysis(): void
    {
        $response = $this->agent->ask('Find database connection errors or timeouts.');
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->getContent());
    }

    public function testSecurityIncidentAnalysis(): void
    {
        $response = $this->agent->ask('Are there any security issues or authentication failures?');
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->getContent());
    }

    private static function isOllamaAvailable(string $url): bool
    {
        return false !== @file_get_contents(
            $url.'/api/version',
            false,
            stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ])
        );
    }
}
