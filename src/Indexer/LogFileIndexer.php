<?php

namespace Hakam\AiLogInspector\Indexer;

use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\Loader\TextFileLoader;
use Symfony\AI\Store\Document\LoaderInterface;

/**
 * Indexer for log files from the filesystem.
 *
 * This indexer is optimized for indexing log files stored on disk.
 * It requires a LoaderInterface that can read from the filesystem
 * (e.g., FileLoader, DirectoryLoader).
 */
class LogFileIndexer extends AbstractLogIndexer
{
    /**
     * @param PlatformInterface       $embeddingPlatform The AI platform for generating embeddings
     * @param string                  $model             The embedding model to use (e.g., 'text-embedding-3-small')
     * @param LoaderInterface         $loader            The document loader for loading log files from disk
     * @param VectorLogStoreInterface $logStore          The vector store for storing embeddings
     * @param int                     $chunkSize         Size of text chunks for splitting (default: 500)
     * @param int                     $chunkOverlap      Overlap between chunks to maintain context (default: 100)
     */
    public function __construct(
        PlatformInterface $embeddingPlatform,
        string $model,
        LoaderInterface $loader = new TextFileLoader(),
        VectorLogStoreInterface $logStore = new VectorLogDocumentStore(),
        int $chunkSize = 500,
        int $chunkOverlap = 100,
    ) {
        parent::__construct(
            $embeddingPlatform,
            $model,
            $loader,
            $logStore,
            $chunkSize,
            $chunkOverlap
        );
    }

    /**
     * Index a specific log file.
     *
     * @param string $logFileName The log file name or path
     * @param array  $options     Additional indexing options
     */
    public function indexLogFile(string $logFileName, array $options = []): void
    {
        $indexerWithSource = $this->indexer->withSource($logFileName);
        $indexerWithSource->index($options);
    }

    /**
     * Index multiple log files.
     *
     * @param array<string> $logFileNames Array of log file names or paths
     * @param array         $options      Additional indexing options
     */
    public function indexLogFiles(array $logFileNames, array $options = []): void
    {
        $indexerWithSource = $this->indexer->withSource($logFileNames);
        $indexerWithSource->index($options);
    }

    /**
     * Index all log files from the configured loader source.
     *
     * @param array $options Additional options:
     *                       - 'pattern': glob pattern for filtering files (default: '*.log')
     *                       - 'recursive': whether to search subdirectories (default: false)
     *                       - 'batch_size': batch size for processing (default: 50)
     */
    public function indexAllLogs(array $options = []): void
    {
        $this->indexer->index($options);
    }
}
