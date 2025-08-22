<?php

namespace Hakam\AiLogInspector\Platform;

use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

interface LogDocumentPlatformInterface
{
    /**
     * Get the platform instance.
     *
     * @return PlatformInterface
     */
    public function getPlatform(): PlatformInterface;
    public function __invoke(string $query, array $options = []): ResultInterface;
}