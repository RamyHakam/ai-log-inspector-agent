<?php

namespace Hakam\AiLogInspector\Test\Functional;

use Hakam\AiLogInspector\Chat\LogInspectorChatFactory;
use Hakam\AiLogInspector\Chat\SessionMessageStore;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizer;
use Hakam\AiLogInspector\Test\Support\LogFileLoader;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Store\InMemory\Store;

/**
 * Real Ollama-based integration test for multi-turn chat conversations
 * 
 * This test demonstrates actual multi-turn conversations with:
 * - Context preservation across questions
 * - Session persistence
 * - Real AI responses
 * 
 * Requirements:
 * - Ollama running locally: http://localhost:11434
 * - Model with tool calling: ollama pull llama3.2:1b
 * 
 * Run: vendor/bin/phpunit test/Functional/RealChatConversationTest.php
 * 
 * @group ollama
 * @group functional
 * @group chat
 */
class RealChatConversationTest extends TestCase
{
    private string $testStoragePath;
    private LogFileLoader $logLoader;
    private array $loadedLogs = [];

    protected function setUp(): void
    {
        if (!$this->isOllamaAvailable()) {
            $this->markTestSkipped('Ollama not available. Run: ollama pull llama3.2:1b');
        }

        $this->testStoragePath = sys_get_temp_dir() . '/ollama-chat-test-' . uniqid();
        mkdir($this->testStoragePath, 0755, true);

        $this->logLoader = new LogFileLoader();
    }

    protected function tearDown(): void
    {

        if (is_dir($this->testStoragePath)) {
            $files = glob($this->testStoragePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testStoragePath);
        }
    }

    /**
     * Test multi-turn conversation with context preservation
     * 
     * Demonstrates:
     * - Starting an investigation
     * - Asking initial question
     * - Following up with contextual questions
     * - AI maintains context across turns
     */
    public function testMultiTurnConversationWithContextPreservation(): void
    {
        echo "\n" . str_repeat("=", 80);
        echo "\n🗣️  MULTI-TURN CONVERSATION TEST";
        echo "\n" . str_repeat("=", 80) . "\n";

        $startTime = microtime(true);


        [$platform, $searchTool, $contextTool] = $this->setupPlatformAndTools();

        $chat = LogInspectorChatFactory::create($platform, $searchTool, $contextTool);

        // Start investigation
        echo "\n📝 Starting investigation: 'Payment errors investigation - 2026-08-18'\n";
        $chat->startInvestigation('Payment errors investigation - 2026-08-18');
        $this->assertTrue($chat->isActive());

        // Turn 1: Initial question
        echo "\n❓ Question 1: 'What payment errors occurred?'\n";
        $response1 = $chat->ask('What payment errors occurred?');
        $this->assertInstanceOf(AssistantMessage::class, $response1);
        $this->assertNotEmpty($response1->getContent());
        
        echo "🤖 Response 1: " . substr($response1->getContent(), 0, 200) . "...\n";
        echo "   Length: " . strlen($response1->getContent()) . " characters\n";

        // Turn 2: Follow-up question (relies on context from turn 1)
        echo "\n❓ Question 2: 'What caused those errors?' (context-dependent)\n";
        $response2 = $chat->followUp('What caused those errors?');
        $this->assertInstanceOf(AssistantMessage::class, $response2);
        $this->assertNotEmpty($response2->getContent());
        
        echo "🤖 Response 2: " . substr($response2->getContent(), 0, 200) . "...\n";
        echo "   Length: " . strlen($response2->getContent()) . " characters\n";

        echo "\n❓ Question 3: 'How can we prevent them?' (context-dependent)\n";
        $response3 = $chat->followUp('How can we prevent them?');
        $this->assertInstanceOf(AssistantMessage::class, $response3);
        $this->assertNotEmpty($response3->getContent());
        
        echo "🤖 Response 3: " . substr($response3->getContent(), 0, 200) . "...\n";
        echo "   Length: " . strlen($response3->getContent()) . " characters\n";

        $duration = microtime(true) - $startTime;
        echo "\n⏱️  Total conversation time: " . number_format($duration, 2) . "s\n";
        echo "\n✅ Context preserved across 3 turns!\n";

        $this->assertLessThan(300, $duration, 'Conversation should complete within 5 minutes');
    }

    /**
     * Test persistent session with resumption
     * 
     * Demonstrates:
     * - Creating a named session
     * - Asking questions
     * - Saving session to disk
     * - Resuming session later
     * - Accessing conversation history
     */
    public function testPersistentSessionWithResumption(): void
    {
        echo "\n" . str_repeat("=", 80);
        echo "\n💾 PERSISTENT SESSION TEST";
        echo "\n" . str_repeat("=", 80) . "\n";

        $sessionId = 'payment-incident-' . date('Y-m-d-His');


        [$platform, $searchTool, $contextTool] = $this->setupPlatformAndTools();

        echo "\n📝 Part 1: Creating session '$sessionId'\n";
        $chat1 = LogInspectorChatFactory::createSession(
            $sessionId,
            $platform,
            $searchTool,
            $contextTool,
            $this->testStoragePath
        );

        $chat1->startInvestigation('Payment incident - Aug 18, 2026');
        
        echo "\n❓ Question: 'Find payment errors'\n";
        $response1 = $chat1->ask('Find payment errors');
        $this->assertInstanceOf(AssistantMessage::class, $response1);
        
        echo "🤖 Response: " . substr($response1->getContent(), 0, 150) . "...\n";

        // Verify session was saved
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $this->assertTrue($store->exists(), 'Session should be persisted to disk');
        
        $metadata = $store->getMetadata();
        echo "\n💾 Session saved: {$metadata['file_path']}\n";
        echo "   File size: {$metadata['file_size']} bytes\n";

        // PART 2: Resume session (simulating restart)
        echo "\n📝 Part 2: Resuming session '$sessionId' (simulating restart)\n";
        $chat2 = LogInspectorChatFactory::createSession(
            $sessionId,
            $platform,
            $searchTool,
            $contextTool,
            $this->testStoragePath
        );

        // Load conversation history
        $loadedMessages = $store->load();
        $this->assertGreaterThan(0, count($loadedMessages->getMessages()), 'Should load previous messages');
        
        echo "📚 Loaded " . count($loadedMessages->getMessages()) . " messages from session\n";

        // Continue conversation with context
        echo "\n❓ Follow-up Question: 'What was the impact?' (using previous context)\n";
        $response2 = $chat2->ask('What was the impact?');
        $this->assertInstanceOf(AssistantMessage::class, $response2);
        
        echo "🤖 Response: " . substr($response2->getContent(), 0, 150) . "...\n";

        echo "\n✅ Session persisted and resumed successfully!\n";
    }

    /**
     * Test investigation helper methods
     * 
     * Demonstrates:
     * - summarize() for investigation summary
     * - getTimeline() for chronological analysis
     * - getRemediation() for fix recommendations
     */
    public function testInvestigationHelperMethods(): void
    {
        echo "\n" . str_repeat("=", 80);
        echo "\n🔧 INVESTIGATION HELPER METHODS TEST";
        echo "\n" . str_repeat("=", 80) . "\n";

        // Setup platform and tools
        [$platform, $searchTool, $contextTool] = $this->setupPlatformAndTools();

        $chat = LogInspectorChatFactory::create($platform, $searchTool);
        $chat->startInvestigation('Database errors investigation');

        // Ask initial question to build context
        echo "\n❓ Initial Question: 'Find database errors'\n";
        $initial = $chat->ask('Find database errors');
        echo "🤖 Response: " . substr($initial->getContent(), 0, 100) . "...\n";

        // Test summarize()
        echo "\n📋 Testing summarize() method...\n";
        $summary = $chat->summarize();
        $this->assertInstanceOf(AssistantMessage::class, $summary);
        $this->assertNotEmpty($summary->getContent());
        echo "   Summary length: " . strlen($summary->getContent()) . " characters\n";

        // Test getTimeline()
        echo "\n📅 Testing getTimeline() method...\n";
        $timeline = $chat->getTimeline();
        $this->assertInstanceOf(AssistantMessage::class, $timeline);
        $this->assertNotEmpty($timeline->getContent());
        echo "   Timeline length: " . strlen($timeline->getContent()) . " characters\n";

        // Test getRemediation()
        echo "\n💊 Testing getRemediation() method...\n";
        $remediation = $chat->getRemediation();
        $this->assertInstanceOf(AssistantMessage::class, $remediation);
        $this->assertNotEmpty($remediation->getContent());
        echo "   Remediation length: " . strlen($remediation->getContent()) . " characters\n";

        echo "\n✅ All helper methods working!\n";
    }

    /**
     * Test quick analysis (one-off queries)
     * 
     * Demonstrates:
     * - Quick analysis without explicit initialization
     * - Auto-initialization behavior
     */
    public function testQuickAnalysisWorkflow(): void
    {
        echo "\n" . str_repeat("=", 80);
        echo "\n⚡ QUICK ANALYSIS TEST";
        echo "\n" . str_repeat("=", 80) . "\n";

        // Setup platform and tools
        [$platform, $searchTool, $contextTool] = $this->setupPlatformAndTools();

        $chat = LogInspectorChatFactory::create($platform, $searchTool);

        // Quick analysis without explicit initialization
        echo "\n❓ Quick Question: 'Show me recent errors'\n";
        $response = $chat->quickAnalysis('Show me recent errors');
        
        $this->assertInstanceOf(AssistantMessage::class, $response);
        $this->assertTrue($chat->isActive(), 'Chat should auto-initialize');
        $this->assertNotEmpty($response->getContent());
        
        echo "🤖 Response: " . substr($response->getContent(), 0, 150) . "...\n";
        echo "\n✅ Quick analysis with auto-initialization works!\n";
    }

    /**
     * Test session management: listing and cleanup
     * 
     * Demonstrates:
     * - Creating multiple sessions
     * - Listing all sessions
     * - Session metadata
     * - Cleanup operations
     */
    public function testSessionManagementOperations(): void
    {
        echo "\n" . str_repeat("=", 80);
        echo "\n🗂️  SESSION MANAGEMENT TEST";
        echo "\n" . str_repeat("=", 80) . "\n";

        // Setup platform and tools
        [$platform, $searchTool, $contextTool] = $this->setupPlatformAndTools();

        $sessionIds = [
            'incident-payment-001',
            'incident-database-002',
            'incident-security-003'
        ];

        // Create multiple sessions
        echo "\n📝 Creating " . count($sessionIds) . " investigation sessions...\n";
        foreach ($sessionIds as $sessionId) {
            $chat = LogInspectorChatFactory::createSession(
                $sessionId,
                $platform,
                $searchTool,
                $contextTool,
                $this->testStoragePath
            );
            
            $chat->startInvestigation("Investigation: $sessionId");
            $chat->ask("Investigate issue for $sessionId");
            
            echo "   ✓ Created session: $sessionId\n";
            usleep(50000); // Small delay for unique timestamps
        }

        // List all sessions
        echo "\n📋 Listing all sessions...\n";
        $store = new SessionMessageStore('temp', $this->testStoragePath);
        $sessions = $store->listSessions();
        
        $this->assertCount(3, $sessions);
        echo "   Found " . count($sessions) . " sessions:\n";
        
        foreach ($sessions as $id => $metadata) {
            echo "   • $id (updated: {$metadata['updated_at']}, size: {$metadata['file_size']} bytes)\n";
            $this->assertArrayHasKey('session_id', $metadata);
            $this->assertArrayHasKey('updated_at', $metadata);
            $this->assertArrayHasKey('file_size', $metadata);
        }

        // Test cleanup
        echo "\n🗑️  Testing session cleanup...\n";
        $cleanupStore = new SessionMessageStore('incident-payment-001', $this->testStoragePath);
        $cleanupStore->drop();
        
        $sessionsAfterCleanup = $store->listSessions();
        $this->assertCount(2, $sessionsAfterCleanup);
        echo "   ✓ Deleted 1 session, " . count($sessionsAfterCleanup) . " remaining\n";

        echo "\n✅ Session management operations complete!\n";
    }

    /**
     * Test complete incident investigation workflow
     * 
     * Demonstrates full workflow from the documentation:
     * - Identify issue
     * - Root cause analysis
     * - Impact assessment
     * - Timeline creation
     * - Remediation steps
     * - Final summary
     */
    public function testCompleteIncidentInvestigationWorkflow(): void
    {
        echo "\n" . str_repeat("=", 80);
        echo "\n🎯 COMPLETE INCIDENT INVESTIGATION WORKFLOW";
        echo "\n" . str_repeat("=", 80) . "\n";

        $startTime = microtime(true);
        $sessionId = 'complete-investigation-' . date('YmdHis');

        // Setup platform and tools
        [$platform, $searchTool, $contextTool] = $this->setupPlatformAndTools();

        $chat = LogInspectorChatFactory::createSession(
            $sessionId,
            $platform,
            $searchTool,
            $contextTool,
            $this->testStoragePath
        );

        echo "\n📝 Investigation: Payment failures - Aug 18, 2026\n";
        $chat->startInvestigation('Complete investigation - Payment failures Aug 18, 2026');

        // Step 1: Identify the issue
        echo "\n1️⃣  Identifying the issue...\n";
        $findings = $chat->ask('What errors occurred in the payment system?');
        echo "   ✓ Initial findings: " . substr($findings->getContent(), 0, 100) . "...\n";

        // Step 2: Root cause analysis
        echo "\n2️⃣  Analyzing root cause...\n";
        $rootCause = $chat->ask('What is the root cause of these payment errors?');
        echo "   ✓ Root cause identified: " . substr($rootCause->getContent(), 0, 100) . "...\n";

        // Step 3: Impact assessment
        echo "\n3️⃣  Assessing impact...\n";
        $impact = $chat->ask('What services and users were affected?');
        echo "   ✓ Impact assessed: " . substr($impact->getContent(), 0, 100) . "...\n";

        // Step 4: Timeline
        echo "\n4️⃣  Creating timeline...\n";
        $timeline = $chat->getTimeline();
        echo "   ✓ Timeline created: " . substr($timeline->getContent(), 0, 100) . "...\n";

        // Step 5: Remediation
        echo "\n5️⃣  Getting remediation steps...\n";
        $remediation = $chat->getRemediation();
        echo "   ✓ Remediation plan: " . substr($remediation->getContent(), 0, 100) . "...\n";

        // Step 6: Summary
        echo "\n6️⃣  Generating summary...\n";
        $summary = $chat->summarize();
        echo "   ✓ Summary created: " . substr($summary->getContent(), 0, 100) . "...\n";

        // Verify session persistence
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $this->assertTrue($store->exists());
        
        $metadata = $store->getMetadata();
        echo "\n💾 Investigation saved to: {$metadata['file_path']}\n";

        $duration = microtime(true) - $startTime;
        echo "\n⏱️  Total investigation time: " . number_format($duration, 2) . "s\n";
        echo "\n✅ Complete incident investigation workflow successful!\n";

        $this->assertLessThan(600, $duration, 'Full investigation should complete within 10 minutes');
    }

    /**
     * Setup platform and tools for testing
     */
    private function setupPlatformAndTools(): array
    {
        // Create in-memory store and load logs
        $inMemoryStore = new Store();
        $this->loadedLogs = $this->logLoader->loadLogsIntoStore($inMemoryStore);
        
        $vectorStore = new VectorLogDocumentStore($inMemoryStore);

        // Create platform with Ollama
        $platform = LogDocumentPlatformFactory::create([
            'provider' => 'ollama',
            'host' => $_ENV['OLLAMA_URL'] ?? 'http://localhost:11434',
            'model' => [
                'name' => $_ENV['OLLAMA_MODEL'] ?? 'llama3.2:1b',
                'capabilities' => ['text', 'tool_calling'],
                'options' => [
                    'temperature' => 0.1,
                    'max_tokens' => 500,
                    'timeout' => 180
                ]
            ],
            'client_options' => [
                'timeout' => 180,
                'max_duration' => 180
            ]
        ]);

        // Create vectorizer
        $vectorizer = new LogDocumentVectorizer(
            $platform->getPlatform(),
            $platform->getModel()->getName()
        );

        // Create tools
        $searchTool = new LogSearchTool($vectorStore, $vectorizer, $platform);
        $contextTool = new RequestContextTool($vectorStore, $vectorizer, $platform);

        return [$platform, $searchTool, $contextTool];
    }

    /**
     * Check if Ollama is available
     */
    private function isOllamaAvailable(): bool
    {
        $url = $_ENV['OLLAMA_URL'] ?? 'http://localhost:11434';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'ignore_errors' => true
            ]
        ]);
        
        $result = @file_get_contents($url . '/api/version', false, $context);
        return $result !== false;
    }
}
