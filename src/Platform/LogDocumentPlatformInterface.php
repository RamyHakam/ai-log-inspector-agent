<?php

namespace Hakam\AiLogInspector\Platform;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

interface LogDocumentPlatformInterface
{
    /**
     * Get the platform instance.
     */
    public function getPlatform(): PlatformInterface;

    public function __invoke(string $query, array $options = []): ResultInterface;

    /**
     * Get the model instance.
     */
    public function getModel(): Model;

    /**
     * Check if the platform/model supports embeddings for vectorization.
     *
     * Chat models (GPT-4, Claude, etc.) do NOT support embeddings.
     * Embedding models (text-embedding-ada-002, nomic-embed-text, etc.) DO support embeddings.
     */
    public function supportsEmbedding(): bool;
}
