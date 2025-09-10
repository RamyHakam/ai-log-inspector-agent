<?php

namespace Hakam\AiLogInspector\Platform;

use Hakam\AiLogInspector\Model\LogDocumentModelFactory;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory as OllamaPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory as VertexAiPlatformFactory;

final class LogDocumentPlatformFactory
{
    public static function create(array $config): LogDocumentPlatformInterface
    {
        $platform =  match($config['provider']) {
            'openai' => OpenAiPlatformFactory::create($config['api_key']),
            'anthropic' =>
            AnthropicPlatformFactory::create($config['api_key']),
            'vertex_ai' =>
            VertexAiPlatformFactory::create($config['location'], $config['project_id']),
            'ollama' => OllamaPlatformFactory::create($config['host']),
            // ... other providers will be added in the future
        };
        $model = LogDocumentModelFactory::create($config['model']);
        return new LogDocumentPlatform($platform, $model);
    }
}