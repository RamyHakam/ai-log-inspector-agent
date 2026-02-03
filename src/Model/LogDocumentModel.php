<?php

namespace Hakam\AiLogInspector\Model;

use Symfony\AI\Platform\Model;

class LogDocumentModel implements LogDocumentModelInterface
{
    private Model $model;
    private array $capabilities;

    /**
     * Known embedding model patterns for different providers.
     */
    private const EMBEDDING_MODEL_PATTERNS = [
        // OpenAI
        'text-embedding-ada',
        'text-embedding-3',
        // Google/Vertex
        'textembedding-gecko',
        'text-embedding-004',
        'embedding-001',
        // Ollama
        'nomic-embed',
        'mxbai-embed',
        'all-minilm',
        'snowflake-arctic-embed',
        // Generic
        'embed',
    ];

    public function __construct(
        string $modelName,
        array $capabilities = [],
        array $options = []
    ) {
        $this->model = new Model($modelName, $capabilities, $options);
        $this->capabilities = $capabilities;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Check if this model supports embeddings/vectorization.
     */
    public function supportsEmbedding(): bool
    {
        // Check if 'embedding' capability is explicitly set
        if (in_array('embedding', $this->capabilities, true)) {
            return true;
        }

        // Check if model name matches known embedding patterns
        $modelName = strtolower($this->model->getName());
        foreach (self::EMBEDDING_MODEL_PATTERNS as $pattern) {
            if (str_contains($modelName, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get model capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public static function fromModel(Model $model): self
    {
        $instance = new self($model->getName(), [], []);
        $instance->model = $model;

        return $instance;
    }
}
