<?php

namespace Hakam\AiLogInspector\Chat;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * Persists the message history of one investigation.
 *
 * Symfony AI 1.0 removed the Chat component, so the log inspector keeps
 * its own small store contract for multi-turn investigations.
 */
interface ConversationStoreInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function setup(array $options = []): void;

    public function save(MessageBag $messages): void;

    public function load(): MessageBag;

    public function drop(): void;
}
