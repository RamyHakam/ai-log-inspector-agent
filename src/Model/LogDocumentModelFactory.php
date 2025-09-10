<?php

namespace Hakam\AiLogInspector\Model;

use Symfony\AI\Platform\Bridge\Ollama\Ollama;

class LogDocumentModelFactory
{
    public static function create(array $modelConfig, string $provider = null): LogDocumentModel
    {
        // For Ollama, create an Ollama-specific model
        if ($provider === 'ollama') {
            $ollamaModel = new Ollama(
                $modelConfig['name'],
                $modelConfig['options'] ?? []
            );
            return LogDocumentModel::fromModel($ollamaModel);
        }
        
        // For other providers, use generic model
        return new LogDocumentModel(
            $modelConfig['name'],
            $modelConfig['capabilities'] ?? [],
            $modelConfig['options'] ?? []
        );
    }
}
