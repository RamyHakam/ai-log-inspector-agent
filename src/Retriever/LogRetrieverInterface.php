<?php

namespace Hakam\AiLogInspector\Retriever;

use Symfony\AI\Store\Document\VectorDocument;

/**
 * Interface for retrieving log documents from a vector store based on a query string.
 */
interface LogRetrieverInterface
{
    /**
     * Retrieve log documents from the store that are similar to the given query.
     *
     * @param string               $query   The search query to vectorize and use for similarity search
     * @param array<string, mixed> $options Options to pass to the store query (e.g., maxItems, filters)
     *
     * @return iterable<VectorDocument> The retrieved documents with similarity scores
     */
    public function retrieve(string $query, array $options = []): iterable;
}
