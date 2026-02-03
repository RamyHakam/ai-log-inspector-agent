<?php

namespace Hakam\AiLogInspector\Vectorizer;

use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\Vectorizer;

final readonly class LogDocumentVectorizer implements LogDocumentVectorizerInterface
{
    private Vectorizer $vectorizer;

    public function __construct(
        private PlatformInterface $platform,
        private string $model,
    ) {
        $this->vectorizer = new Vectorizer($this->platform, $this->model);
    }

    public function vectorizeLogTextDocuments(array $logTextDocs): array
    {
        return $this->vectorizer->vectorize($logTextDocs);
    }

    public function getVectorizer(): Vectorizer
    {
        return $this->vectorizer;
    }
}
