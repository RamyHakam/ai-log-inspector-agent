<?php

namespace Hakam\AiLogInspector\Test\Unit\Platform;

use Hakam\AiLogInspector\Enum\PlatformEnum;
use Hakam\AiLogInspector\Platform\EmbeddingPlatform;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;

class EmbeddingPlatformTest extends TestCase
{
    public function testSupportsEmbeddingReturnsTrueWhenCatalogHasEmbeddingModel(): void
    {
        $platform = $this->createEmbeddingPlatformWithEmbeddingSupport();

        $this->assertTrue($platform->supportsEmbedding());
    }

    public function testConstructorThrowsWhenPlatformDoesNotSupportEmbedding(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not support embedding/');

        $this->createEmbeddingPlatformWithoutEmbeddingSupport();
    }

    public function testGetPlatformReturnsUnderlyingPlatform(): void
    {
        $mockPlatform = $this->createMockPlatformWithEmbedding();
        $platform = $this->createEmbeddingPlatformWithInjected($mockPlatform);

        $this->assertSame($mockPlatform, $platform->getPlatform());
    }

    public function testSupportsEmbeddingChecksAllModelsInCatalog(): void
    {
        // Model catalog with mixed capabilities: one chat model, one embedding model
        $chatModel = new Model('gpt-4o-mini', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);
        $embeddingModel = new Model('text-embedding-3-small', [Capability::EMBEDDINGS]);

        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockCatalog->method('getModels')->willReturn([
            'gpt-4o-mini' => $chatModel,
            'text-embedding-3-small' => $embeddingModel,
        ]);

        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->method('getModelCatalog')->willReturn($mockCatalog);

        $platform = $this->createEmbeddingPlatformWithInjected($mockPlatform);

        $this->assertTrue($platform->supportsEmbedding());
    }

    public function testSupportsEmbeddingReturnsFalseWhenOnlyChatModels(): void
    {
        $chatModel = new Model('gpt-4o-mini', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);

        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockCatalog->method('getModels')->willReturn([
            'gpt-4o-mini' => $chatModel,
        ]);

        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->method('getModelCatalog')->willReturn($mockCatalog);

        $this->expectException(\RuntimeException::class);
        $this->createEmbeddingPlatformWithInjected($mockPlatform);
    }

    public function testSupportsEmbeddingWithEmptyCatalog(): void
    {
        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockCatalog->method('getModels')->willReturn([]);

        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->method('getModelCatalog')->willReturn($mockCatalog);

        $this->expectException(\RuntimeException::class);
        $this->createEmbeddingPlatformWithInjected($mockPlatform);
    }

    public function testSupportsEmbeddingWithMultipleEmbeddingModels(): void
    {
        $embedding1 = new Model('text-embedding-3-small', [Capability::EMBEDDINGS]);
        $embedding2 = new Model('text-embedding-3-large', [Capability::EMBEDDINGS]);

        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockCatalog->method('getModels')->willReturn([
            'text-embedding-3-small' => $embedding1,
            'text-embedding-3-large' => $embedding2,
        ]);

        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->method('getModelCatalog')->willReturn($mockCatalog);

        $platform = $this->createEmbeddingPlatformWithInjected($mockPlatform);

        $this->assertTrue($platform->supportsEmbedding());
    }

    private function createMockPlatformWithEmbedding(): PlatformInterface
    {
        $embeddingModel = new Model('text-embedding-3-small', [Capability::EMBEDDINGS]);

        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockCatalog->method('getModels')->willReturn([
            'text-embedding-3-small' => $embeddingModel,
        ]);

        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->method('getModelCatalog')->willReturn($mockCatalog);

        return $mockPlatform;
    }

    private function createEmbeddingPlatformWithEmbeddingSupport(): EmbeddingPlatform
    {
        $mockPlatform = $this->createMockPlatformWithEmbedding();

        return $this->createEmbeddingPlatformWithInjected($mockPlatform);
    }

    private function createEmbeddingPlatformWithoutEmbeddingSupport(): EmbeddingPlatform
    {
        $chatModel = new Model('gpt-4o-mini', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);

        $mockCatalog = $this->createMock(ModelCatalogInterface::class);
        $mockCatalog->method('getModels')->willReturn([
            'gpt-4o-mini' => $chatModel,
        ]);

        $mockPlatform = $this->createMock(PlatformInterface::class);
        $mockPlatform->method('getModelCatalog')->willReturn($mockCatalog);

        return $this->createEmbeddingPlatformWithInjected($mockPlatform);
    }

    private function createEmbeddingPlatformWithInjected(PlatformInterface $injectedPlatform): EmbeddingPlatform
    {
        return new readonly class(PlatformEnum::OPENAI, ['api_key' => 'test'], $injectedPlatform) extends EmbeddingPlatform {
            public function __construct(
                PlatformEnum $platformType,
                array $config,
                private PlatformInterface $injected,
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
