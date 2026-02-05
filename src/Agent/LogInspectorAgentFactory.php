<?php

namespace Hakam\AiLogInspector\Agent;

use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Retriever\LogRetrieverInterface;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;

class LogInspectorAgentFactory
{
    public static function create(
        LogDocumentPlatformInterface $platform,
        VectorLogStoreInterface $store,
        LogRetrieverInterface $retriever,
        ?string $systemPrompt = null
    ): LogInspectorAgent {
        $tools = [
            new LogSearchTool($store, $retriever, $platform),
            new RequestContextTool($store, $retriever, $platform),
        ];

        return new LogInspectorAgent($platform, $tools, $systemPrompt);
    }

    public static function createWithAdditionalTools(
        LogDocumentPlatformInterface $platform,
        VectorLogStoreInterface $store,
        LogRetrieverInterface $retriever,
        iterable $additionalTools,
        ?string $systemPrompt = null
    ): LogInspectorAgent {
        $defaultTools = [
            new LogSearchTool($store, $retriever, $platform),
            new RequestContextTool($store, $retriever, $platform),
        ];

        $allTools = array_merge($defaultTools, is_array($additionalTools) ? $additionalTools : iterator_to_array($additionalTools));

        return new LogInspectorAgent($platform, $allTools, $systemPrompt);
    }

    public static function createWithSearchOnly(
        LogDocumentPlatformInterface $platform,
        VectorLogStoreInterface $store,
        LogRetrieverInterface $retriever,
        ?string $systemPrompt = null
    ): LogInspectorAgent {
        $tools = [
            new LogSearchTool($store, $retriever, $platform),
        ];

        return new LogInspectorAgent($platform, $tools, $systemPrompt);
    }

    public static function createWithRequestContextOnly(
        LogDocumentPlatformInterface $platform,
        VectorLogStoreInterface $store,
        LogRetrieverInterface $retriever,
        ?string $systemPrompt = null
    ): LogInspectorAgent {
        $tools = [
            new RequestContextTool($store, $retriever, $platform),
        ];

        return new LogInspectorAgent($platform, $tools, $systemPrompt);
    }

    public static function createWithCustomToolsOnly(
        LogDocumentPlatformInterface $platform,
        iterable $customTools,
        ?string $systemPrompt = null
    ): LogInspectorAgent {
        return new LogInspectorAgent($platform, $customTools, $systemPrompt);
    }
}
