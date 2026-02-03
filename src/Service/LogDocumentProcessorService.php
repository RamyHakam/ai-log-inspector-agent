<?php

namespace Hakam\AiLogInspector\Service;

use Hakam\AiLogInspector\Document\LogDocumentFactory;
use Hakam\AiLogInspector\DTO\LogDataDTO;
use Hakam\AiLogInspector\Indexer\LogDocumentIndexer;

readonly class LogDocumentProcessorService
{
    public function __construct(
        private LogDocumentIndexer $indexer,
    ) {
    }

    public function processDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $this->indexer->indexLogDocuments($documents);
    }

    /**
     * Process a single log data entry (DTO or array).
     *
     * @param LogDataDTO|array $logData Single log data entry
     * @param array $options Additional indexing options
     */
    public function processData(LogDataDTO|array $logData, array $options = []): void
    {
        if (empty($logData)) {
            return;
        }

        $documents = [];
        foreach ($logData as $data) {
            $documents[] = LogDocumentFactory::createFromData($data);
        }

        $this->processDocuments($documents, );
    }
}
