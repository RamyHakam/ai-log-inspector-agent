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
use Symfony\Component\HttpClient\Exception\ClientException;

class LogDocumentPlatform implements LogDocumentPlatformInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly LogDocumentModelInterface $model,)
    {}

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
        
        try {
            $resultPromise = $this->platform->invoke($this->model->getModel()->getName(), $query, $options);
            return $resultPromise->getResult();
        } catch (ClientException $e) {
            // Handle common Ollama API compatibility issues
            $errorMessage = $e->getMessage();
            
            if (str_contains($errorMessage, '404') && str_contains($errorMessage, '/api/show')) {
                throw new \RuntimeException(
                    'Ollama API endpoint "/api/show" not found. This indicates an incompatible Ollama version. ' .
                    'Please ensure you are using Ollama v0.1.0+ and the model "' . $this->model->getModel()->getName() . '" is properly loaded. ' .
                    'Try: ollama pull ' . $this->model->getModel()->getName(),
                    0,
                    $e
                );
            }
            
            if (str_contains($errorMessage, '404')) {
                throw new \RuntimeException(
                    'Ollama API endpoint not found. Please ensure Ollama is running on the correct host and the API is accessible.',
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    public function getModel(): Model
    {
        return $this->model->getModel();
    }
}
