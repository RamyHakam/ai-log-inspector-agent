<?php

namespace Hakam\AiLogInspector\Platform;

use Hakam\AiLogInspector\Enum\PlatformEnum;

final class LogDocumentPlatformFactory
{
    /**
     * Create a platform from a configuration array.
     *
     * Config format:
     * [
     *     'provider' => 'openai' | 'anthropic' | 'ollama',
     *     'api_key' => '...',  // For openai/anthropic
     *     'host' => '...',     // For ollama
     *     'model' => [
     *         'name' => 'gpt-4o-mini',
     *         'capabilities' => ['text', 'tool_calling'],
     *         'options' => ['temperature' => 0.7, 'max_tokens' => 500],
     *     ],
     * ]
     */
    public static function create(array $config): LogDocumentPlatformInterface
    {
        $provider = $config['provider'] ?? 'openai';
        $platformType = self::getPlatformEnum($provider);

        $platformConfig = self::buildPlatformConfig($config, $platformType);

        return new LogDocumentPlatform($platformType, $platformConfig);
    }

    public static function createBrainPlatform(PlatformEnum $platformType, array $config): LogDocumentPlatformInterface
    {
        return new LogDocumentPlatform($platformType, $config);
    }

    public static function createEmbeddingPlatform(PlatformEnum $platform, array $config): LogDocumentPlatformInterface
    {
        return new EmbeddingPlatform($platform, $config);
    }

    private static function getPlatformEnum(string $provider): PlatformEnum
    {
        return match (strtolower($provider)) {
            'openai' => PlatformEnum::OPENAI,
            'anthropic' => PlatformEnum::ANTHROPIC,
            'ollama' => PlatformEnum::OLLAMA,
            default => throw new \InvalidArgumentException("Unknown provider: $provider"),
        };
    }

    private static function buildPlatformConfig(array $config, PlatformEnum $platformType): array
    {
        $platformConfig = [];

        // Extract API key or host based on platform type
        if (PlatformEnum::OLLAMA === $platformType) {
            $platformConfig['host'] = $config['host'] ?? 'http://localhost:11434';
        } else {
            $platformConfig['api_key'] = $config['api_key'] ?? '';
        }

        // Extract model configuration
        if (isset($config['model'])) {
            if (is_array($config['model'])) {
                $platformConfig['model'] = $config['model']['name'] ?? null;
                $platformConfig['model_options'] = $config['model']['options'] ?? [];
                $platformConfig['model_capabilities'] = $config['model']['capabilities'] ?? [];
            } else {
                $platformConfig['model'] = $config['model'];
            }
        }

        // Pass through client options
        if (isset($config['client_options'])) {
            $platformConfig['client_options'] = $config['client_options'];
        }

        return $platformConfig;
    }
}
