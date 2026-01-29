<?php

namespace Hakam\AiLogInspector\Indexer;

use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Vectorizer\VectorizerFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Indexer;

class VectorLogDocumentIndexer implements VectorLogIndexerInterface
{
    private Indexer $indexer;

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly VectorLogStoreInterface $logStore,
    ) {
        $this->indexer = new Indexer(
            VectorizerFactory::create(
                $this->platform,
                $this->model,
            ),
            $this->logStore->getStore()
        );
    }

    public function indexAndSaveLogs(iterable $textDocs): void
    {
        $this->indexer->index($textDocs);
    }
}