<?php

namespace Hakam\AiLogInspector\Service;

use Hakam\AiLogInspector\Document\VirtualLogDocument;
use Hakam\AiLogInspector\Factory\TextDocumentFactory;
use Hakam\AiLogInspector\Indexer\VectorLogIndexerInterface;

final readonly class LogProcessorService
{
    public function __construct(
     private VectorLogIndexerInterface $indexer,
    )
    {}

    public function process(array $VirtualDocs): void
    {
        if (empty($VirtualDocs)) {
            return;
        }
        $textDocs = array_map(fn(VirtualLogDocument $doc) => TextDocumentFactory::createFromVirtualDocument($doc), $VirtualDocs);
        $this->indexer->indexAndSaveLogs($textDocs);
    }
}
