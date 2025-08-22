<?php

namespace Hakam\AiLogInspector\Platform;

use Hakam\AiLogInspector\Model\LogDocumentModelInterface;
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
        $resultPromise = $this->platform->invoke($this->model->getModel(), $query, $options);
       return $resultPromise->getResult();
    }
}