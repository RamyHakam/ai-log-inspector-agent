<?php

namespace Hakam\AiLogInspector\Vectorizer;

interface LogDocumentVectorizerInterface
{
    public function  vectorizeLogTextDocuments(array $logTextDocs): array;
}