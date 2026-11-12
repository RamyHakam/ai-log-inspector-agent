<?php

namespace Hakam\AiLogInspector\Store;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\InMemory\Store;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

final readonly class VectorLogDocumentStore implements VectorLogStoreInterface, StoreInterface
{
    public function __construct(
        private StoreInterface $aiStore = new Store(),
    ) {
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
            if (!$doc instanceof VectorDocumentInterface) {
                throw new \InvalidArgumentException('All documents must be instances of VectorDocument.');
            }
            $this->aiStore->add($doc);
        }
    }

    public function getStore(): StoreInterface
    {
        return $this->aiStore;
    }

    public function queryForVector(Vector $query, array $options = []): iterable
    {
        return $this->aiStore->query(new VectorQuery($query), $options);
    }

    public function add(VectorDocumentInterface|array $documents): void
    {
        $this->aiStore->add($documents);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        $this->aiStore->remove($ids, $options);
    }

    public function clear(array $options = []): void
    {
        $this->aiStore->clear($options);
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        return $this->aiStore->query($query, $options);
    }

    public function supports(string $queryClass): bool
    {
        return $this->aiStore->supports($queryClass);
    }

    public function count(): int
    {
        return \count($this->aiStore);
    }
}
