<?php

namespace Hakam\AiLogInspector\Platform;

use Hakam\AiLogInspector\Model\LogDocumentModelFactory;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory as OllamaPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory as VertexAiPlatformFactory;
use Symfony\Component\HttpClient\HttpClient;

final class LogDocumentPlatformFactory
{
    public static function create(array $config): LogDocumentPlatformInterface
    {
        $provider = $config['provider'];
        $clientOptions = $config['client_options'] ?? [];
        
        $httpClient = null;
        if (!empty($clientOptions)) {
            $httpClient = HttpClient::create($clientOptions);
        }
        
        $platform =  match($provider) {
            'openai' => OpenAiPlatformFactory::create($config['api_key'], $httpClient),
            'anthropic' =>
            AnthropicPlatformFactory::create($config['api_key'], $httpClient),
            'vertex_ai' =>
            VertexAiPlatformFactory::create($config['location'], $config['project_id'], $httpClient),
            'ollama' => OllamaPlatformFactory::create($config['host'], $httpClient),
            // ... other providers will be added in the future
        };
        $model = LogDocumentModelFactory::create($config['model'], $provider);
        return new LogDocumentPlatform($platform, $model);
    }
}