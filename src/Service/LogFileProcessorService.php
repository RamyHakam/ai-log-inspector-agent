<?php

namespace Hakam\AiLogInspector\Service;

use Hakam\AiLogInspector\Indexer\LogFileIndexer;

readonly class LogFileProcessorService
{
    public function __construct(
        private LogFileIndexer $indexer,
    ) {
    }

    public function processLogFiles(array $files, array $options = []): void
    {
        if (empty($files)) {  // Process all log files if none are specified
            $this->indexer->indexAllLogs($options);

            return;
        }

        $this->indexer->indexLogFiles($files, $options);
    }
}
