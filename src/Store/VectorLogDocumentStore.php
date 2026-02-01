<?php

namespace Hakam\AiLogInspector\Store;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\StoreInterface;

final readonly class VectorLogDocumentStore implements VectorLogStoreInterface
{
    public function __construct(
        private StoreInterface $aiStore
    )
    {
    }

    /**
     * Save log vectors to the store.
     *
     * @param VectorDocument[] $logVectorsDocs
     */
    public function saveLogVectorDocuments(array $logVectorsDocs): void
    {
        if (empty($logVectorsDocs)) {
            return;
        }

        foreach ($logVectorsDocs as $doc) {
            if (!$doc instanceof VectorDocument) {
                throw new \InvalidArgumentException('All documents must be instances of VectorDocument.');
            }
            $this->aiStore->add($doc);
        }
    }

    // getStore
    public function getStore(): StoreInterface
    {
        return $this->aiStore;
    }

    public function queryForVector(Vector $query, array $options = []): iterable
    {
        return $this->aiStore->query($query, $options);
    }
}
