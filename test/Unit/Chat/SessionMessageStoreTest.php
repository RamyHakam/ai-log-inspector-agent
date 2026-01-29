<?php

namespace Hakam\AiLogInspector\Test\Unit\Chat;

use Hakam\AiLogInspector\Chat\SessionMessageStore;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

class SessionMessageStoreTest extends TestCase
{
    private string $testStoragePath;
    private SessionMessageStore $store;

    protected function setUp(): void
    {
        $this->testStoragePath = sys_get_temp_dir() . '/log-inspector-test-' . uniqid();
        mkdir($this->testStoragePath, 0755, true);
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

    public function testConstructorWithSessionId(): void
    {
        $sessionId = 'test-session-123';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);

        $this->assertInstanceOf(SessionMessageStore::class, $store);
        $this->assertEquals($sessionId, $store->getSessionId());
    }

    public function testConstructorWithDefaultSessionId(): void
    {
        $store = new SessionMessageStore(basePath: $this->testStoragePath);

        $this->assertInstanceOf(SessionMessageStore::class, $store);
        $this->assertNotEmpty($store->getSessionId());
    }

    public function testSetupCreatesStorageDirectory(): void
    {
        $customPath = $this->testStoragePath . '/custom-sessions';
        $store = new SessionMessageStore('test-session', $customPath);

        $this->assertDirectoryDoesNotExist($customPath);

        $store->setup();

        $this->assertDirectoryExists($customPath);

        // Cleanup
        rmdir($customPath);
    }

    public function testSaveAndLoadMessages(): void
    {
        $sessionId = 'test-save-load';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        $messages = new MessageBag(
            Message::ofUser('What errors occurred?'),
            Message::ofAssistant('Found 3 payment errors')
        );

        $store->save($messages);

        $loadedMessages = $store->load();

        $this->assertInstanceOf(MessageBag::class, $loadedMessages);
        $this->assertCount(2, $loadedMessages->getMessages());
    }

    public function testLoadReturnsEmptyBagWhenNoFileExists(): void
    {
        $sessionId = 'non-existent-session';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        $messages = $store->load();

        $this->assertInstanceOf(MessageBag::class, $messages);
        $this->assertCount(0, $messages->getMessages());
    }

    public function testExistsReturnsFalseWhenSessionDoesNotExist(): void
    {
        $sessionId = 'non-existent-session';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        $this->assertFalse($store->exists());
    }

    public function testExistsReturnsTrueAfterSave(): void
    {
        $sessionId = 'existing-session';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        $messages = new MessageBag(Message::ofUser('Test message'));
        $store->save($messages);

        $this->assertTrue($store->exists());
    }

    public function testDropDeletesSessionFile(): void
    {
        $sessionId = 'delete-test-session';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        $messages = new MessageBag(Message::ofUser('Test message'));
        $store->save($messages);

        $this->assertTrue($store->exists());

        $store->drop();

        $this->assertFalse($store->exists());
    }

    public function testDropDoesNotThrowWhenFileDoesNotExist(): void
    {
        $sessionId = 'non-existent-to-drop';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        // Should not throw an exception
        $store->drop();

        $this->assertFalse($store->exists());
    }

    public function testGetMetadataReturnsNullWhenSessionDoesNotExist(): void
    {
        $sessionId = 'no-metadata-session';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        $metadata = $store->getMetadata();

        $this->assertNull($metadata);
    }

    public function testGetMetadataReturnsCorrectData(): void
    {
        $sessionId = 'metadata-test-session';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        $messages = new MessageBag(Message::ofUser('Test message'));
        $store->save($messages);

        $metadata = $store->getMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('session_id', $metadata);
        $this->assertArrayHasKey('updated_at', $metadata);
        $this->assertArrayHasKey('file_path', $metadata);
        $this->assertArrayHasKey('file_size', $metadata);
        $this->assertEquals($sessionId, $metadata['session_id']);
        $this->assertGreaterThan(0, $metadata['file_size']);
    }

    public function testListSessionsReturnsEmptyArrayWhenNoSessions(): void
    {
        $store = new SessionMessageStore('test', $this->testStoragePath);
        $store->setup();

        $sessions = $store->listSessions();

        $this->assertIsArray($sessions);
        $this->assertEmpty($sessions);
    }

    public function testListSessionsReturnsAllSessions(): void
    {
        $sessionIds = ['session-1', 'session-2', 'session-3'];
        
        foreach ($sessionIds as $sessionId) {
            $store = new SessionMessageStore($sessionId, $this->testStoragePath);
            $store->setup();
            $store->save(new MessageBag(Message::ofUser("Message for $sessionId")));
            
            // Sleep briefly to ensure different timestamps
            usleep(10000);
        }

        $store = new SessionMessageStore('test', $this->testStoragePath);
        $sessions = $store->listSessions();

        $this->assertIsArray($sessions);
        $this->assertCount(3, $sessions);
        
        // Check that each session has the required metadata
        foreach ($sessions as $sessionData) {
            $this->assertArrayHasKey('session_id', $sessionData);
            $this->assertArrayHasKey('updated_at', $sessionData);
            $this->assertArrayHasKey('file_path', $sessionData);
            $this->assertArrayHasKey('file_size', $sessionData);
        }
    }

    public function testListSessionsSortedByUpdatedAt(): void
    {
        // Create sessions with slight delays to ensure different timestamps
        $store1 = new SessionMessageStore('session-old', $this->testStoragePath);
        $store1->setup();
        $store1->save(new MessageBag(Message::ofUser('Old message')));
        
        usleep(100000); // 100ms delay
        
        $store2 = new SessionMessageStore('session-new', $this->testStoragePath);
        $store2->setup();
        $store2->save(new MessageBag(Message::ofUser('New message')));

        $store = new SessionMessageStore('test', $this->testStoragePath);
        $sessions = $store->listSessions();

        $sessionIds = array_keys($sessions);
        
        // Most recent should be first
        $this->assertEquals('session-new', $sessionIds[0]);
        $this->assertEquals('session-old', $sessionIds[1]);
    }

    public function testSessionIdSanitization(): void
    {
        // Test with potentially unsafe characters
        $unsafeId = 'session/with:unsafe*chars?';
        $store = new SessionMessageStore($unsafeId, $this->testStoragePath);
        $store->setup();

        $messages = new MessageBag(Message::ofUser('Test'));
        $store->save($messages);

        // Should not throw exceptions and should create a valid file
        $this->assertTrue($store->exists());
        
        $loadedMessages = $store->load();
        $this->assertCount(1, $loadedMessages->getMessages());
    }

    public function testMultipleSavesOverwritePrevious(): void
    {
        $sessionId = 'overwrite-test';
        $store = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store->setup();

        // Save first message
        $messages1 = new MessageBag(Message::ofUser('First message'));
        $store->save($messages1);

        // Save second message (should overwrite)
        $messages2 = new MessageBag(
            Message::ofUser('First message'),
            Message::ofAssistant('Response'),
            Message::ofUser('Second message')
        );
        $store->save($messages2);

        // Load should return the latest saved messages
        $loaded = $store->load();
        $this->assertCount(3, $loaded->getMessages());
    }

    public function testSessionPersistenceAcrossInstances(): void
    {
        $sessionId = 'persistence-test';
        
        // Create first instance and save
        $store1 = new SessionMessageStore($sessionId, $this->testStoragePath);
        $store1->setup();
        $messages = new MessageBag(
            Message::ofUser('Test question'),
            Message::ofAssistant('Test answer')
        );
        $store1->save($messages);

        // Create second instance with same session ID
        $store2 = new SessionMessageStore($sessionId, $this->testStoragePath);
        $loadedMessages = $store2->load();

        // Should load the same messages
        $this->assertCount(2, $loadedMessages->getMessages());
    }
}
