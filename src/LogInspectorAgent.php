<?php

namespace Hakam\AiLogInspector\Agent;

use Hakam\AiLogInspector\Agent\Tool\LogSearchTool;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Store\StoreInterface;

final  class LogInspectorAgent
{
    private Agent $agent;

    public function __construct(
        private PlatformInterface $platform,
        private Model $model,
        private StoreInterface $store,
        private ?string $systemPrompt = null
    ) {
        $tool = new LogSearchTool($store, $platform, $model);
        $toolbox = new Toolbox([
            $tool,
        ]);
        $processor = new AgentProcessor($toolbox);

        $this->agent = new Agent(
            $platform,
            $model,
            inputProcessors: [$processor],
            outputProcessors: [$processor]
        );

        $this->systemPrompt = $systemPrompt ?? <<<PROMPT
            You are an AI Log Inspector.
            Use the `log_search` tool to find relevant log entries.
            Always explain based on logs, cite log IDs if available.
            If no logs are relevant, say you cannot find the reason.
        PROMPT;
    }

    public function ask(string $question) : ResultInterface
    {
        $messages = new MessageBag(
            Message::forSystem($this->systemPrompt),
            Message::ofUser($question),
        );

        return $this->agent->call($messages);
    }
}