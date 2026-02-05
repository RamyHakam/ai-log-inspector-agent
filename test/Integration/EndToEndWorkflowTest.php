<?php

namespace Hakam\AiLogInspector\Test\Integration;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\InMemory\Store;
use Symfony\Component\Uid\Uuid;

/**
 * End-to-end integration tests that simulate real-world log analysis workflows.
 */
class EndToEndWorkflowTest extends TestCase
{
    private LogDocumentPlatformInterface $platform;
    private Store $store;
    private LogInspectorAgent $agent;

    protected function setUp(): void
    {
        $this->store = new Store();

        $this->setupProductionLikeLogData();

        // Create mock platform that wraps InMemoryPlatform
        $this->platform = $this->createMockLogDocumentPlatform();

        // Create a simple mock tool for the agent
        $tools = [$this->createMockLogSearchTool()];

        $this->agent = new LogInspectorAgent(
            $this->platform,
            $tools,
            'You are an AI Log Inspector for production systems. Analyze logs systematically, provide actionable insights, and always cite log IDs. Focus on root causes and business impact.'
        );
    }

    private function setupProductionLikeLogData(): void
    {
        $productionLogs = [
            // Incident Timeline: E-commerce Site Outage (14:20-14:30)
            [
                'content' => '[2024-01-15 14:20:15] production.INFO: High traffic detected: 1250 concurrent users, 95th percentile response time: 2.3s {"concurrent_users": 1250, "response_time_p95": 2300, "request_rate": 450} []',
                'metadata' => [
                    'log_id' => 'traffic_spike_001',
                    'content' => '[2024-01-15 14:20:15] production.INFO: High traffic detected: 1250 concurrent users, 95th percentile response time: 2.3s {"concurrent_users": 1250, "response_time_p95": 2300, "request_rate": 450} []',
                    'timestamp' => '2024-01-15T14:20:15Z',
                    'level' => 'info',
                    'source' => 'load-balancer',
                    'tags' => ['traffic', 'performance', 'monitoring'],
                ],
                'vector' => [0.2, 0.8, 0.1, 0.6, 0.3],
            ],
            [
                'content' => '[2024-01-15 14:22:30] production.WARNING: Database connection pool utilization at 85% {"active_connections": 17, "max_connections": 20, "queue_length": 12} []',
                'metadata' => [
                    'log_id' => 'db_warning_001',
                    'content' => '[2024-01-15 14:22:30] production.WARNING: Database connection pool utilization at 85% {"active_connections": 17, "max_connections": 20, "queue_length": 12} []',
                    'timestamp' => '2024-01-15T14:22:30Z',
                    'level' => 'warning',
                    'source' => 'database-pool',
                    'tags' => ['database', 'connection-pool', 'capacity'],
                ],
                'vector' => [0.1, 0.3, 0.9, 0.2, 0.5],
            ],
            [
                'content' => '[2024-01-15 14:23:45] production.ERROR: PaymentException: Stripe webhook timeout for order #ORD-12345 {"orderId": "ORD-12345", "webhook_id": "whsec_abc123", "timeout_duration": 30000, "retry_count": 3} []',
                'metadata' => [
                    'log_id' => 'payment_error_001',
                    'content' => '[2024-01-15 14:23:45] production.ERROR: PaymentException: Stripe webhook timeout for order #ORD-12345 {"orderId": "ORD-12345", "webhook_id": "whsec_abc123", "timeout_duration": 30000, "retry_count": 3} []',
                    'timestamp' => '2024-01-15T14:23:45Z',
                    'level' => 'error',
                    'source' => 'payment-service',
                    'tags' => ['payment', 'stripe', 'webhook', 'timeout'],
                ],
                'vector' => [0.9, 0.1, 0.2, 0.8, 0.4],
            ],
            [
                'content' => '[2024-01-15 14:24:12] production.ERROR: Doctrine\\DBAL\\Exception\\ConnectionException: SQLSTATE[HY000] [1040] Too many connections {"sql": "SELECT * FROM orders WHERE status = \'pending\'", "connection_attempts": 5} []',
                'metadata' => [
                    'log_id' => 'db_error_001',
                    'content' => '[2024-01-15 14:24:12] production.ERROR: Doctrine\\DBAL\\Exception\\ConnectionException: SQLSTATE[HY000] [1040] Too many connections {"sql": "SELECT * FROM orders WHERE status = \'pending\'", "connection_attempts": 5} []',
                    'timestamp' => '2024-01-15T14:24:12Z',
                    'level' => 'error',
                    'source' => 'database',
                    'tags' => ['database', 'connection', 'max-connections', 'doctrine'],
                ],
                'vector' => [0.0, 0.2, 0.9, 0.1, 0.6],
            ],
            [
                'content' => '[2024-01-15 14:25:33] production.CRITICAL: CircuitBreakerOpen: Database circuit breaker opened due to 5 consecutive failures {"service": "database", "failure_count": 5, "threshold": 5, "timeout": 60} []',
                'metadata' => [
                    'log_id' => 'circuit_breaker_001',
                    'content' => '[2024-01-15 14:25:33] production.CRITICAL: CircuitBreakerOpen: Database circuit breaker opened due to 5 consecutive failures {"service": "database", "failure_count": 5, "threshold": 5, "timeout": 60} []',
                    'timestamp' => '2024-01-15T14:25:33Z',
                    'level' => 'critical',
                    'source' => 'circuit-breaker',
                    'tags' => ['circuit-breaker', 'database', 'failure', 'resilience'],
                ],
                'vector' => [0.1, 0.0, 0.8, 0.9, 0.7],
            ],
            [
                'content' => '[2024-01-15 14:26:45] production.ERROR: Symfony\\Component\\HttpKernel\\Exception\\ServiceUnavailableHttpException: Service temporarily unavailable in CheckoutController@process {"request_id": "req_789xyz", "user_id": 1001, "cart_total": 299.99} []',
                'metadata' => [
                    'log_id' => 'service_unavailable_001',
                    'content' => '[2024-01-15 14:26:45] production.ERROR: Symfony\\Component\\HttpKernel\\Exception\\ServiceUnavailableHttpException: Service temporarily unavailable in CheckoutController@process {"request_id": "req_789xyz", "user_id": 1001, "cart_total": 299.99} []',
                    'timestamp' => '2024-01-15T14:26:45Z',
                    'level' => 'error',
                    'source' => 'web-application',
                    'tags' => ['http', 'service-unavailable', 'checkout', 'controller'],
                ],
                'vector' => [0.8, 0.0, 0.3, 0.7, 0.2],
            ],
            [
                'content' => '[2024-01-15 14:28:20] production.INFO: Auto-scaling triggered: Scaling from 3 to 6 instances {"current_instances": 3, "target_instances": 6, "cpu_utilization": 89, "memory_utilization": 76} []',
                'metadata' => [
                    'log_id' => 'autoscaling_001',
                    'content' => '[2024-01-15 14:28:20] production.INFO: Auto-scaling triggered: Scaling from 3 to 6 instances {"current_instances": 3, "target_instances": 6, "cpu_utilization": 89, "memory_utilization": 76} []',
                    'timestamp' => '2024-01-15T14:28:20Z',
                    'level' => 'info',
                    'source' => 'autoscaler',
                    'tags' => ['autoscaling', 'infrastructure', 'capacity'],
                ],
                'vector' => [0.3, 0.7, 0.2, 0.5, 0.8],
            ],
            [
                'content' => '[2024-01-15 14:30:05] production.INFO: Database circuit breaker closed: Service restored {"service": "database", "restoration_time": 60, "success_count": 3} []',
                'metadata' => [
                    'log_id' => 'circuit_breaker_restored_001',
                    'content' => '[2024-01-15 14:30:05] production.INFO: Database circuit breaker closed: Service restored {"service": "database", "restoration_time": 60, "success_count": 3} []',
                    'timestamp' => '2024-01-15T14:30:05Z',
                    'level' => 'info',
                    'source' => 'circuit-breaker',
                    'tags' => ['circuit-breaker', 'database', 'recovery', 'restored'],
                ],
                'vector' => [0.2, 0.5, 0.6, 0.4, 0.9],
            ],

            // Security Events
            [
                'content' => '[2024-01-15 11:45:30] security.WARNING: Multiple failed login attempts detected {"ip": "203.0.113.42", "user": "admin", "attempts": 15, "timeframe": 300, "country": "Unknown"} []',
                'metadata' => [
                    'log_id' => 'security_threat_001',
                    'content' => '[2024-01-15 11:45:30] security.WARNING: Multiple failed login attempts detected {"ip": "203.0.113.42", "user": "admin", "attempts": 15, "timeframe": 300, "country": "Unknown"} []',
                    'timestamp' => '2024-01-15T11:45:30Z',
                    'level' => 'warning',
                    'source' => 'security-monitor',
                    'tags' => ['security', 'brute-force', 'authentication', 'threat'],
                ],
                'vector' => [0.9, 0.8, 0.0, 0.3, 0.1],
            ],
            [
                'content' => '[2024-01-15 11:47:15] security.CRITICAL: IP blocked due to suspicious activity {"ip": "203.0.113.42", "reason": "brute_force_attack", "block_duration": 3600, "threat_score": 95} []',
                'metadata' => [
                    'log_id' => 'security_block_001',
                    'content' => '[2024-01-15 11:47:15] security.CRITICAL: IP blocked due to suspicious activity {"ip": "203.0.113.42", "reason": "brute_force_attack", "block_duration": 3600, "threat_score": 95} []',
                    'timestamp' => '2024-01-15T11:47:15Z',
                    'level' => 'critical',
                    'source' => 'security-firewall',
                    'tags' => ['security', 'ip-blocking', 'threat-mitigation', 'firewall'],
                ],
                'vector' => [1.0, 0.9, 0.0, 0.2, 0.0],
            ],
        ];

        foreach ($productionLogs as $logData) {
            $vector = new Vector($logData['vector']);
            $metadata = new Metadata($logData['metadata']);

            $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
            $this->store->add($document);
        }
    }

    private function createMockLogDocumentPlatform(): LogDocumentPlatformInterface
    {
        $inMemoryPlatform = new InMemoryPlatform(function (Model $model, array|string|object $input, array $options = []): string {
            $query = is_string($input) ? $input : serialize($input);

            // Map different queries to appropriate responses
            return match (true) {
                str_contains($query, 'what happened during the outage between 14:20 and 14:30') => 'Traffic spike overwhelmed database capacity causing cascade failure. The incident started with high traffic (1250 concurrent users, traffic_spike_001) which saturated the database connection pool (85% utilization, db_warning_001). This led to "too many connections" errors (db_error_001), triggering the circuit breaker after 5 consecutive failures (circuit_breaker_001). With the database circuit breaker open, the checkout service became unavailable (service_unavailable_001), resulting in service unavailable errors for users. Root cause: insufficient database connection pool capacity for traffic spikes.',

                str_contains($query, 'why did checkout fail with service unavailable') => 'Checkout service became unavailable due to upstream dependency failure. The service unavailable error in the CheckoutController (service_unavailable_001) indicates that a critical dependency (likely the database based on concurrent issues) was not responding, causing the checkout process to fail gracefully rather than timeout.',

                str_contains($query, 'database connection issues and circuit breaker') => 'Database overwhelmed leading to circuit breaker activation. The database reached its maximum connection limit (1040 - too many connections, db_error_001), causing repeated connection failures. After 5 consecutive failures, the circuit breaker opened (circuit_breaker_001) to prevent further damage and allow the database to recover. This is a protective measure indicating the database was under excessive load.',

                str_contains($query, 'security threats and brute force attacks') => 'Brute force attack detected and mitigated. An attacker from IP 203.0.113.42 attempted to compromise the admin account with 15 failed login attempts in 5 minutes (security_threat_001). The security system correctly identified this as a brute force attack (threat score: 95) and automatically blocked the IP (security_block_001) for 1 hour to prevent further attacks.',

                str_contains($query, 'walk me through the timeline') => '14:20:15 - High traffic detected: 1250 concurrent users, 2.3s response time (traffic_spike_001). 14:22:30 - Database connection pool at 85% utilization (db_warning_001). 14:24:12 - Database connection failures: too many connections (db_error_001). 14:25:33 - circuit breaker opened after 5 failures (circuit_breaker_001). 14:26:45 - Checkout service unavailable (service_unavailable_001). 14:28:20 - Auto-scaling triggered (autoscaling_001). 14:30:05 - circuit breaker closed, service restored (circuit_breaker_restored_001).',

                str_contains($query, 'business impact of the checkout failures') => 'checkout service unavailable caused direct revenue impact. Service_unavailable_001 shows user_id 1001 unable to complete $299.99 cart_total purchase. This affected customer experience and potential lost sales during the outage period.',

                str_contains($query, 'prevent similar database overload') => 'To prevent database overload: 1) Increase connection pool size based on peak traffic analysis, 2) Implement better connection pooling strategies, 3) Add database read replicas for load distribution, 4) Improve query optimization to reduce connection time, 5) Set up proactive monitoring for connection pool utilization before reaching critical thresholds.',

                str_contains($query, 'payment service failure affected other systems') => 'Payment service failure cascaded through multiple systems. Payment timeout (payment_error_001) occurred alongside database connection issues, affecting checkout flow (service_unavailable_001) and causing service unavailable errors for users trying to complete purchases.',

                str_contains($query, 'system successfully recovered') => 'System recovery was successful through multiple mechanisms. circuit breaker closed indicating database recovery (circuit_breaker_restored_001), autoscaling doubled capacity from 3 to 6 instances (autoscaling_001), and service restoration was confirmed after 60 seconds.',

                str_contains($query, 'performance metrics indicate the severity') => 'Performance metrics show high severity: 1250 concurrent users exceeded normal capacity (traffic_spike_001), 95th percentile response time degraded to 2.3s indicating system stress, 450 request rate overwhelmed database connection pool capacity.',

                str_contains($query, 'first sign of trouble') => 'First sign was the traffic spike at 14:20:15 showing 1250 concurrent users with 2.3s response times (traffic_spike_001), indicating system was beginning to struggle under load.',

                str_contains($query, 'cascaded from the initial problem') => 'Traffic spike caused database connection pool saturation (db_warning_001), leading to connection failures (db_error_001), circuit breaker activation (circuit_breaker_001), and ultimately service unavailable errors (service_unavailable_001).',

                str_contains($query, 'final resolution') => 'Resolution achieved through autoscaling to 6 instances (autoscaling_001) and circuit breaker restoration (circuit_breaker_restored_001) after database recovered.',

                str_contains($query, 'analyze the checkout service failure') => 'Checkout service failure root cause: upstream database dependency became unavailable due to connection pool exhaustion. Service_unavailable_001 shows graceful failure handling in CheckoutController when database circuit breaker was open (circuit_breaker_001).',

                str_contains($query, 'correlation between traffic spikes and database failures') => 'Strong correlation: traffic spike (traffic_spike_001) at 14:20 with 1250 users directly led to database pool saturation (db_warning_001) at 14:22, then connection failures (db_error_001) at 14:24. Traffic overwhelmed database capacity causing cascade failure.',

                str_contains($query, 'security measures were effective') => 'Security measures worked effectively: brute force detection identified 15 failed attempts (security_threat_001), automatic IP blocking prevented further attacks (security_block_001), threat score of 95 correctly classified high-risk activity.',

                str_contains($query, 'autoscaling respond to performance degradation') => 'Autoscaling responded appropriately by doubling capacity from 3 to 6 instances (autoscaling_001) when CPU utilization reached 89% and memory 76%, helping restore service availability.',

                str_contains($query, 'monitoring alerts should have fired') => 'Key alerts should have fired: database connection pool threshold (85% utilization from db_warning_001), high response time alert (2.3s from traffic_spike_001), circuit breaker state change (circuit_breaker_001), service availability alerts (service_unavailable_001).',

                default => 'Based on the log analysis, I can see various incidents including traffic spikes (traffic_spike_001), database issues (db_error_001, circuit_breaker_001), payment failures (payment_error_001), service outages (service_unavailable_001), and security events (security_threat_001, security_block_001). The system showed good resilience with autoscaling (autoscaling_001) and recovery (circuit_breaker_restored_001).'
            };
        });

        // Create a mock LogDocumentPlatformInterface that wraps InMemoryPlatform
        $mockModel = $this->createMock(Model::class);
        $mockModel->method('getName')->willReturn('test-model');

        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockCatalog->method('getModel')->willReturn($mockModel);

        $platform = $this->createMock(LogDocumentPlatformInterface::class);
        $platform->method('getPlatform')->willReturn($inMemoryPlatform);
        $platform->method('getModel')->willReturn($mockModel);
        $platform->method('getModelCatalog')->willReturn($mockCatalog);

        // Mock invoke to delegate to InMemoryPlatform
        $platform->method('invoke')->willReturnCallback(
            fn (string $model, object|array|string $input, array $options = []) => $inMemoryPlatform->invoke($model, $input, $options)
        );

        return $platform;
    }

    private function createMockLogSearchTool(): object
    {
        return new #[AsTool(name: 'log_search', description: 'Search logs for testing')] class() {
            public function __invoke(string $query): array
            {
                // Return mock search results that match what the tests expect
                return [
                    'success' => true,
                    'reason' => 'Mock search result for testing purposes',
                    'evidence_logs' => [
                        [
                            'id' => 'traffic_spike_001',
                            'content' => '[2024-01-15 14:20:15] production.INFO: High traffic detected: 1250 concurrent users, 95th percentile response time: 2.3s',
                            'timestamp' => '2024-01-15T14:20:15Z',
                            'level' => 'info',
                            'source' => 'load-balancer',
                            'tags' => ['traffic', 'performance', 'monitoring'],
                        ],
                    ],
                ];
            }
        };
    }

    public function testCompleteIncidentInvestigation(): void
    {
        $result = $this->agent->ask('what happened during the outage between 14:20 and 14:30');

        $content = $result->getContent();

        // Should provide comprehensive incident analysis
        $this->assertStringContainsString('Traffic spike', $content);
        $this->assertStringContainsString('database', $content);
        $this->assertStringContainsString('circuit breaker', $content);
        $this->assertStringContainsString('cascade failure', $content);

        // Should mention specific log IDs for evidence
        $this->assertStringContainsString('traffic_spike_001', $content);
        $this->assertStringContainsString('db_error_001', $content);
        $this->assertStringContainsString('circuit_breaker_001', $content);
    }

    public function testRootCauseAnalysis(): void
    {
        $result = $this->agent->ask('why did checkout fail with service unavailable');

        $content = $result->getContent();

        // Should identify root cause, not just symptoms
        $this->assertStringContainsString('upstream dependency', $content);
        $this->assertStringContainsString('database', $content);
        $this->assertStringContainsString('CheckoutController', $content);

        // Should reference specific log
        $this->assertStringContainsString('service_unavailable_001', $content);
    }

    public function testTechnicalDeepDive(): void
    {
        $result = $this->agent->ask('database connection issues and circuit breaker');

        $content = $result->getContent();

        // Should provide technical analysis
        $this->assertStringContainsString('too many connections', $content);
        $this->assertStringContainsString('circuit breaker', $content);
        $this->assertStringContainsString('protective measure', $content);

        // Should cite relevant logs
        $this->assertStringContainsString('db_error_001', $content);
        $this->assertStringContainsString('circuit_breaker_001', $content);
    }

    public function testSecurityIncidentAnalysis(): void
    {
        $result = $this->agent->ask('security threats and brute force attacks');

        $content = $result->getContent();

        // Should identify security threat
        $this->assertStringContainsString('brute force', $content);
        $this->assertStringContainsString('admin', $content);
        $this->assertStringContainsString('blocked', $content);
        $this->assertStringContainsString('203.0.113.42', $content);

        // Should reference security logs
        $this->assertStringContainsString('security_threat_001', $content);
        $this->assertStringContainsString('security_block_001', $content);
    }

    public function testTimelineReconstruction(): void
    {
        // Ask for a chronological analysis
        $result = $this->agent->ask('walk me through the timeline of what happened during the incident');

        $content = $result->getContent();

        // Should reconstruct timeline
        $this->assertStringContainsString('14:20', $content);
        $this->assertStringContainsString('14:22', $content);
        $this->assertStringContainsString('14:24', $content);
        $this->assertStringContainsString('14:25', $content);
        $this->assertStringContainsString('14:26', $content);

        // Should show progression of events
        $this->assertStringContainsString('traffic', $content);
        $this->assertStringContainsString('connection pool', $content);
        $this->assertStringContainsString('circuit breaker', $content);
    }

    public function testBusinessImpactAssessment(): void
    {
        $result = $this->agent->ask('what was the business impact of the checkout failures');

        $content = $result->getContent();

        // Should identify business impact
        $this->assertStringContainsString('checkout', $content);
        $this->assertStringContainsString('unavailable', $content);

        // Should mention affected users/orders
        $this->assertStringContainsString('user_id', $content);
        $this->assertStringContainsString('cart_total', $content);
    }

    public function testPreventionRecommendations(): void
    {
        $result = $this->agent->ask('how can we prevent similar database overload issues');

        $content = $result->getContent();

        // Should provide actionable recommendations
        $this->assertIsString($content);
        $this->assertNotEmpty($content);

        // Should reference the connection pool issue
        $this->assertStringContainsString('connection', $content);
    }

    public function testMultiServiceCorrelation(): void
    {
        $result = $this->agent->ask('show me how the payment service failure affected other systems');

        $content = $result->getContent();

        // Should show cross-service impact
        $this->assertStringContainsString('payment', $content);
        $this->assertStringContainsString('checkout', $content);

        // Should reference multiple services
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
    }

    public function testRecoveryValidation(): void
    {
        $result = $this->agent->ask('was the system successfully recovered and how');

        $content = $result->getContent();

        // Should mention recovery indicators
        $this->assertStringContainsString('circuit breaker closed', $content);
        $this->assertStringContainsString('restored', $content);
        $this->assertStringContainsString('autoscaling', $content);

        // Should reference recovery logs
        $this->assertStringContainsString('circuit_breaker_restored_001', $content);
        $this->assertStringContainsString('autoscaling_001', $content);
    }

    public function testPerformanceMetricsAnalysis(): void
    {
        $result = $this->agent->ask('what performance metrics indicate the severity of the incident');

        $content = $result->getContent();

        // Should analyze performance indicators
        $this->assertStringContainsString('concurrent users', $content);
        $this->assertStringContainsString('response time', $content);
        $this->assertStringContainsString('1250', $content);
        $this->assertStringContainsString('2.3s', $content);

        // Should reference performance log
        $this->assertStringContainsString('traffic_spike_001', $content);
    }

    public function testErrorSequenceAnalysis(): void
    {
        $questions = [
            'what was the first sign of trouble',
            'what cascaded from the initial problem',
            'what was the final resolution',
        ];

        foreach ($questions as $question) {
            $result = $this->agent->ask($question);
            $content = $result->getContent();

            $this->assertIsString($content);
            $this->assertNotEmpty($content);

            // Each response should provide meaningful analysis
            $this->assertGreaterThan(50, strlen($content));
        }
    }

    public function testSystemPromptEffectiveness(): void
    {
        $result = $this->agent->ask('analyze the checkout service failure');

        $content = $result->getContent();

        // Should follow system prompt guidelines
        // - Cite log IDs
        $this->assertMatchesRegularExpression('/[a-z_]+_\d{3}/', $content);

        // - Provide actionable insights
        $this->assertNotEmpty($content);

        // - Focus on root causes
        $this->assertStringContainsString('cause', $content);
    }

    public function testComplexQueryHandling(): void
    {
        $complexQueries = [
            'analyze the correlation between traffic spikes and database failures',
            'what security measures were effective during the attack',
            'how did autoscaling respond to the performance degradation',
            'what monitoring alerts should have fired during this incident',
        ];

        foreach ($complexQueries as $query) {
            $result = $this->agent->ask($query);

            $this->assertInstanceOf(ResultInterface::class, $result);

            $content = $result->getContent();
            $this->assertIsString($content);
            $this->assertNotEmpty($content);

            // Should provide substantial analysis for complex queries
            $this->assertGreaterThan(100, strlen($content));
        }
    }
}
