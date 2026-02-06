<?php

namespace Hakam\AiLogInspector\Platform;

use Hakam\AiLogInspector\Enum\PlatformEnum;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;

readonly class EmbeddingPlatform extends LogDocumentPlatform implements EmbeddingPlatformInterface
{
    /**
     * Known embedding model class names across different platforms.
     * Some platforms (e.g. OpenAI) don't tag embedding models with Capability::EMBEDDINGS
     * but use a dedicated class instead.
     */
    private const EMBEDDING_MODEL_CLASSES = [
        'Symfony\AI\Platform\Bridge\OpenAi\Embeddings',
    ];

    public function __construct(
        private PlatformEnum $platformType,
        private array $config,
    ) {
        parent::__construct($platformType, $config);
        if (!$this->supportsEmbedding()) {
            throw new \RuntimeException("The platform {$this->platformType->name} does not support embedding models.");
        }
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    /**
     * Check if the platform has at least one model supporting embeddings.
     */
    public function supportsEmbedding(): bool
    {
        // TODO: this is a temporary workaround until we have a more robust way to determine embedding support per platform.
        // this will be fixed in the new Symfony AI version where platforms will have embedding-specific capabilities and models will be tagged accordingly.
        $models = $this->getPlatform()->getModelCatalog()->getModels();

        foreach ($models as $model) {
            // Handle Model objects with explicit EMBEDDINGS capability (e.g. Ollama)
            if ($model instanceof Model && $model->supports(Capability::EMBEDDINGS)) {
                return true;
            }

            // Handle array format from platform catalogs
            if (is_array($model)) {
                // Check explicit EMBEDDINGS capability
                if (isset($model['capabilities']) && in_array(Capability::EMBEDDINGS, $model['capabilities'], true)) {
                    return true;
                }
                // Check for known embedding model classes (e.g. OpenAI Embeddings class)
                if (isset($model['class']) && in_array($model['class'], self::EMBEDDING_MODEL_CLASSES, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
