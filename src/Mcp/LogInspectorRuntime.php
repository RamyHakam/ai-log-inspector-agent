<?php

namespace Hakam\AiLogInspector\Mcp;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Enum\PlatformEnum;
use Hakam\AiLogInspector\Indexer\LogDocumentIndexer;
use Hakam\AiLogInspector\Platform\LogDocumentPlatform;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Retriever\LogRetriever;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;

/**
 * Wires the log inspector (brain platform, embeddings, vector store, tools, agent)
 * from environment variables, so the MCP server needs no framework or config files.
 *
 *  LOG_INSPECTOR_PROVIDER            openai | anthropic | ollama (default: openai)
 *  LOG_INSPECTOR_MODEL               chat model (default per provider)
 *  LOG_INSPECTOR_EMBEDDING_PROVIDER  openai | ollama (default: the chat provider, ollama for anthropic)
 *  LOG_INSPECTOR_EMBEDDING_MODEL     embedding model (default per provider)
 *  LOG_INSPECTOR_PATHS               comma-separated log files or directories indexed at startup
 *  OPENAI_API_KEY, ANTHROPIC_API_KEY, OLLAMA_HOST, OLLAMA_TIMEOUT (seconds per model call, default 300)
 */
final class LogInspectorRuntime
{
    private const CHAT_MODELS = [
        'openai' => 'gpt-5.4-mini',
        'anthropic' => 'claude-sonnet-5-5',
        'ollama' => 'llama3.2',
    ];

    private const EMBEDDING_MODELS = [
        'openai' => 'text-embedding-3-small',
        'ollama' => 'nomic-embed-text',
    ];

    private function __construct(
        public readonly LogInspectorMcpTools $tools,
        public readonly LogInspectorAgent $agent,
        public readonly VectorLogDocumentStore $store,
        /** @var list<string> */
        public readonly array $startupPaths,
        /** @var array<string, string> */
        public readonly array $description,
    ) {
    }

    /**
     * @param array<string, string|false|null> $env
     */
    public static function fromEnv(array $env): self
    {
        $get = static fn (string $key, ?string $default = null): ?string => (isset($env[$key]) && '' !== $env[$key] && false !== $env[$key]) ? (string) $env[$key] : $default;

        $provider = strtolower($get('LOG_INSPECTOR_PROVIDER', 'openai'));
        $chatModel = $get('LOG_INSPECTOR_MODEL', self::CHAT_MODELS[$provider] ?? null)
            ?? throw new \InvalidArgumentException(\sprintf('Unknown LOG_INSPECTOR_PROVIDER "%s".', $provider));

        $embeddingProvider = strtolower($get('LOG_INSPECTOR_EMBEDDING_PROVIDER', 'anthropic' === $provider ? 'ollama' : $provider));
        $embeddingModel = $get('LOG_INSPECTOR_EMBEDDING_MODEL', self::EMBEDDING_MODELS[$embeddingProvider] ?? null)
            ?? throw new \InvalidArgumentException(\sprintf('Provider "%s" has no embedding models, set LOG_INSPECTOR_EMBEDDING_PROVIDER to openai or ollama.', $embeddingProvider));

        $brain = self::platform($provider, $chatModel, $get);
        $embeddings = self::platform($embeddingProvider, $embeddingModel, $get);

        $store = new VectorLogDocumentStore();
        $retriever = new LogRetriever($embeddings->getPlatform(), $embeddingModel, $store);
        $logSearch = new LogSearchTool($store, $retriever, $brain);
        $requestContext = new RequestContextTool($store, $retriever, $brain);
        $agent = new LogInspectorAgent($brain, [$logSearch, $requestContext]);
        $indexer = new LogDocumentIndexer($embeddings->getPlatform(), $embeddingModel, logStore: $store);

        $paths = array_values(array_filter(array_map('trim', explode(',', $get('LOG_INSPECTOR_PATHS', '') ?? ''))));

        return new self(
            new LogInspectorMcpTools($logSearch, $requestContext, $agent, $indexer),
            $agent,
            $store,
            $paths,
            [
                'provider' => $provider,
                'model' => $chatModel,
                'embedding_provider' => $embeddingProvider,
                'embedding_model' => $embeddingModel,
            ],
        );
    }

    /**
     * Index the LOG_INSPECTOR_PATHS given at startup.
     *
     * @return array<string, mixed>
     */
    public function indexStartupPaths(): array
    {
        $result = [];
        foreach ($this->startupPaths as $path) {
            $result[$path] = $this->tools->indexLogs($path);
        }

        return $result;
    }

    /**
     * @param \Closure(string, ?string=): ?string $get
     */
    private static function platform(string $provider, string $model, \Closure $get): LogDocumentPlatformInterface
    {
        return match ($provider) {
            'openai' => new LogDocumentPlatform(PlatformEnum::OPENAI, [
                'api_key' => $get('OPENAI_API_KEY') ?? throw new \InvalidArgumentException('OPENAI_API_KEY is not set.'),
                'model' => $model,
            ]),
            'anthropic' => new LogDocumentPlatform(PlatformEnum::ANTHROPIC, [
                'api_key' => $get('ANTHROPIC_API_KEY') ?? throw new \InvalidArgumentException('ANTHROPIC_API_KEY is not set.'),
                'model' => $model,
            ]),
            'ollama' => new LogDocumentPlatform(PlatformEnum::OLLAMA, [
                'host' => $get('OLLAMA_HOST', 'http://localhost:11434'),
                'model' => $model,
                'timeout' => (float) $get('OLLAMA_TIMEOUT', '300'),
            ]),
            default => throw new \InvalidArgumentException(\sprintf('Unknown provider "%s", use openai, anthropic or ollama.', $provider)),
        };
    }
}
