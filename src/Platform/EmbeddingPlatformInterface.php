<?php

namespace Hakam\AiLogInspector\Platform;

interface EmbeddingPlatformInterface
{
    /**
     * Check if the platform/model supports embeddings for vectorization.
     *
     * Chat models (GPT-4, Claude, etc.) do NOT support embeddings.
     * Embedding models (text-embedding-ada-002, nomic-embed-text, etc.) DO support embeddings.
     */
    public function supportsEmbedding(): bool;
}
