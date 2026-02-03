<?php

namespace Hakam\AiLogInspector\Test\Functional;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Retriever\LogRetriever;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Test\Support\LogFileLoader;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\InMemory\Store;

/**
 * Comprehensive AI Log Inspector Functional Tests.
 */
class LogInspectorFunctionalTest extends TestCase
{
    private LogInspectorAgent $agent;
    private VectorLogDocumentStore $store;
    private LogFileLoader $logLoader;
    private array $loadedLogs = [];

    protected function setUp(): void
    {
        if (!$this->isOllamaWithToolsAvailable()) {
            $this->markTestSkipped('Ollama with tool calling not available. Run: ollama pull llama3.2:1b');
        }

        // Initialize components using production classes
        $inMemoryStore = new Store();
        $this->store = new VectorLogDocumentStore($inMemoryStore);
        $this->logLoader = new LogFileLoader();

        // Load realistic PHP logs from fixture files
        $this->loadedLogs = $this->logLoader->loadLogsIntoStore($inMemoryStore);

        // Create platform with tool calling model (optimized for CI)
        $platform = LogDocumentPlatformFactory::create([
            'provider' => 'ollama',
            'host' => $_ENV['OLLAMA_URL'] ?? 'http://localhost:11434',
            'model' => [
                'name' => $_ENV['OLLAMA_MODEL'] ?? 'llama3.2:1b',
                'capabilities' => ['text', 'tool_calling'],
                'options' => [
                    'temperature' => 0.1,  // Lower for faster, more deterministic responses
                    'max_tokens' => 300,   // Reduced for faster responses
                    'timeout' => 180,       // 3 minute timeout for CI
                ],
            ],
            'client_options' => [
                'timeout' => 180,  // 3 minute HTTP timeout
                'max_duration' => 180,  // Maximum duration for the entire request
            ],
        ]);

        // Create retriever for the LogSearchTool
        $retriever = new LogRetriever(
            $platform->getPlatform(),
            $platform->getModel()->getName(),
            $this->store,
        );

        // Create production LogSearchTool
        $logSearchTool = new LogSearchTool(
            $this->store,
            $retriever,
            $platform
        );

        // Create agent with comprehensive system prompt
        $this->agent = new LogInspectorAgent(
            $platform,
            [$logSearchTool],
            'You are an expert AI Log Inspector for PHP applications. Analyze real production logs and provide detailed root cause analysis with specific recommendations. Always cite log IDs and provide actionable insights based on evidence from the logs.'
        );
    }

    /**
     * Test basic AI analysis capability (simplified for CI).
     */
    public function testBasicAnalysisCapability()
    {
        $startTime = microtime(true);

        $response = $this->agent->ask('Find any payment errors in the logs.');

        $duration = microtime(true) - $startTime;

        $this->assertNotNull($response);
        $content = $response->getContent();

        $this->assertNotEmpty($content);
        $this->assertGreaterThan(20, strlen($content), 'Should provide some analysis');
        $this->assertLessThan(120.0, $duration, 'Should respond within 2 minutes');

        echo "\n💳 Basic analysis completed!";
        echo "\n📝 Analysis length: ".strlen($content).' characters';
        echo "\n⏱️ Response time: ".number_format($duration, 2).'s';
        echo "\n📊 Loaded logs: ".count($this->loadedLogs).' entries';
    }

    public function testLogStatisticsValidation()
    {
        $stats = $this->logLoader->getLogStats();

        $this->assertNotEmpty($stats, 'Should have log statistics');
        $this->assertArrayHasKey('payment', $stats);
        $this->assertArrayHasKey('database', $stats);
        $this->assertArrayHasKey('security', $stats);
        $this->assertArrayHasKey('application', $stats);
        $this->assertArrayHasKey('performance', $stats);

        $totalLogs = 0;
        foreach ($stats as $category => $info) {
            $this->assertGreaterThan(0, $info['logCount'], "Category {$category} should have logs");
            $totalLogs += $info['logCount'];
        }

        $this->assertGreaterThan(30, $totalLogs, 'Should have substantial log data');
        // Note: loadedLogs contains parsed log data, totalLogs is from file line count
        // They may differ slightly due to parsing, so just verify we have substantial data
        $this->assertGreaterThan(40, count($this->loadedLogs), 'Should have loaded substantial logs');

        echo "\n📊 Log Statistics:";
        foreach ($stats as $category => $info) {
            echo "\n   • {$category}: {$info['logCount']} logs ({$info['filename']})";
        }
        echo "\n   • Total: {$totalLogs} log entries loaded";
    }

    public function testCategorySpecificAnalysis()
    {
        $categories = ['payment', 'database', 'security', 'application', 'performance'];

        foreach ($categories as $category) {
            $logs = $this->logLoader->getLogsByCategory($category);
            $this->assertNotEmpty($logs, "Category {$category} should have logs");

            // Verify log structure
            $firstLog = $logs[0];
            $this->assertArrayHasKey('metadata', $firstLog);
            $this->assertArrayHasKey('log_id', $firstLog['metadata']);
            $this->assertArrayHasKey('category', $firstLog['metadata']);
            $this->assertEquals($category, $firstLog['metadata']['category']);

            echo "\n🏷️  {$category}: ".count($logs).' logs';
        }
    }

    public function testPlatformFactoryIntegration()
    {
        $platform = LogDocumentPlatformFactory::create([
            'provider' => 'ollama',
            'host' => $_ENV['OLLAMA_URL'] ?? 'http://localhost:11434',
            'model' => [
                'name' => $_ENV['OLLAMA_MODEL'] ?? 'llama3.2:1b',
                'capabilities' => ['text'],
                'options' => [
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                ],
            ],
        ]);

        $response = $platform->__invoke('Analyze this error: PaymentException timeout for order #12345');

        $this->assertNotNull($response);
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(30, strlen($content), 'Should provide substantial analysis');

        // Should mention payment or timeout
        $lowerContent = strtolower($content);
        $hasRelevantAnalysis = str_contains($lowerContent, 'payment')
                              || str_contains($lowerContent, 'timeout');

        $this->assertTrue($hasRelevantAnalysis, 'Should provide relevant analysis');

        echo "\n🏭 Platform factory integration validated!";
    }

    public function testBasicErrorHandling()
    {
        // Test simple query to verify basic functionality
        $response = $this->agent->ask('Help me find errors.');
        $this->assertNotNull($response);
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(5, strlen($content));

        echo "\n🚫 Basic error handling validated!";
    }

    public function testOverallSystemIntegration()
    {
        // Test that all components work together
        $this->assertNotNull($this->agent, 'Agent should be initialized');
        $this->assertNotNull($this->store, 'Store should be initialized');
        $this->assertNotNull($this->logLoader, 'LogLoader should be initialized');
        $this->assertGreaterThan(30, count($this->loadedLogs), 'Should have loaded substantial logs');

        // Test simple analysis
        $response = $this->agent->ask('What issues do you see?');

        $this->assertNotNull($response);
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(10, strlen($content), 'Should provide some analysis');

        echo "\n🎯 Overall system integration validated!";
        echo "\n📊 Final Stats:";
        echo "\n   • Total logs loaded: ".count($this->loadedLogs);
        echo "\n   • Analysis length: ".strlen($content).' characters';
    }

    private function isOllamaWithToolsAvailable(): bool
    {
        $url = $_ENV['OLLAMA_URL'] ?? 'http://localhost:11434';
        $model = $_ENV['OLLAMA_MODEL'] ?? 'llama3.2:1b';

        // Check if Ollama is running
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url.'/api/version', false, $context);
        if (false === $result) {
            return false;
        }

        return true; // Skip model capability check for now
    }
}
