<?php

namespace Hakam\AiLogInspector\Document;

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;

readonly class LogDocument implements LogDocumentInterface
{
    private TextDocument $textDocument;

    public function __construct(
        private string $content,
        private array $rowMetadata = []
    ) {
        $this->textDocument = new TextDocument(
            uniqid('', true),
            $this->content,
            $this->buildMetadata()
        );
    }

    public function getId(): string
    {
        return $this->textDocument->getId();
    }

    public function getContent(): string
    {
        return $this->textDocument->getContent();
    }

    public function getMetadata(): Metadata
    {
        return $this->textDocument->getMetadata();
    }

    private function buildMetadata(): Metadata
    {
        $metadata = new Metadata();

        foreach ($this->rowMetadata as $key => $value) {
            $metadata[$key] = $value;
        }
        if (!isset($this->metadata['timestamp'])) {
            $metadata['timestamp'] = new \DateTime()->format(\DateTimeInterface::ATOM);
        }

        return $metadata;
    }
}
