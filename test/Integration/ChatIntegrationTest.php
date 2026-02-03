<?php

namespace Hakam\AiLogInspector\Test\Integration;

use Hakam\AiLogInspector\Chat\LogInspectorChatFactory;
use Hakam\AiLogInspector\Chat\SessionMessageStore;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizer;
use Hakam\AiLogInspector\Test\Support\LogFileLoader;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\InMemory\Store;

/**
 * Integration tests for Chat functionality
 *
 * These tests demonstrate API usage patterns without requiring actual AI execution.
 * For real multi-turn conversation tests with Ollama, see:
 * test/Functional/RealChatConversationTest.php
 */
#[Group('integration')]
class ChatIntegrationTest extends TestCase
{
    private string $testStoragePath;

    protected function setUp(): void
    {
        $this->testStoragePath = sys_get_temp_dir() . '/chat-integration-test-' . uniqid();
        mkdir($this->testStoragePath, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up test storage
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
     * Test chat factory creates instances correctly
     * 
     * Demonstrates basic factory usage
     */
    public function testChatFactoryCreatesInstances(): void
    {
        // Setup platform and tools (won't execute, just verifies creation)
        [$platform, $searchTool, $contextTool] = $this->createTestComponents();

        // Create chat instance with factory
        $chat = LogInspectorChatFactory::create($platform, $searchTool, $contextTool);

        $this->assertNotNull($chat);
        $this->assertFalse($chat->isActive(), 'Chat should not be active initially');
    }

    /**
     * Test session persistence and resumption
     * 
     * Demonstrates:
     * - Creating a named session
     * - Saving messages to disk
     * - Loading session in a new instance
     */
    public function testSessionPersistenceAndResumption(): void
    {
        $sessionId = 'incident-2024-08-18-payments';

        // Setup platform and tools
        [$platform, $searchTool, $contextTool] = $this->createTestComponents();

        // Create first session instance
        $chat1 = LogInspectorChatFactory::createSession(
            $sessionId,
            $platform,
            $searchTool,
            $contextTool,
            $this->testStoragePath
        );

        $chat1->startInvestigation('Payment incident investigation');
        
        // Manually save some messages to simulate conversation
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();
        
        $messages = new MessageBag(
            Message::ofUser('What happened?'),
            Message::ofAssistant('Database connection pool was exhausted.')
        );
        $store->save($messages);

        // Verify session exists
        $this->assertTrue($store->exists());

        // Create second session instance (simulating restart)
        $chat2 = LogInspectorChatFactory::createSession(
            $sessionId,
            $platform,
            $searchTool,
            $contextTool,
            $this->testStoragePath
        );

        // Load and verify messages
        $loadedMessages = $store->load();
        $this->assertCount(2, $loadedMessages->getMessages());
    }

    /**
     * Test chat initialization and state management
     * 
     * Demonstrates:
     * - Manual initialization
     * - Active state tracking
     * - Message bag initiation
     */
    public function testChatInitializationAndStateManagement(): void
    {
        [$platform, $searchTool, $contextTool] = $this->createTestComponents();

        $chat = LogInspectorChatFactory::create($platform, $searchTool);

        // Initially not active
        $this->assertFalse($chat->isActive());

        // Start investigation
        $chat->startInvestigation('Database investigation');
        $this->assertTrue($chat->isActive());

        // Test initiate with message bag
        $messages = new MessageBag(
            Message::ofUser('Test question'),
            Message::ofAssistant('Test answer')
        );
        
        $chat->initiate($messages);
        $this->assertTrue($chat->isActive());
    }

    /**
     * Test factory creates sessions with custom storage path
     * 
     * Demonstrates custom storage configuration
     */
    public function testFactoryCreatesSessionsWithCustomStorage(): void
    {
        [$platform, $searchTool, $contextTool] = $this->createTestComponents();

        $customPath = $this->testStoragePath . '/custom-sessions';
        $sessionId = 'test-custom-storage';

        $chat = LogInspectorChatFactory::createSession(
            $sessionId,
            $platform,
            $searchTool,
            $contextTool,
            $customPath
        );

        $this->assertNotNull($chat);
        $this->assertDirectoryExists($customPath);
        
        // Cleanup
        rmdir($customPath);
    }

    /**
     * Test session management and cleanup
     * 
     * Demonstrates:
     * - Creating multiple sessions
     * - Listing sessions
     * - Session cleanup
     */
    public function testSessionManagementAndCleanup(): void
    {
        $sessionIds = [
            'incident-payment-001',
            'incident-database-002',
            'incident-api-003'
        ];

        // Create multiple sessions
        foreach ($sessionIds as $sessionId) {
            $store = new SessionMessageStore($sessionId, $this->testStoragePath);
            $store->setup();
            $store->save(new MessageBag(
                Message::ofUser("Investigation for $sessionId")
            ));
            usleep(10000); // Ensure different timestamps
        }

        // List all sessions
        $store = new SessionMessageStore('temp', $this->testStoragePath);
        $sessions = $store->listSessions();
        
        $this->assertCount(3, $sessions);
        $this->assertArrayHasKey('incident-payment-001', $sessions);
        $this->assertArrayHasKey('incident-database-002', $sessions);
        $this->assertArrayHasKey('incident-api-003', $sessions);

        // Verify session metadata
        foreach ($sessions as $sessionData) {
            $this->assertArrayHasKey('session_id', $sessionData);
            $this->assertArrayHasKey('updated_at', $sessionData);
            $this->assertArrayHasKey('file_size', $sessionData);
        }

        // Clean up individual session
        $cleanupStore = new SessionMessageStore('incident-payment-001', $this->testStoragePath);
        $cleanupStore->drop();
        
        $sessionsAfterCleanup = $store->listSessions();
        $this->assertCount(2, $sessionsAfterCleanup);
    }

    /**
     * Test message store save and load operations
     * 
     * Demonstrates message persistence
     */
    public function testMessageStoreSaveAndLoad(): void
    {
        $sessionId = 'test-message-store';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        // Save messages
        $messages = new MessageBag(
            Message::ofUser('What errors occurred?'),
            Message::ofAssistant('Found 3 errors'),
            Message::ofUser('What caused them?'),
            Message::ofAssistant('Database connection timeout')
        );
        
        $store->save($messages);
        $this->assertTrue($store->exists());

        // Load messages
        $loaded = $store->load();
        $this->assertCount(4, $loaded->getMessages());

        // Verify metadata
        $metadata = $store->getMetadata();
        $this->assertArrayHasKey('session_id', $metadata);
        $this->assertEquals($sessionId, $metadata['session_id']);
        $this->assertGreaterThan(0, $metadata['file_size']);
    }

    /**
     * Test session exists and drop operations
     * 
     * Demonstrates session lifecycle
     */
    public function testSessionExistsAndDrop(): void
    {
        $sessionId = 'test-lifecycle';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        // Initially doesn't exist
        $this->assertFalse($store->exists());

        // Save messages
        $messages = new MessageBag(Message::ofUser('Test'));
        $store->save($messages);

        // Now exists
        $this->assertTrue($store->exists());

        // Drop session
        $store->drop();

        // No longer exists
        $this->assertFalse($store->exists());
    }

    /**
     * Test factory creates both in-memory and persistent chats
     * 
     * Demonstrates different factory methods
     */
    public function testFactoryCreatesBothChatTypes(): void
    {
        [$platform, $searchTool, $contextTool] = $this->createTestComponents();

        // Create in-memory chat (no session persistence)
        $memoryChat = LogInspectorChatFactory::create($platform, $searchTool);
        $this->assertNotNull($memoryChat);

        // Create persistent session chat
        $sessionChat = LogInspectorChatFactory::createSession(
            'test-session',
            $platform,
            $searchTool,
            $contextTool,
            $this->testStoragePath
        );
        $this->assertNotNull($sessionChat);

        // They are different instances
        $this->assertNotSame($memoryChat, $sessionChat);
    }

    /**
     * Helper method to create test components
     * Note: These won't execute, just used for API demonstration
     */
    private function createTestComponents(): array
    {
        // Create minimal setup for testing factory and session APIs
        $inMemoryStore = new Store();
        $vectorStore = new VectorLogDocumentStore($inMemoryStore);

        // Create platform (won't actually call Ollama in these tests)
        $platform = LogDocumentPlatformFactory::create([
            'provider' => 'ollama',
            'host' => 'http://localhost:11434',
            'model' => [
                'name' => 'llama3.2:1b',
                'capabilities' => ['text', 'tool_calling'],
            ]
        ]);

        $vectorizer = new LogDocumentVectorizer(
            $platform->getPlatform(),
            $platform->getModel()->getName()
        );

        $searchTool = new LogSearchTool($vectorStore, $vectorizer, $platform);
        $contextTool = new RequestContextTool($vectorStore, $vectorizer, $platform);

        return [$platform, $searchTool, $contextTool];
    }
}
