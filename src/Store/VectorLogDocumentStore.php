<?php

namespace Hakam\AiLogInspector\Store;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\InMemory\Store;
use Symfony\AI\Store\StoreInterface;

final readonly class VectorLogDocumentStore implements VectorLogStoreInterface,StoreInterface
{
    public function __construct(
        private StoreInterface $aiStore = new Store()
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

    public function add(VectorDocument|array $documents): void
    {
        $this->aiStore->add($documents);
    }

    public function query(Vector $vector, array $options = []): iterable
    {
        return $this->aiStore->query($vector, $options);
    }
}
