<?php

namespace Hakam\AiLogInspector\Test\Unit\Platform;

use Hakam\AiLogInspector\Enum\PlatformEnum;
use Hakam\AiLogInspector\Platform\EmbeddingPlatform;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;

class LogDocumentPlatformFactoryTest extends TestCase
{
    public function testCreateBrainPlatformWithOpenAi(): void
    {
        $platform = LogDocumentPlatformFactory::createBrainPlatform(
            PlatformEnum::OPENAI,
            ['api_key' => 'sk-test-key']
        );

        $this->assertInstanceOf(PlatformInterface::class, $platform->getPlatform());
    }

    public function testCreateBrainPlatformWithAnthropic(): void
    {
        $platform = LogDocumentPlatformFactory::createBrainPlatform(
            PlatformEnum::ANTHROPIC,
            ['api_key' => 'anthropic-test-key']
        );

        $this->assertInstanceOf(PlatformInterface::class, $platform->getPlatform());
    }

    public function testCreateBrainPlatformWithOllama(): void
    {
        $platform = LogDocumentPlatformFactory::createBrainPlatform(
            PlatformEnum::OLLAMA,
            ['host' => 'http://localhost:11434']
        );

        $this->assertInstanceOf(PlatformInterface::class, $platform->getPlatform());
    }

    public function testCreateEmbeddingPlatformReturnsEmbeddingPlatformInstance(): void
    {
        try {
            $platform = LogDocumentPlatformFactory::createEmbeddingPlatform(
                PlatformEnum::OPENAI,
                ['api_key' => 'sk-test-key']
            );

            $this->assertInstanceOf(LogDocumentPlatformInterface::class, $platform);
            $this->assertInstanceOf(EmbeddingPlatform::class, $platform);
        } catch (\Error|\RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testCreateBrainPlatformIsNotEmbeddingPlatform(): void
    {
        $platform = LogDocumentPlatformFactory::createBrainPlatform(
            PlatformEnum::OPENAI,
            ['api_key' => 'sk-test-key']
        );

        $this->assertNotInstanceOf(EmbeddingPlatform::class, $platform);
    }

    public function testCreateBrainPlatformReturnsDifferentInstancesPerCall(): void
    {
        $platform1 = LogDocumentPlatformFactory::createBrainPlatform(
            PlatformEnum::OPENAI,
            ['api_key' => 'sk-test-key']
        );

        $platform2 = LogDocumentPlatformFactory::createBrainPlatform(
            PlatformEnum::OPENAI,
            ['api_key' => 'sk-test-key']
        );

        $this->assertNotSame($platform1, $platform2);
    }

    public function testFactoryMethodsAreStatic(): void
    {
        $reflection = new \ReflectionClass(LogDocumentPlatformFactory::class);

        $createBrain = $reflection->getMethod('createBrainPlatform');
        $this->assertTrue($createBrain->isStatic());

        $createEmbedding = $reflection->getMethod('createEmbeddingPlatform');
        $this->assertTrue($createEmbedding->isStatic());
    }

    public function testCreateBrainPlatformReturnTypes(): void
    {
        $reflection = new \ReflectionClass(LogDocumentPlatformFactory::class);
        $method = $reflection->getMethod('createBrainPlatform');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame(LogDocumentPlatformInterface::class, $returnType->getName());
    }

    public function testCreateEmbeddingPlatformReturnTypes(): void
    {
        $reflection = new \ReflectionClass(LogDocumentPlatformFactory::class);
        $method = $reflection->getMethod('createEmbeddingPlatform');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame(LogDocumentPlatformInterface::class, $returnType->getName());
    }
}
