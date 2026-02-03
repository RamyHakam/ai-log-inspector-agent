<?php

namespace Hakam\AiLogInspector\Indexer;

use Hakam\AiLogInspector\Document\LogDocumentInterface;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\LoaderInterface;

/**
 * Indexer for individual log documents.
 *
 * This indexer is optimized for indexing log documents that are already
 * in memory or loaded from a custom source. It uses an InMemoryLoader
 * by default but can accept any LoaderInterface implementation.
 */
class LogDocumentIndexer extends AbstractLogIndexer
{
    /**
     * @param PlatformInterface       $embeddingPlatform The AI platform for generating embeddings
     * @param string                  $model             The embedding model to use (e.g., 'text-embedding-3-small')
     * @param LoaderInterface         $loader            The document loader (default: InMemoryLoader)
     * @param VectorLogStoreInterface $logStore          The vector store for storing embeddings
     * @param int                     $chunkSize         Size of text chunks for splitting (default: 500)
     * @param int                     $chunkOverlap      Overlap between chunks to maintain context (default: 100)
     */
    public function __construct(
        PlatformInterface $embeddingPlatform,
        string $model,
        LoaderInterface $loader = new InMemoryLoader(),
        VectorLogStoreInterface $logStore = new VectorLogDocumentStore(),
        int $chunkSize = 500,
        int $chunkOverlap = 100,
    ) {
        parent::__construct(
            $embeddingPlatform,
            $model,
            $loader,
            $logStore,
            $chunkSize,
            $chunkOverlap
        );
    }

    /**
     * Index multiple log documents.
     *
     * @param array<LogDocumentInterface> $logDocuments The log documents to index
     */
    public function indexLogDocuments(array $logDocuments): void
    {
        $this->indexer->withSource($logDocuments);
        $this->indexer->index($logDocuments);
    }
}
