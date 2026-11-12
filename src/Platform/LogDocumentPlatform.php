<?php

namespace Hakam\AiLogInspector\Platform;

use Hakam\AiLogInspector\Enum\PlatformEnum;
use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Ollama\Factory as OllamaPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;

readonly class LogDocumentPlatform implements LogDocumentPlatformInterface
{
    protected PlatformInterface $platform;

    public function __construct(
        private PlatformEnum $platformType,
        private array $config,
    ) {
        $this->platform = $this->initPlatform();
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    public function getModel(): Model
    {
        $modelName = $this->getDefaultModel();
        $catalog = $this->platform->getModelCatalog();

        return $catalog->getModel($modelName);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }

    public function invoke(string|\Symfony\AI\Platform\Model $model, object|array|string $input, array $options = []): DeferredResult
    {
        return $this->platform->invoke($model, $input, $options);
    }

    public function __invoke(string|array $input): ResultInterface
    {
        $model = $this->getDefaultModel();

        // Convert string input to MessageBag for platform compatibility
        if (is_string($input)) {
            $input = new MessageBag(Message::ofUser($input));
        }

        return $this->platform->invoke($model, $input)->getResult();
    }

    protected function initPlatform(): PlatformInterface
    {
        return match ($this->platformType) {
            PlatformEnum::OPENAI => OpenAiPlatformFactory::createPlatform($this->config['api_key']),
            PlatformEnum::ANTHROPIC => AnthropicPlatformFactory::createPlatform($this->config['api_key']),
            PlatformEnum::OLLAMA => OllamaPlatformFactory::createPlatform($this->config['host']),
        };
    }

    protected function getDefaultModel(): string
    {
        return $this->config['model'] ?? match ($this->platformType) {
            PlatformEnum::OPENAI => 'gpt-4o-mini',
            PlatformEnum::ANTHROPIC => 'claude-sonnet-4-20250514',
            PlatformEnum::OLLAMA => 'llama3.1',
        };
    }
}
