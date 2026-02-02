<?php

namespace Hakam\AiLogInspector\Indexer;

use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Vectorizer\VectorizerFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\IndexerInterface;

class VectorLogDocumentIndexer implements VectorLogIndexerInterface
{
    private IndexerInterface $indexer;

    /**
     * @param PlatformInterface $embeddingPlatform The AI platform for generating embeddings
     * @param string $model The embedding model to use
     * @param LoaderInterface $loader The document loader for loading log files
     * @param VectorLogStoreInterface $logStore Optional custom vector store
     * @param int $chunkSize Size of text chunks for splitting (default: 500)
     * @param int $chunkOverlap Overlap between chunks (default: 100)
     */
    public function __construct(
        private readonly PlatformInterface $embeddingPlatform,
        private readonly string $model,
        private readonly LoaderInterface $loader,
        private readonly VectorLogStoreInterface $logStore = new VectorLogDocumentStore(),
        private readonly int $chunkSize = 500,
        private readonly int $chunkOverlap = 100,
    ) {
        if ($this->checkForEmbeddingSupport() === false) {
            throw new \RuntimeException(sprintf(
                'The model "%s" does not support embeddings. Please use a platform that supports the "%s" capability.',
                $this->model,
                Capability::EMBEDDINGS->name,
            ));
        }

        $this->indexer = new Indexer(
            loader: $this->loader,
            vectorizer: VectorizerFactory::create(
                $this->embeddingPlatform,
                $this->model,
            ),
            store: $this->logStore,
            transformers: [
                new TextSplitTransformer(
                    chunkSize: $this->chunkSize,
                    overlap: $this->chunkOverlap
                ),
            ],
        );
    }

    /**
     * Index a specific log file from the cache directory
     * 
     * @param string $logFileName The log file name (relative to cache directory)
     * @param array $options Additional indexing options
     */
    public function indexLogFile(string $logFileName, array $options = []): void
    {
        $indexerWithSource = $this->indexer->withSource($logFileName);
        $indexerWithSource->index($options);
    }

    /**
     * Index multiple log files from the cache directory
     * 
     * @param array<string> $logFileNames Array of log file names
     * @param array $options Additional indexing options
     */
    public function indexLogFiles(array $logFileNames, array $options = []): void
    {
        $indexerWithSource = $this->indexer->withSource($logFileNames);
        $indexerWithSource->index($options);
    }

    /**
     * Index all log files from the cache directory
     *
     * @param array $options Additional options:
     *                       - 'pattern': glob pattern for filtering files (default: '*.log')
     *                       - 'recursive': whether to search subdirectories (default: false)
     *                       - 'chunk_size': batch size for processing (default: 50)
     */
    public function indexAllLogs(array $options = []): void
    {
        $this->indexer->index($options);
    }


    public function checkForEmbeddingSupport(): bool
    {
        // TODO: this is an existing issue in symfony/ai where hasCapability does not work as expected
        //  Will be fixed in future versions
        return true;
    }
}
