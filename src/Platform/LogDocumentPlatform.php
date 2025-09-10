<?php

namespace Hakam\AiLogInspector\Platform;

use Hakam\AiLogInspector\Model\LogDocumentModelInterface;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

class LogDocumentPlatform implements LogDocumentPlatformInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly LogDocumentModelInterface $model,)
    {
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    public function __invoke( $query, array $options = []): ResultInterface
    {
        // Convert string queries to MessageBag for Ollama compatibility
        if (is_string($query) && $this->model->getModel() instanceof Ollama) {
            $content = new Text($query);
            $message = new UserMessage($content);
            $query = new MessageBag($message);
        }
        
        $resultPromise = $this->platform->invoke($this->model->getModel(), $query, $options);
       return $resultPromise->getResult();
    }

    public function getModel(): Model
    {
        return $this->model->getModel();
    }
}