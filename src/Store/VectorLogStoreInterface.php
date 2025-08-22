<?php

namespace Hakam\AiLogInspector\Store;

use Symfony\AI\Platform\Vector\Vector;

interface VectorLogStoreInterface
{
    public function saveLogVectorDocuments(array $logVectorsDocs): void;

    public function queryForVector(Vector $query, array $options = []): array;

}