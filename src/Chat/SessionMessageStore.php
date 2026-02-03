<?php

namespace Hakam\AiLogInspector\Chat;

use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Uid\Uuid;

/**
 * Persistent Message Store for Investigation Sessions.
 *
 * Stores conversation history to disk, allowing investigations to be
 * paused and resumed across multiple sessions or application restarts.
 *
 * @example
 * ```php
 * // Start investigation
 * $store = new SessionMessageStore('incident-2024-08-18');
 * $chat = new LogInspectorChat($agent, $store);
 * $chat->ask('What errors occurred?');
 *
 * // Later, resume the same investigation
 * $store = new SessionMessageStore('incident-2024-08-18');
 * $chat = new LogInspectorChat($agent, $store);
 * $chat->ask('What were your previous findings?'); // Has full history!
 * ```
 */
class SessionMessageStore implements MessageStoreInterface, ManagedStoreInterface
{
    private string $storagePath;

    /**
     * @param string $sessionId Unique identifier for this investigation session
     * @param string $basePath  Base directory for storing sessions
     */
    public function __construct(
        private readonly string $sessionId = Uuid::NAMESPACE_X500,
        string $basePath = '/tmp/log-inspector-sessions',
    ) {
        $this->storagePath = rtrim($basePath, '/');
    }

    /**
     * Set up the storage directory.
     *
     * @param array<string, mixed> $options Configuration options (unused)
     */
    public function setup(array $options = []): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Save messages to the session file.
     *
     * @param MessageBag $messages The messages to persist
     */
    public function save(MessageBag $messages): void
    {
        $filePath = $this->getFilePath();
        $data = [
            'session_id' => $this->sessionId,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'messages' => serialize($messages),
        ];

        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Load messages from the session file.
     *
     * @return MessageBag The stored messages (empty bag if none exist)
     */
    public function load(): MessageBag
    {
        $filePath = $this->getFilePath();

        if (!file_exists($filePath)) {
            return new MessageBag();
        }

        $data = json_decode(file_get_contents($filePath), true);

        if (!isset($data['messages'])) {
            return new MessageBag();
        }

        return unserialize($data['messages']);
    }

    /**
     * Delete the session and all its messages.
     */
    public function drop(): void
    {
        $filePath = $this->getFilePath();

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Check if this session exists.
     *
     * @return bool True if session file exists
     */
    public function exists(): bool
    {
        return file_exists($this->getFilePath());
    }

    /**
     * Get the session ID.
     *
     * @return string The session identifier
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Get session metadata without loading full messages.
     *
     * @return array<string, mixed>|null Session metadata or null if not found
     */
    public function getMetadata(): ?array
    {
        $filePath = $this->getFilePath();

        if (!file_exists($filePath)) {
            return null;
        }

        $data = json_decode(file_get_contents($filePath), true);

        return [
            'session_id' => $data['session_id'] ?? $this->sessionId,
            'updated_at' => $data['updated_at'] ?? null,
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
        ];
    }

    /**
     * List all available sessions in the storage directory.
     *
     * @return array<string, array<string, mixed>> Array of session metadata keyed by session ID
     */
    public function listSessions(): array
    {
        if (!is_dir($this->storagePath)) {
            return [];
        }

        $sessions = [];
        $files = glob($this->storagePath.'/*.session.json');

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $sessionId = $data['session_id'] ?? basename($file, '.session.json');

            $sessions[$sessionId] = [
                'session_id' => $sessionId,
                'updated_at' => $data['updated_at'] ?? null,
                'file_path' => $file,
                'file_size' => filesize($file),
            ];
        }

        // Sort by updated_at descending (most recent first)
        uasort($sessions, function ($a, $b) {
            return ($b['updated_at'] ?? '') <=> ($a['updated_at'] ?? '');
        });

        return $sessions;
    }

    /**
     * Get the file path for this session.
     *
     * @return string Full path to the session file
     */
    private function getFilePath(): string
    {
        // Sanitize session ID for filesystem safety
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->sessionId);

        return $this->storagePath.'/'.$safeId.'.session.json';
    }
}
