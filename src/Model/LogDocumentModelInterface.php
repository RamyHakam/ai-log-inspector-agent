<?php

namespace Hakam\AiLogInspector\Model;

interface LogDocumentModelInterface
{
    public function getModel();

    /**
     * Check if this model supports embeddings/vectorization
     */
    public function supportsEmbedding(): bool;

    /**
     * Get model capabilities
     */
    public function getCapabilities(): array;
}
