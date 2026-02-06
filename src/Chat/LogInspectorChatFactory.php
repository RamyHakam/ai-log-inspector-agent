<?php

namespace Hakam\AiLogInspector\Chat;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;
use Symfony\Component\Uid\Uuid;

class LogInspectorChatFactory
{
    public static function create(
        LogDocumentPlatformInterface $platform,
        LogSearchTool $searchTool,
        ?RequestContextTool $contextTool = null,
    ): LogInspectorChat {
        $tools = [$searchTool];
        if ($contextTool) {
            $tools[] = $contextTool;
        }

        $agent = new LogInspectorAgent($platform, $tools);

        // Use a unique session ID for non-persistent chats to avoid conflicts
        return new LogInspectorChat($agent, new SessionMessageStore(Uuid::v4()->toRfc4122()));
    }

    public static function createSession(
        string $sessionId,
        LogDocumentPlatformInterface $platform,
        LogSearchTool $searchTool,
        ?RequestContextTool $contextTool = null,
        string $storagePath = '/tmp/log-inspector-sessions',
    ): LogInspectorChat {
        $tools = [$searchTool];
        if ($contextTool) {
            $tools[] = $contextTool;
        }

        $agent = new LogInspectorAgent($platform, $tools);
        $store = new SessionMessageStore($sessionId, $storagePath);
        $store->setup();

        return new LogInspectorChat($agent, $store);
    }
}
