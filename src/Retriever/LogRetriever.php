<?php

namespace Hakam\AiLogInspector\Retriever;

use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Vectorizer\VectorizerFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Retriever;
use Symfony\AI\Store\RetrieverInterface;

/**
 * Retrieves log documents from a vector store using semantic similarity search.
 *
 * Wraps Symfony's Retriever, mirroring the same pattern used by AbstractLogIndexer.
 */
class LogRetriever implements LogRetrieverInterface
{
    private readonly RetrieverInterface $retriever;

    /**
     * @param PlatformInterface       $embeddingPlatform The AI platform for generating embeddings
     * @param string                  $model             The embedding model to use (e.g., 'text-embedding-3-small')
     * @param VectorLogStoreInterface $logStore          The vector store for querying documents
     */
    public function __construct(
        private readonly PlatformInterface $embeddingPlatform,
        private readonly string $model,
        private readonly VectorLogStoreInterface $logStore,
    ) {
        $this->retriever = new Retriever(
            vectorizer: VectorizerFactory::create($this->embeddingPlatform, $this->model),
            store: $this->logStore,
        );
    }

    /**
     * @return iterable<VectorDocument>
     */
    public function retrieve(string $query, array $options = []): iterable
    {
        return $this->retriever->retrieve($query, $options);
    }
}
