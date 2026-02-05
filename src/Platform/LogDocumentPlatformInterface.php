<?php

namespace Hakam\AiLogInspector\Platform;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

interface LogDocumentPlatformInterface extends PlatformInterface
{
    /**
     * Get the platform instance.
     */
    public function getPlatform(): PlatformInterface;

    /**
     * Get the default model for this platform.
     */
    public function getModel(): Model;

    /**
     * Invoke the platform with a prompt string for AI analysis.
     *
     * This is a convenience method that wraps invoke() for simple text-in/text-out scenarios.
     */
    public function __invoke(string|array $prompt): ResultInterface;
}
