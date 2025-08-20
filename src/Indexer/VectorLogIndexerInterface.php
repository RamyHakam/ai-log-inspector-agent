<?php

namespace Hakam\AiLogInspector\Indexer;

use Symfony\AI\Store\StoreInterface;

interface VectorLogIndexerInterface
{
    public function indexAndSaveLogs(iterable $textDocs): void;
}
