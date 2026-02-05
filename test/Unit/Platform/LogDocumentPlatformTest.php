<?php

namespace Hakam\AiLogInspector\Test\Unit\Platform;

use Hakam\AiLogInspector\Enum\PlatformEnum;
use Hakam\AiLogInspector\Platform\LogDocumentPlatform;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

class LogDocumentPlatformTest extends TestCase
{
    public function testGetPlatformReturnsUnderlyingPlatform(): void
    {
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $platform = $this->createPlatformWithInjectedPlatform($mockPlatform);

        $this->assertSame($mockPlatform, $platform->getPlatform());
    }

    public function testGetModelCatalogDelegatesToPlatform(): void
    {
        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->method('getModelCatalog')->willReturn($mockCatalog);

        $platform = $this->createPlatformWithInjectedPlatform($mockPlatform);

        $this->assertSame($mockCatalog, $platform->getModelCatalog());
    }

    public function testGetModelReturnsModelFromCatalog(): void
    {
        $mockModel = $this->createMock(Model::class);
        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockCatalog->method('getModel')->with('gpt-4o-mini')->willReturn($mockModel);

        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->method('getModelCatalog')->willReturn($mockCatalog);

        $platform = $this->createPlatformWithInjectedPlatform($mockPlatform);

        $this->assertSame($mockModel, $platform->getModel());
    }

    public function testInvokeDelegatesToPlatform(): void
    {
        $mockDeferred = $this->createMock(DeferredResult::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o-mini', 'test input', ['temperature' => 0.5])
            ->willReturn($mockDeferred);

        $platform = $this->createPlatformWithInjectedPlatform($mockPlatform);

        $result = $platform->invoke('gpt-4o-mini', 'test input', ['temperature' => 0.5]);

        $this->assertSame($mockDeferred, $result);
    }

    public function testInvokeWithObjectInput(): void
    {
        $input = new \stdClass();
        $mockDeferred = $this->createMock(DeferredResult::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o-mini', $input, [])
            ->willReturn($mockDeferred);

        $platform = $this->createPlatformWithInjectedPlatform($mockPlatform);

        $result = $platform->invoke('gpt-4o-mini', $input);

        $this->assertSame($mockDeferred, $result);
    }

    public function testInvokeWithArrayInput(): void
    {
        $input = ['message' => 'hello'];
        $mockDeferred = $this->createMock(DeferredResult::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o-mini', $input, [])
            ->willReturn($mockDeferred);

        $platform = $this->createPlatformWithInjectedPlatform($mockPlatform);

        $result = $platform->invoke('gpt-4o-mini', $input);

        $this->assertSame($mockDeferred, $result);
    }

    public function testInitPlatformCreatesOpenAiPlatform(): void
    {
        // This tests the actual initPlatform match branch for OpenAI.
        // It will call OpenAiPlatformFactory::create() which returns a real platform.
        $platform = new LogDocumentPlatform(PlatformEnum::OPENAI, ['api_key' => 'sk-test-key']);

        $this->assertInstanceOf(PlatformInterface::class, $platform->getPlatform());
    }

    public function testInitPlatformCreatesAnthropicPlatform(): void
    {
        $platform = new LogDocumentPlatform(PlatformEnum::ANTHROPIC, ['api_key' => 'anthropic-test-key']);

        $this->assertInstanceOf(PlatformInterface::class, $platform->getPlatform());
    }

    public function testInitPlatformCreatesOllamaPlatform(): void
    {
        $platform = new LogDocumentPlatform(PlatformEnum::OLLAMA, ['host' => 'http://localhost:11434']);

        $this->assertInstanceOf(PlatformInterface::class, $platform->getPlatform());
    }

    /**
     * Creates a LogDocumentPlatform with initPlatform() overridden to inject a mock.
     */
    private function createPlatformWithInjectedPlatform(PlatformInterface $injectedPlatform): LogDocumentPlatform
    {
        return new class(PlatformEnum::OPENAI, ['api_key' => 'test'], $injectedPlatform) extends LogDocumentPlatform {
            public function __construct(
                PlatformEnum $platformType,
                array $config,
                private readonly PlatformInterface $injected,
            ) {
                parent::__construct($platformType, $config);
            }

            protected function initPlatform(): PlatformInterface
            {
                return $this->injected;
            }
        };
    }

    /**
     * Creates a LogDocumentPlatform with initPlatform() overridden to return a mock.
     */
    private function createPlatformWithMockedInit(PlatformEnum $type, array $config): LogDocumentPlatform
    {
        $mock = $this->createMock(PlatformInterface::class);

        return new class($type, $config, $mock) extends LogDocumentPlatform {
            public function __construct(
                PlatformEnum $platformType,
                array $config,
                private readonly PlatformInterface $injected,
            ) {
                parent::__construct($platformType, $config);
            }

            protected function initPlatform(): PlatformInterface
            {
                return $this->injected;
            }
        };
    }
}
