<?php

namespace Hakam\AiLogInspector\Test\Integration;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Model\LogDocumentModel;
use Hakam\AiLogInspector\Platform\LogDocumentPlatform;
use Hakam\AiLogInspector\Retriever\LogRetriever;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\InMemory\Store;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Uid\Uuid;

/**
 * Integration test using real OpenAI API.
 *
 * Set OPENAI_API_KEY in .env file or as environment variable to run this test
 * Example: vendor/bin/phpunit test/Integration/RealOpenAIIntegrationTest.php
 */
#[Group('openai-api')]
class RealOpenAIIntegrationTest extends TestCase
{
    private $platform;
    private Store $store;
    private VectorLogDocumentStore $vectorStore;
    private LogDocumentPlatform $logPlatform;
    private LogDocumentModel $model;
    private LogRetriever $retriever;
    private LogInspectorAgent $agent;
    private LogSearchTool $tool;

    private const EMBEDDING_MODEL = 'text-embedding-3-small';
    private const CHAT_MODEL = 'gpt-4o-mini';

    protected function setUp(): void
    {
        // Load .env file from project root
        $envFile = dirname(__DIR__, 2).'/.env';
        if (file_exists($envFile)) {
            $dotenv = new Dotenv();
            $dotenv->loadEnv($envFile);
        }

        $apiKey = $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

        if (!$apiKey) {
            $this->markTestSkipped('OPENAI_API_KEY environment variable not set. Set it in .env file or as environment variable.');
        }

        // Create OpenAI platform
        $this->platform = PlatformFactory::create($apiKey);
        $this->store = new Store();
        $this->vectorStore = new VectorLogDocumentStore($this->store);

        // Create model with capabilities
        $this->model = new LogDocumentModel(
            self::CHAT_MODEL,
            [Capability::TOOL_CALLING, Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]
        );

        // Create LogDocumentPlatform wrapper
        $this->logPlatform = new LogDocumentPlatform($this->platform, $this->model);

        // Setup test data
        $this->setupRealWorldLogData();

        // Create retriever with embedding model
        $this->retriever = new LogRetriever(
            $this->platform,
            self::EMBEDDING_MODEL,
            $this->vectorStore,
        );

        // Create LogSearchTool
        $this->tool = new LogSearchTool(
            $this->vectorStore,
            $this->retriever,
            $this->logPlatform
        );

        // Create agent with the tool
        $this->agent = new LogInspectorAgent(
            $this->logPlatform,
            [$this->tool],
            null // Use default system prompt
        );
    }

    private function setupRealWorldLogData(): void
    {
        $realWorldLogs = [
            // E-commerce payment failure
            [
                'content' => '[2024-08-18 14:23:45] production.ERROR: PaymentGatewayException: Stripe payment failed for order #ORD-12345 - Card declined by issuer {"userId": 1001, "orderId": "ORD-12345", "amount": 299.99, "gateway": "stripe", "error_code": "card_declined", "card_last4": "4242"} []',
                'metadata' => [
                    'log_id' => 'payment_001',
                    'timestamp' => '2024-08-18T14:23:45Z',
                    'level' => 'error',
                    'source' => 'payment-service',
                    'tags' => ['payment', 'stripe', 'card_declined', 'ecommerce'],
                ],
            ],
            // Database connection timeout
            [
                'content' => '[2024-08-18 14:23:50] production.ERROR: Doctrine\\DBAL\\Exception\\ConnectionException: SQLSTATE[HY000] [2002] Connection timed out after 30 seconds (SQL: SELECT * FROM orders WHERE status = "pending" AND created_at > "2024-08-18 14:00:00") {"query_time": 30.001, "connection_pool": "orders_read", "affected_queries": 156} []',
                'metadata' => [
                    'log_id' => 'database_001',
                    'timestamp' => '2024-08-18T14:23:50Z',
                    'level' => 'error',
                    'source' => 'database',
                    'tags' => ['database', 'timeout', 'doctrine', 'orders'],
                ],
            ],
            // Authentication brute force attempt
            [
                'content' => '[2024-08-18 10:15:22] security.WARNING: Failed login attempt for user "admin" from IP 192.168.1.100 - too many attempts {"user": "admin", "ip": "192.168.1.100", "attempts_count": 15, "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)", "blocked_until": "2024-08-18T11:15:22Z"} []',
                'metadata' => [
                    'log_id' => 'security_001',
                    'timestamp' => '2024-08-18T10:15:22Z',
                    'level' => 'warning',
                    'source' => 'security',
                    'tags' => ['authentication', 'brute_force', 'security', 'admin'],
                ],
            ],
            // Memory exhaustion in data processing
            [
                'content' => '[2024-08-18 16:45:10] production.CRITICAL: PHP Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 65536 bytes) in /var/www/app/src/Service/DataProcessor.php:156 - Processing large CSV file with 500,000 records {"file_path": "/uploads/bulk_import_20240818.csv", "processed_records": 123456, "memory_peak": "128MB", "processing_time": 45.2} []',
                'metadata' => [
                    'log_id' => 'performance_001',
                    'timestamp' => '2024-08-18T16:45:10Z',
                    'level' => 'critical',
                    'source' => 'data_processor',
                    'tags' => ['memory', 'php', 'fatal_error', 'csv_import'],
                ],
            ],
            // API rate limiting from external service
            [
                'content' => '[2024-08-18 12:30:15] production.WARNING: External API rate limit exceeded - Shopify API {"endpoint": "https://shop.myshopify.com/admin/api/2023-07/products.json", "rate_limit": "40/40", "retry_after": 2.5, "request_id": "req_abc123", "shop_domain": "example.myshopify.com"} []',
                'metadata' => [
                    'log_id' => 'api_001',
                    'timestamp' => '2024-08-18T12:30:15Z',
                    'level' => 'warning',
                    'source' => 'shopify_integration',
                    'tags' => ['api', 'rate_limit', 'shopify', 'external_service'],
                ],
            ],
            // File permission error in log rotation
            [
                'content' => '[2024-08-18 09:12:45] production.ERROR: Failed to rotate log file - Permission denied {"file_path": "/var/log/app/application.log", "rotation_size": "100MB", "disk_space": "85%", "user": "www-data", "group": "www-data", "permissions": "644"} []',
                'metadata' => [
                    'log_id' => 'filesystem_001',
                    'timestamp' => '2024-08-18T09:12:45Z',
                    'level' => 'error',
                    'source' => 'log_rotation',
                    'tags' => ['filesystem', 'permissions', 'log_rotation', 'disk_space'],
                ],
            ],
        ];

        // Convert log content to text documents
        $textDocuments = [];
        foreach ($realWorldLogs as $logData) {
            $textDocuments[] = new TextDocument(
                Uuid::v4(),
                $logData['content'],
                new Metadata(array_merge($logData['metadata'], ['content' => $logData['content']]))
            );
        }

        // Create Symfony AI Vectorizer and vectorize documents
        $symfonyVectorizer = new Vectorizer($this->platform, self::EMBEDDING_MODEL);
        $vectorDocuments = $symfonyVectorizer->vectorize($textDocuments);

        // Add vectorized documents to store
        $this->store->add($vectorDocuments);
    }

    public function testRealPaymentFailureAnalysis(): void
    {
        $result = $this->tool->__invoke('payment failed card declined');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('evidence_logs', $result);

        if ($result['success']) {
            $this->assertNotEmpty($result['reason']);

            // Verify AI correctly identified payment failure
            $reasonLower = strtolower($result['reason']);
            $foundPaymentRelated = str_contains($reasonLower, 'card')
                || str_contains($reasonLower, 'payment')
                || str_contains($reasonLower, 'declined')
                || str_contains($reasonLower, 'stripe')
                || str_contains($reasonLower, 'gateway');

            if ($foundPaymentRelated) {
                $this->assertNotEmpty($result['evidence_logs']);
            }
        }

        echo "\n Payment Analysis Result:\n";
        echo 'Success: '.($result['success'] ? 'Yes' : 'No')."\n";
        echo 'Reason: '.$result['reason']."\n";
        echo 'Evidence Logs: '.count($result['evidence_logs'])." found\n";
    }

    public function testRealDatabasePerformanceIssue(): void
    {
        $result = $this->tool->__invoke('database connection timeout error');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('reason', $result);

        if ($result['success']) {
            $this->assertNotEmpty($result['reason']);

            $reasonLower = strtolower($result['reason']);
            $this->assertTrue(
                str_contains($reasonLower, 'database')
                || str_contains($reasonLower, 'connection')
                || str_contains($reasonLower, 'timeout')
                || str_contains($reasonLower, 'sql'),
                'AI should identify database performance issue. Got: '.$result['reason']
            );
        }

        echo "\n Database Analysis Result:\n";
        echo 'Success: '.($result['success'] ? 'Yes' : 'No')."\n";
        echo 'Reason: '.$result['reason']."\n";
    }

    public function testRealSecurityIncidentAnalysis(): void
    {
        $result = $this->tool->__invoke('security login failed authentication brute force');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('reason', $result);

        if ($result['success']) {
            $this->assertNotEmpty($result['reason']);

            $reasonLower = strtolower($result['reason']);
            $this->assertTrue(
                str_contains($reasonLower, 'brute')
                || str_contains($reasonLower, 'attack')
                || str_contains($reasonLower, 'security')
                || str_contains($reasonLower, 'login')
                || str_contains($reasonLower, 'authentication')
                || str_contains($reasonLower, 'attempts'),
                'AI should identify security/authentication issue. Got: '.$result['reason']
            );
        }

        echo "\n Security Analysis Result:\n";
        echo 'Success: '.($result['success'] ? 'Yes' : 'No')."\n";
        echo 'Reason: '.$result['reason']."\n";
    }

    public function testRealMemoryIssueAnalysis(): void
    {
        $result = $this->tool->__invoke('memory exhausted fatal error PHP');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('reason', $result);

        if ($result['success']) {
            $this->assertNotEmpty($result['reason']);

            $reasonLower = strtolower($result['reason']);
            $this->assertTrue(
                str_contains($reasonLower, 'memory')
                || str_contains($reasonLower, 'exhausted')
                || str_contains($reasonLower, 'php')
                || str_contains($reasonLower, 'fatal')
                || str_contains($reasonLower, 'csv')
                || str_contains($reasonLower, 'processing'),
                'AI should identify memory exhaustion issue. Got: '.$result['reason']
            );
        }

        echo "\n Memory Analysis Result:\n";
        echo 'Success: '.($result['success'] ? 'Yes' : 'No')."\n";
        echo 'Reason: '.$result['reason']."\n";
    }

    public function testRealIrrelevantQuery(): void
    {
        $result = $this->tool->__invoke('quantum flux capacitor nuclear reactor');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('reason', $result);

        // For irrelevant queries, we expect success to be false or low relevance
        echo "\n Irrelevant Query Result:\n";
        echo 'Success: '.($result['success'] ? 'Yes' : 'No')."\n";
        echo 'Reason: '.$result['reason']."\n";
    }

    public function testRealAgentWorkflow(): void
    {
        // Test the agent's ask() method which returns ResultInterface
        $result = $this->agent->ask('What errors occurred today?');

        // agent->ask() returns ResultInterface
        $this->assertInstanceOf(\Symfony\AI\Platform\Result\ResultInterface::class, $result);

        $content = $result->getContent();
        $this->assertNotEmpty($content);

        echo "\n Agent Workflow Result:\n";
        echo 'Content: '.(is_string($content) ? $content : json_encode($content))."\n";
    }
}
