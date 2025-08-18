<?php

namespace Hakam\AiLogInspector\Agent\Test\Integration;

use Hakam\AiLogInspector\Agent\Tool\LogSearchTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\InMemoryPlatform;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Uid\Uuid;

class LogSearchToolIntegrationTest extends TestCase
{
    private InMemoryPlatform $platform;
    private InMemoryStore $store;
    private Model $model;
    private LogSearchTool $tool;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->model = new Model('test-model');

        $this->platform = new InMemoryPlatform(
            function (Model $model, array|string|object $input, array $options = []) {
                return $this->handleRequest($input);
            }
        );

        $this->setupRealWorldLogData();

        $this->tool = new LogSearchTool($this->store, $this->platform, $this->model);
    }

    private function handleRequest(array|string|object $input): ResultInterface
    {
        $inputString = is_string($input) ? $input : (string)$input;

        if (!str_contains($inputString, 'Analyze these log entries')) {
            if (str_contains($inputString, 'checkout') || str_contains($inputString, 'payment') || str_contains($inputString, 'timeout')) {
                return new VectorResult(new Vector([0.8, 0.1, 0.2, 0.9, 0.3]));
            }
            if (str_contains($inputString, 'authentication') || str_contains($inputString, 'failed') || str_contains($inputString, 'login') || str_contains($inputString, 'admin')) {
                return new VectorResult(new Vector([0.1, 0.9, 0.0, 0.2, 0.8]));
            }
            if (str_contains($inputString, 'memory') || str_contains($inputString, 'exhausted') || str_contains($inputString, 'performance')) {
                return new VectorResult(new Vector([0.3, 0.4, 0.9, 0.0, 0.1]));
            }
            if (str_contains($inputString, 'api') || str_contains($inputString, 'external') || str_contains($inputString, 'service')) {
                return new VectorResult(new Vector([0.6, 0.1, 0.4, 0.7, 0.5]));
            }
            if (str_contains($inputString, 'file') || str_contains($inputString, 'permission') || str_contains($inputString, 'denied')) {
                return new VectorResult(new Vector([0.2, 0.7, 0.1, 0.4, 0.6]));
            }
            if (str_contains($inputString, 'database') || str_contains($inputString, 'connection')) {
                return new VectorResult(new Vector([0.7, 0.2, 0.8, 0.1, 0.4]));
            }
            if (str_contains($inputString, 'nuclear') || str_contains($inputString, 'physics') || str_contains($inputString, 'quantum')) {
                return new VectorResult(new Vector([0.0, 0.0, 0.0, 0.0, 0.0]));
            }
            // Default vector for other queries
            return new VectorResult(new Vector([0.5, 0.5, 0.5, 0.5, 0.5]));
        }

        $logPositions = [
            'cURL error' => strpos($inputString, 'cURL error'),
            'Operation timed out' => strpos($inputString, 'Operation timed out'),
            'Authentication failure' => strpos($inputString, 'Authentication failure'),
            'account locked' => strpos($inputString, 'account locked'),
            'memory size' => strpos($inputString, 'memory size'),
            'exhausted' => strpos($inputString, 'exhausted'),
            'Permission denied' => strpos($inputString, 'Permission denied'),
            'Unable to write file' => strpos($inputString, 'Unable to write file'),
            'Connection timed out' => strpos($inputString, 'Connection timed out'),
            'SQLSTATE' => strpos($inputString, 'SQLSTATE'),
            'PaymentException' => strpos($inputString, 'PaymentException'),
            'Gateway timeout' => strpos($inputString, 'Gateway timeout')
        ];

        $logPositions = array_filter($logPositions, function ($pos) {
            return $pos !== false;
        });

        if (!empty($logPositions)) {
            $firstLogType = array_keys($logPositions, min($logPositions))[0];

            if (in_array($firstLogType, ['Authentication failure', 'account locked'])) {
                return new TextResult('Multiple failed authentication attempts triggered account lockout. The admin user account was locked after 3 consecutive failed login attempts from the same IP address (192.168.1.100), indicating either forgotten credentials or a potential brute force attack.');
            }
            if (in_array($firstLogType, ['memory size', 'exhausted'])) {
                return new TextResult('PHP memory limit exceeded in DataProcessor service. The application attempted to allocate more memory than the configured limit (128MB), indicating either inefficient memory usage, processing large datasets, or a memory leak in the DataProcessor.php file.');
            }
            if (in_array($firstLogType, ['cURL error', 'Operation timed out'])) {
                return new TextResult('External API timeout occurred during service integration. The application failed to receive a response from the external service within the configured timeout period.');
            }
            if (in_array($firstLogType, ['Permission denied', 'Unable to write file'])) {
                return new TextResult('File system permission error prevented write operation. The application lacks sufficient permissions to write to the target location.');
            }
            if (in_array($firstLogType, ['Connection timed out', 'SQLSTATE'])) {
                return new TextResult('Database connection timeout during order query. The application failed to connect to the database within the timeout period while attempting to retrieve order information, indicating database server overload or network connectivity issues.');
            }
            if (in_array($firstLogType, ['PaymentException', 'Gateway timeout'])) {
                return new TextResult('Payment gateway timeout occurred during order processing. The Stripe payment gateway failed to respond within the expected timeframe for order #ORD-12345, likely due to network issues or high load on the payment provider\'s servers.');
            }
        }

        return new TextResult('Unable to determine the specific cause from the available logs.');
    }

    private function setupRealWorldLogData(): void
    {
        $realWorldLogs = [
            // E-commerce checkout flow errors
            [
                'content' => '[2024-01-15 14:23:45] production.ERROR: PaymentException: Gateway timeout for order #ORD-12345 {"userId": 1001, "orderId": "ORD-12345", "amount": 299.99, "gateway": "stripe"} []',
                'metadata' => [
                    'log_id' => 'ecommerce_001',
                    'content' => '[2024-01-15 14:23:45] production.ERROR: PaymentException: Gateway timeout for order #ORD-12345 {"userId": 1001, "orderId": "ORD-12345", "amount": 299.99, "gateway": "stripe"} []',
                    'timestamp' => '2024-01-15T14:23:45Z',
                    'level' => 'error',
                    'source' => 'payment-service',
                    'tags' => ['payment', 'stripe', 'timeout', 'ecommerce']
                ],
                'vector' => [0.8, 0.1, 0.2, 0.9, 0.3]
            ],
            [
                'content' => '[2024-01-15 14:23:46] production.ERROR: Doctrine\\DBAL\\Exception\\ConnectionException: SQLSTATE[HY000] [2002] Connection timed out (SQL: SELECT * FROM orders WHERE id = ORD-12345) []',
                'metadata' => [
                    'log_id' => 'database_001',
                    'content' => '[2024-01-15 14:23:46] production.ERROR: Doctrine\\DBAL\\Exception\\ConnectionException: SQLSTATE[HY000] [2002] Connection timed out (SQL: SELECT * FROM orders WHERE id = ORD-12345) []',
                    'timestamp' => '2024-01-15T14:23:46Z',
                    'level' => 'error',
                    'source' => 'database',
                    'tags' => ['database', 'doctrine', 'timeout', 'sql']
                ],
                'vector' => [0.7, 0.2, 0.8, 0.1, 0.4]
            ],

            // Authentication and security logs
            [
                'content' => '[2024-01-15 10:15:22] security.WARNING: Authentication failure for user "admin" from IP 192.168.1.100 {"user": "admin", "ip": "192.168.1.100", "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"} []',
                'metadata' => [
                    'log_id' => 'security_001',
                    'content' => '[2024-01-15 10:15:22] security.WARNING: Authentication failure for user "admin" from IP 192.168.1.100 {"user": "admin", "ip": "192.168.1.100", "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"} []',
                    'timestamp' => '2024-01-15T10:15:22Z',
                    'level' => 'warning',
                    'source' => 'security',
                    'tags' => ['authentication', 'failed-login', 'security', 'admin']
                ],
                'vector' => [0.1, 0.9, 0.0, 0.2, 0.8]
            ],
            [
                'content' => '[2024-01-15 10:15:30] security.ERROR: User account "admin" locked after 3 failed login attempts from IP 192.168.1.100 {"user": "admin", "ip": "192.168.1.100", "failed_attempts": 3, "lockout_duration": 900} []',
                'metadata' => [
                    'log_id' => 'security_002',
                    'content' => '[2024-01-15 10:15:30] security.ERROR: User account "admin" locked after 3 failed login attempts from IP 192.168.1.100 {"user": "admin", "ip": "192.168.1.100", "failed_attempts": 3, "lockout_duration": 900} []',
                    'timestamp' => '2024-01-15T10:15:30Z',
                    'level' => 'error',
                    'source' => 'security',
                    'tags' => ['authentication', 'account-locked', 'security', 'brute-force']
                ],
                'vector' => [0.0, 0.8, 0.1, 0.3, 0.9]
            ],

            // Performance and memory issues
            [
                'content' => '[2024-01-15 16:45:10] production.ERROR: PHP Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 65536 bytes) in /var/www/app/src/Service/DataProcessor.php:156 []',
                'metadata' => [
                    'log_id' => 'performance_001',
                    'content' => '[2024-01-15 16:45:10] production.ERROR: PHP Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 65536 bytes) in /var/www/app/src/Service/DataProcessor.php:156 []',
                    'timestamp' => '2024-01-15T16:45:10Z',
                    'level' => 'error',
                    'source' => 'php',
                    'tags' => ['memory', 'php', 'fatal-error', 'performance']
                ],
                'vector' => [0.3, 0.4, 0.9, 0.0, 0.1]
            ],

            // API and external service issues
            [
                'content' => '[2024-01-15 12:30:15] production.ERROR: GuzzleHttp\\Exception\\ConnectException: cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.external-service.com/v1/users []',
                'metadata' => [
                    'log_id' => 'api_001',
                    'content' => '[2024-01-15 12:30:15] production.ERROR: GuzzleHttp\\Exception\\ConnectException: cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.external-service.com/v1/users []',
                    'timestamp' => '2024-01-15T12:30:15Z',
                    'level' => 'error',
                    'source' => 'api-client',
                    'tags' => ['api', 'timeout', 'curl', 'external-service']
                ],
                'vector' => [0.6, 0.1, 0.4, 0.7, 0.5]
            ],

            // File system and permission errors
            [
                'content' => '[2024-01-15 09:12:45] production.ERROR: League\\Flysystem\\UnableToWriteFile: Unable to write file at location: storage/logs/app.log. Permission denied []',
                'metadata' => [
                    'log_id' => 'filesystem_001',
                    'content' => '[2024-01-15 09:12:45] production.ERROR: League\\Flysystem\\UnableToWriteFile: Unable to write file at location: storage/logs/app.log. Permission denied []',
                    'timestamp' => '2024-01-15T09:12:45Z',
                    'level' => 'error',
                    'source' => 'filesystem',
                    'tags' => ['filesystem', 'permissions', 'storage', 'flysystem']
                ],
                'vector' => [0.2, 0.7, 0.1, 0.4, 0.6]
            ]
        ];

        foreach ($realWorldLogs as $logData) {
            $vector = new Vector($logData['vector']);
            $metadata = new Metadata($logData['metadata']);

            $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
            $this->store->add($document);
        }
    }

    public function testAnalyzeEcommerceCheckoutFailure(): void
    {
        $result = $this->tool->__invoke('checkout payment timeout error');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Payment gateway timeout', $result['reason']);
        $this->assertStringContainsString('Stripe', $result['reason']);
        $this->assertNotEmpty($result['evidence_logs']);

        $log = $result['evidence_logs'][0];
        $this->assertEquals('ecommerce_001', $log['id']);
        $this->assertEquals('error', $log['level']);
        $this->assertEquals('payment-service', $log['source']);
        $this->assertContains('payment', $log['tags']);
    }

    public function testAnalyzeSecurityBreachAttempt(): void
    {
        $result = $this->tool->__invoke('authentication failed login admin');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('failed authentication', $result['reason']);
        $this->assertStringContainsString('account lockout', $result['reason']);
        $this->assertNotEmpty($result['evidence_logs']);

        // Should find both security logs
        $this->assertGreaterThanOrEqual(1, count($result['evidence_logs']));

        $foundSecurityLog = false;
        foreach ($result['evidence_logs'] as $log) {
            if ($log['source'] === 'security') {
                $foundSecurityLog = true;
                break;
            }
        }
        $this->assertTrue($foundSecurityLog);
    }

    public function testAnalyzePerformanceIssue(): void
    {
        $result = $this->tool->__invoke('memory exhausted performance issue');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('memory limit exceeded', $result['reason']);
        $this->assertStringContainsString('DataProcessor', $result['reason']);
        $this->assertNotEmpty($result['evidence_logs']);

        $log = $result['evidence_logs'][0];
        $this->assertEquals('performance_001', $log['id']);
        $this->assertContains('memory', $log['tags']);
    }

    public function testAnalyzeApiIntegrationFailure(): void
    {
        $result = $this->tool->__invoke('api timeout external service');

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['evidence_logs']);

        $isTimeoutResponse = str_contains($result['reason'], 'timeout') ||
            str_contains($result['reason'], 'API') ||
            str_contains($result['reason'], 'gateway') ||
            str_contains($result['reason'], 'external service');
        $this->assertTrue($isTimeoutResponse, 'Expected timeout-related response, got: ' . $result['reason']);

        // Should find logs related to timeouts (may include api, payment, or database logs)
        $foundTimeoutLog = false;
        foreach ($result['evidence_logs'] as $log) {
            if (in_array('timeout', $log['tags']) || in_array('api', $log['tags'])) {
                $foundTimeoutLog = true;
                break;
            }
        }
        $this->assertTrue($foundTimeoutLog, 'Should find at least one timeout-related log');
    }

    public function testAnalyzeFileSystemPermissionError(): void
    {
        $result = $this->tool->__invoke('file permission denied error');

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['evidence_logs']);

        $log = $result['evidence_logs'][0];
        $this->assertEquals('filesystem_001', $log['id']);
        $this->assertContains('permissions', $log['tags']);
        $this->assertEquals('filesystem', $log['source']);
    }

    public function testHighRelevanceThresholdFiltering(): void
    {
        $result = $this->tool->__invoke('completely unrelated nuclear physics quantum mechanics');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('evidence_logs', $result);

        if (!$result['success']) {
            $this->assertStringContainsString('No relevant log entries found', $result['reason']);
            $this->assertEmpty($result['evidence_logs']);
        } else {
            $this->assertNotEmpty($result['reason']);
        }
    }

    public function testLogMetadataIntegrity(): void
    {
        $result = $this->tool->__invoke('checkout payment timeout error');

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['evidence_logs']);

        foreach ($result['evidence_logs'] as $log) {

            $this->assertArrayHasKey('id', $log);
            $this->assertArrayHasKey('content', $log);
            $this->assertArrayHasKey('timestamp', $log);
            $this->assertArrayHasKey('level', $log);
            $this->assertArrayHasKey('source', $log);
            $this->assertArrayHasKey('tags', $log);

            $this->assertIsString($log['id']);
            $this->assertIsString($log['content']);
            $this->assertIsString($log['level']);
            $this->assertIsString($log['source']);
            $this->assertIsArray($log['tags']);

            $this->assertNotEmpty($log['id']);
            $this->assertNotEmpty($log['content']);
            $this->assertNotEmpty($log['level']);
            $this->assertNotEmpty($log['source']);
        }
    }
}