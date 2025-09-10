<?php

namespace Hakam\AiLogInspector\Test\Functional;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizer;
use Hakam\AiLogInspector\Test\Support\LogFileLoader;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Comprehensive AI Log Inspector Functional Tests
 *
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
        $inMemoryStore = new InMemoryStore();
        $this->store = new VectorLogDocumentStore($inMemoryStore);
        $this->logLoader = new LogFileLoader();

        // Load realistic PHP logs from fixture files
        $this->loadedLogs = $this->logLoader->loadLogsIntoStore($inMemoryStore);

        // Create platform with tool calling model
        $platform = LogDocumentPlatformFactory::create([
            'provider' => 'ollama',
            'host' => $_ENV['OLLAMA_URL'] ?? 'http://localhost:11434',
            'model' => [
                'name' => $_ENV['OLLAMA_MODEL'] ?? 'llama3.2:1b',
                'capabilities' => ['text', 'tool_calling'],
                'options' => [
                    'temperature' => 0.7,
                    'max_tokens' => 1000
                ]
            ]
        ]);

        // Create vectorizer for the LogSearchTool
        $vectorizer = new LogDocumentVectorizer(
            $platform->getPlatform(),
            $platform->getModel()
        );

        // Create production LogSearchTool
        $logSearchTool = new LogSearchTool(
            $this->store,
            $vectorizer,
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
     * Test comprehensive payment system analysis
     */
    public function testAnalyzePaymentSystemIncidents()
    {
        $startTime = microtime(true);
        
        $response = $this->agent->ask('We are experiencing payment failures in our e-commerce system. Please investigate the payment logs and identify the root causes, affected gateways, and provide specific recommendations.');
        
        $duration = microtime(true) - $startTime;
        
        $this->assertNotNull($response);
        $content = $response->getContent();
        
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(100, strlen($content), 'Should provide comprehensive payment analysis');
        $this->assertLessThan(45.0, $duration, 'Should respond within reasonable time');
        
        // Should mention payment-related terms and specific gateways
        $lowerContent = strtolower($content);
        $hasPaymentContent = str_contains($lowerContent, 'payment') || 
                            str_contains($lowerContent, 'stripe') || 
                            str_contains($lowerContent, 'paypal') ||
                            str_contains($lowerContent, 'gateway');
        
        $this->assertTrue($hasPaymentContent, 'Should analyze payment-related issues');
        
        echo "\n💳 Payment system analysis completed!";
        echo "\n📝 Analysis length: " . strlen($content) . " characters";
        echo "\n⏱️ Response time: " . number_format($duration, 2) . "s";
        echo "\n📊 Loaded logs: " . count($this->loadedLogs) . " entries";
    }

    public function testAnalyzeDatabasePerformanceIssues()
    {
        $response = $this->agent->ask('Our application is experiencing database-related performance issues. Please analyze the database logs and identify connection problems, slow queries, and provide optimization recommendations.');
        
        $this->assertNotNull($response);
        $content = $response->getContent();
        
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(150, strlen($content));
        
        // Should mention database-related terms
        $lowerContent = strtolower($content);
        $hasDatabaseContent = str_contains($lowerContent, 'database') || 
                             str_contains($lowerContent, 'connection') || 
                             str_contains($lowerContent, 'mysql') ||
                             str_contains($lowerContent, 'doctrine') ||
                             str_contains($lowerContent, 'query');
        
        $this->assertTrue($hasDatabaseContent, 'Should analyze database-related issues');
        
        echo "\n💾 Database performance analysis completed!";
    }

    public function testAnalyzeSecurityIncidents()
    {
        $response = $this->agent->ask('We suspect security threats in our application. Please examine the security logs for authentication failures, potential attacks, and provide security recommendations.');
        
        $this->assertNotNull($response);
        $content = $response->getContent();
        
        $this->assertNotEmpty($content);
        
        // Should mention security-related terms
        $lowerContent = strtolower($content);
        $hasSecurityContent = str_contains($lowerContent, 'security') || 
                             str_contains($lowerContent, 'authentication') || 
                             str_contains($lowerContent, 'attack') ||
                             str_contains($lowerContent, 'injection') ||
                             str_contains($lowerContent, 'brute');
        
        $this->assertTrue($hasSecurityContent, 'Should analyze security-related threats');
        
        echo "\n🛡️ Security incident analysis completed!";
    }

    public function testAnalyzeApplicationErrors()
    {
        $response = $this->agent->ask('Investigate PHP application errors including exceptions, memory issues, and type errors. What are the main problems and how can they be resolved?');
        
        $this->assertNotNull($response);
        $content = $response->getContent();
        
        $this->assertNotEmpty($content);
        
        // Should mention application-related terms
        $lowerContent = strtolower($content);
        $hasApplicationContent = str_contains($lowerContent, 'exception') || 
                                str_contains($lowerContent, 'error') || 
                                str_contains($lowerContent, 'memory') ||
                                str_contains($lowerContent, 'php') ||
                                str_contains($lowerContent, 'type');
        
        $this->assertTrue($hasApplicationContent, 'Should analyze application errors');
        
        echo "\n🐛 Application error analysis completed!";
    }

    public function testAnalyzeSystemPerformanceMetrics()
    {
        $response = $this->agent->ask('Analyze system performance issues including CPU usage, memory consumption, API response times, and service availability. What optimizations are needed?');
        
        $this->assertNotNull($response);
        $content = $response->getContent();
        
        $this->assertNotEmpty($content);
        
        // Should mention performance-related terms
        $lowerContent = strtolower($content);
        $hasPerformanceContent = str_contains($lowerContent, 'performance') || 
                                str_contains($lowerContent, 'cpu') || 
                                str_contains($lowerContent, 'memory') ||
                                str_contains($lowerContent, 'response') ||
                                str_contains($lowerContent, 'timeout');
        
        $this->assertTrue($hasPerformanceContent, 'Should analyze performance issues');
        
        echo "\n⚡ System performance analysis completed!";
    }

    public function testReconstructIncidentTimeline()
    {
        $response = $this->agent->ask('Reconstruct the timeline of events from 2025-09-09 between 13:00 and 16:00. What sequence of problems occurred and how are they related?');
        
        $this->assertNotNull($response);
        $content = $response->getContent();
        
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(100, strlen($content), 'Should provide timeline analysis');
        
        // Should mention time-related or sequence terms
        $lowerContent = strtolower($content);
        $hasTimelineContent = str_contains($lowerContent, 'timeline') || 
                             str_contains($lowerContent, 'sequence') || 
                             str_contains($lowerContent, '2025-09-09') ||
                             str_contains($lowerContent, 'event') ||
                             str_contains($lowerContent, 'time');
        
        $this->assertTrue($hasTimelineContent, 'Should provide timeline reconstruction');
        
        echo "\n📅 Incident timeline reconstruction completed!";
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
            
            echo "\n🏷️  {$category}: " . count($logs) . " logs";
        }
    }


    public function testPerformanceWithRealisticLogVolume()
    {
        $questions = [
            'Find payment timeout errors',
            'Check database connection issues', 
            'Identify security threats',
            'Review application exceptions',
            'Analyze performance problems'
        ];
        
        $totalTime = 0;
        $successCount = 0;
        
        foreach ($questions as $question) {
            $startTime = microtime(true);
            
            try {
                $response = $this->agent->ask($question);
                $duration = microtime(true) - $startTime;
                $totalTime += $duration;
                
                $this->assertNotNull($response);
                $this->assertNotEmpty($response->getContent());
                $successCount++;
                
                echo "\n⚡ '{$question}': " . number_format($duration, 2) . "s";
                
            } catch (\Exception $e) {
                echo "\n❌ '{$question}' failed: " . $e->getMessage();
            }
        }
        
        $avgTime = $successCount > 0 ? $totalTime / $successCount : 0;
        
        $this->assertGreaterThan(0, $successCount, 'At least some requests should succeed');
        $this->assertLessThan(30.0, $avgTime, 'Average response time should be reasonable');
        
        echo "\n📊 Performance with realistic logs:";
        echo "\n   • Successful requests: {$successCount}/" . count($questions);
        echo "\n   • Average response time: " . number_format($avgTime, 2) . "s";
        echo "\n   • Total logs analyzed: " . count($this->loadedLogs);
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
                    'max_tokens' => 500
                ]
            ]
        ]);
        
        $response = $platform->__invoke('Analyze this error: PaymentException timeout for order #12345');
        
        $this->assertNotNull($response);
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(30, strlen($content), 'Should provide substantial analysis');
        
        // Should mention payment or timeout
        $lowerContent = strtolower($content);
        $hasRelevantAnalysis = str_contains($lowerContent, 'payment') || 
                              str_contains($lowerContent, 'timeout');
        
        $this->assertTrue($hasRelevantAnalysis, 'Should provide relevant analysis');
        
        echo "\n🏭 Platform factory integration validated!";
    }


    public function testDevOpsUserStoryWorkflows()
    {
        $scenarios = [
            [
                'question' => 'The checkout service is returning 500 errors. What should I check first?',
                'keywords' => ['log', 'error', 'check', 'service']
            ],
            [
                'question' => 'Multiple failed login attempts from the same IP. Is this a security concern?',
                'keywords' => ['security', 'attack', 'block', 'monitor']
            ],
            [
                'question' => 'API response times increased from 200ms to 2 seconds. Where do I start?',
                'keywords' => ['performance', 'slow', 'database', 'memory']
            ]
        ];
        
        foreach ($scenarios as $scenario) {
            $startTime = microtime(true);
            $response = $this->agent->ask($scenario['question']);
            $duration = microtime(true) - $startTime;
            
            $this->assertNotNull($response);
            $content = $response->getContent();
            $this->assertNotEmpty($content);
            $this->assertGreaterThan(50, strlen($content), 'Should provide substantial guidance');
            $this->assertLessThan(20.0, $duration, 'Should respond within reasonable time');
            
            // Check for relevant keywords
            $lowerContent = strtolower($content);
            $hasRelevantContent = false;
            $foundKeywords = [];
            foreach ($scenario['keywords'] as $keyword) {
                if (str_contains($lowerContent, $keyword)) {
                    $hasRelevantContent = true;
                    $foundKeywords[] = $keyword;
                }
            }
            
            // If no exact keywords found, check for related terms
            if (!$hasRelevantContent) {
                $relatedTerms = [
                    'log' => ['logs', 'logging', 'entries'],
                    'error' => ['errors', 'exception', 'failure', 'issue', 'problem'],
                    'check' => ['examine', 'investigate', 'review', 'analyze'],
                    'service' => ['services', 'application', 'system'],
                    'security' => ['auth', 'login', 'authentication', 'breach'],
                    'attack' => ['threat', 'intrusion', 'malicious'],
                    'block' => ['prevent', 'stop', 'restrict'],
                    'monitor' => ['watch', 'track', 'observe'],
                    'performance' => ['slow', 'fast', 'speed', 'optimization'],
                    'database' => ['db', 'sql', 'query', 'connection'],
                    'memory' => ['ram', 'heap', 'allocation']
                ];
                
                foreach ($scenario['keywords'] as $keyword) {
                    if (isset($relatedTerms[$keyword])) {
                        foreach ($relatedTerms[$keyword] as $related) {
                            if (str_contains($lowerContent, $related)) {
                                $hasRelevantContent = true;
                                $foundKeywords[] = "{$keyword} (via {$related})";
                                break 2;
                            }
                        }
                    }
                }
            }
            
            // Debug output for failed matches
            if (!$hasRelevantContent) {
                echo "\n⚠️  No relevant keywords found for: '{$scenario['question']}'";
                echo "\n   Expected: " . implode(', ', $scenario['keywords']);
                echo "\n   Response: " . substr($content, 0, 200) . '...';
            } else {
                echo "\n✅ Found keywords: " . implode(', ', $foundKeywords);
            }
            
            $this->assertTrue($hasRelevantContent, 'Should provide relevant troubleshooting guidance');
        }
        
        echo "\n👥 DevOps user story workflows validated!";
    }


    public function testErrorHandlingAndEdgeCases()
    {
        // Test empty query
        $response = $this->agent->ask('');
        $this->assertNotNull($response);
        $content = $response->getContent();
        $this->assertNotNull($content); // Should handle gracefully
        
        // Test very short query
        $response = $this->agent->ask('Help');
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(5, strlen($content));
        
        // Test irrelevant query
        $response = $this->agent->ask('What is the weather like today?');
        $this->assertNotNull($response);
        
        echo "\n🚫 Error handling and edge cases validated!";
    }


    public function testOverallSystemIntegration()
    {
        // Test that all components work together
        $this->assertNotNull($this->agent, 'Agent should be initialized');
        $this->assertNotNull($this->store, 'Store should be initialized');
        $this->assertNotNull($this->logLoader, 'LogLoader should be initialized');
        $this->assertGreaterThan(30, count($this->loadedLogs), 'Should have loaded substantial logs');
        
        // Test comprehensive analysis
        $response = $this->agent->ask('Perform a comprehensive analysis of all system issues from the logs. What are the top 3 problems and their solutions?');
        
        $this->assertNotNull($response);
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(150, strlen($content), 'Should provide comprehensive analysis');
        
        // Should mention multiple categories
        $lowerContent = strtolower($content);
        $mentionedCategories = 0;
        $categories = ['payment', 'database', 'security', 'application', 'performance'];
        
        foreach ($categories as $category) {
            if (str_contains($lowerContent, $category)) {
                $mentionedCategories++;
            }
        }
        
        $this->assertGreaterThan(1, $mentionedCategories, 'Should analyze multiple problem categories');
        
        echo "\n🎯 Overall system integration validated!";
        echo "\n📊 Final Stats:";
        echo "\n   • Total logs loaded: " . count($this->loadedLogs);
        echo "\n   • Analysis length: " . strlen($content) . " characters";
        echo "\n   • Categories mentioned: {$mentionedCategories}/" . count($categories);
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
                'ignore_errors' => true
            ]
        ]);
        
        $result = @file_get_contents($url . '/api/version', false, $context);
        if ($result === false) {
            return false;
        }
        
        return true; // Skip model capability check for now
    }
}
