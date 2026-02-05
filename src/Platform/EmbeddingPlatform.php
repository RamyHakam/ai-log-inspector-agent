<?php

namespace Hakam\AiLogInspector\Platform;

use Hakam\AiLogInspector\Enum\PlatformEnum;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;

readonly class EmbeddingPlatform extends LogDocumentPlatform implements EmbeddingPlatformInterface
{
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
        $models = $this->getPlatform()->getModelCatalog()->getModels();

        foreach ($models as $model) {
            // Handle both Model objects and arrays
            if ($model instanceof Model && $model->supports(Capability::EMBEDDINGS)) {
                return true;
            }
            // Handle array format from some platform catalogs
            if (is_array($model) && isset($model['capabilities']) && in_array(Capability::EMBEDDINGS, $model['capabilities'], true)) {
                return true;
            }
        }

        return false;
    }
}
