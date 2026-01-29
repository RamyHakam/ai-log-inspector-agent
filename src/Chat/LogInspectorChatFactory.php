<?php

namespace Hakam\AiLogInspector\Chat;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;

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

        return new LogInspectorChat($agent, new SessionMessageStore());
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
