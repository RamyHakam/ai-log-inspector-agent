<?php

namespace Hakam\AiLogInspector\Test\Unit\Chat;

use Hakam\AiLogInspector\Chat\LogInspectorChat;
use Hakam\AiLogInspector\Chat\LogInspectorChatFactory;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;

class LogInspectorChatFactoryTest extends TestCase
{
    private LogDocumentPlatformInterface $platform;
    private LogSearchTool $searchTool;
    private RequestContextTool $contextTool;
    private string $testStoragePath;

    protected function setUp(): void
    {

        $this->platform = $this->createMock(LogDocumentPlatformInterface::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockModel = $this->createMock(Model::class);
        
        $mockModel->method('supports')->willReturn(true);
        $mockModel->method('getName')->willReturn('test-model');
        
        $this->platform->method('getPlatform')->willReturn($mockPlatform);
        $this->platform->method('getModel')->willReturn($mockModel);
        
        $this->searchTool = $this->createMock(LogSearchTool::class);
        $this->contextTool = $this->createMock(RequestContextTool::class);
        
        $this->testStoragePath = sys_get_temp_dir() . '/factory-test-' . uniqid();
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

    public function testCreateWithOnlySearchTool(): void
    {
        $chat = LogInspectorChatFactory::create(
            $this->platform,
            $this->searchTool
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat);
    }

    public function testCreateWithBothTools(): void
    {
        $chat = LogInspectorChatFactory::create(
            $this->platform,
            $this->searchTool,
            $this->contextTool
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat);
    }

    public function testCreateWithoutContextTool(): void
    {
        $chat = LogInspectorChatFactory::create(
            $this->platform,
            $this->searchTool,
            null
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat);
    }

    public function testCreateSessionWithSessionId(): void
    {
        $sessionId = 'incident-2026-08-18';
        
        $chat = LogInspectorChatFactory::createSession(
            $sessionId,
            $this->platform,
            $this->searchTool,
            storagePath: $this->testStoragePath
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat);
    }

    public function testCreateSessionWithBothTools(): void
    {
        $sessionId = 'test-session-with-tools';
        
        $chat = LogInspectorChatFactory::createSession(
            $sessionId,
            $this->platform,
            $this->searchTool,
            $this->contextTool,
            $this->testStoragePath
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat);
    }

    public function testCreateSessionWithCustomStoragePath(): void
    {
        $sessionId = 'custom-path-session';
        $customPath = $this->testStoragePath . '/custom-storage';
        
        $chat = LogInspectorChatFactory::createSession(
            $sessionId,
            $this->platform,
            $this->searchTool,
            storagePath: $customPath
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat);
        
        $this->assertDirectoryExists($customPath);
        
        // Cleanup
        rmdir($customPath);
    }

    public function testCreateSessionMultipleTimes(): void
    {
        $sessionId = 'reusable-session';

        $chat1 = LogInspectorChatFactory::createSession(
            $sessionId,
            $this->platform,
            $this->searchTool,
            storagePath: $this->testStoragePath
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat1);

        $chat2 = LogInspectorChatFactory::createSession(
            $sessionId,
            $this->platform,
            $this->searchTool,
            storagePath: $this->testStoragePath
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat2);
        
        $this->assertNotSame($chat1, $chat2);
    }

    public function testCreateSessionWithDifferentSessionIds(): void
    {
        $sessionId1 = 'session-one';
        $sessionId2 = 'session-two';
        
        $chat1 = LogInspectorChatFactory::createSession(
            $sessionId1,
            $this->platform,
            $this->searchTool,
            storagePath: $this->testStoragePath
        );

        $chat2 = LogInspectorChatFactory::createSession(
            $sessionId2,
            $this->platform,
            $this->searchTool,
            storagePath: $this->testStoragePath
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat1);
        $this->assertInstanceOf(LogInspectorChat::class, $chat2);
        $this->assertNotSame($chat1, $chat2);
    }

    public function testCreateReturnsNewInstanceEachTime(): void
    {
        $chat1 = LogInspectorChatFactory::create(
            $this->platform,
            $this->searchTool
        );

        $chat2 = LogInspectorChatFactory::create(
            $this->platform,
            $this->searchTool
        );

        $this->assertNotSame($chat1, $chat2);
    }

    public function testCreateUsesInMemoryStore(): void
    {
        $chat = LogInspectorChatFactory::create(
            $this->platform,
            $this->searchTool
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat);

        $this->assertFalse($chat->isActive());
    }

    public function testCreateSessionDefaultStoragePath(): void
    {
        $sessionId = 'default-path-test';
        
        $chat = LogInspectorChatFactory::createSession(
            $sessionId,
            $this->platform,
            $this->searchTool
        );

        $this->assertInstanceOf(LogInspectorChat::class, $chat);
        
        $defaultPath = '/tmp/log-inspector-sessions';
        if (is_dir($defaultPath)) {
            $files = glob($defaultPath . '/*' . $sessionId . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
