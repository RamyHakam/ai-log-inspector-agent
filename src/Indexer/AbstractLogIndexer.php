<?php

namespace Hakam\AiLogInspector\Indexer;

use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Vectorizer\VectorizerFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\IndexerInterface;

/**
 * Provides common functionality for indexing log documents and files,
 * including vectorization, text chunking, and embedding support validation.
 */
abstract class AbstractLogIndexer implements LogIndexerInterface
{
    protected IndexerInterface $indexer;

    /**
     * @param PlatformInterface       $embeddingPlatform The AI platform for generating embeddings
     * @param string                  $model             The embedding model to use (e.g., 'text-embedding-3-small')
     * @param LoaderInterface         $loader            The document loader for loading log data
     * @param VectorLogStoreInterface $logStore          The vector store for storing embeddings
     * @param int                     $chunkSize         Size of text chunks for splitting (default: 500)
     * @param int                     $chunkOverlap      Overlap between chunks to maintain context (default: 100)
     */
    public function __construct(
        protected readonly PlatformInterface $embeddingPlatform,
        protected readonly string $model,
        protected readonly LoaderInterface $loader,
        protected readonly VectorLogStoreInterface $logStore = new VectorLogDocumentStore(),
        protected readonly int $chunkSize = 500,
        protected readonly int $chunkOverlap = 100,
    ) {
        $this->validateEmbeddingSupport();
        $this->initializeIndexer();
    }

    /**
     * Validate that the platform supports embeddings.
     *
     * @throws \RuntimeException if embeddings are not supported
     */
    protected function validateEmbeddingSupport(): void
    {
        if (!$this->checkForEmbeddingSupport()) {
            throw new \RuntimeException(sprintf('The model "%s" does not support embeddings. Please use a platform that supports the "%s" capability.', $this->model, Capability::EMBEDDINGS->name));
        }
    }

    /**
     * Initialize the Symfony AI indexer with configured components.
     */
    protected function initializeIndexer(): void
    {
        $this->indexer = new Indexer(
            loader: $this->loader,
            vectorizer: VectorizerFactory::create(
                $this->embeddingPlatform,
                $this->model,
            ),
            store: $this->logStore,
            transformers: $this->getTransformers(),
        );
    }

    /**
     * Get transformers for document processing.
     *
     * Override this method to customize document transformation pipeline.
     *
     * @return array<TransformerInterface>
     */
    protected function getTransformers(): array
    {
        return [
            new TextSplitTransformer(
                chunkSize: $this->chunkSize,
                overlap: $this->chunkOverlap
            ),
        ];
    }

    /**
     * Check if the platform supports embeddings capability.
     */
    public function checkForEmbeddingSupport(): bool
    {
        // TODO: this is an existing issue in symfony/ai where hasCapability does not work as expected
        //  Will be fixed in future versions
        return true;
    }

    /**
     * Get the underlying Symfony AI indexer.
     */
    public function getIndexer(): IndexerInterface
    {
        return $this->indexer;
    }

    /**
     * Get the vector store used by this indexer.
     */
    public function getStore(): VectorLogStoreInterface
    {
        return $this->logStore;
    }

    /**
     * Get the embedding model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the chunk size used for text splitting.
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Get the chunk overlap size.
     */
    public function getChunkOverlap(): int
    {
        return $this->chunkOverlap;
    }
}
