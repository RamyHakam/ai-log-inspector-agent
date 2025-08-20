<?php

namespace Hakam\AiLogInspector\Store;

interface VectorLogStoreInterface
{
    public function saveLogVectorDocuments(array $logVectorsDocs): void;

}