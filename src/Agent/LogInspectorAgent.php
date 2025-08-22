<?php

namespace Hakam\AiLogInspector\Agent;

use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

final  class LogInspectorAgent
{
    private Agent $agent;

    public function __construct(
        private readonly LogDocumentPlatformInterface $platform,
        private readonly  iterable $tools,
        private ?string $systemPrompt = null,
    ) {
        $toolbox = new Toolbox($this->tools);
        $processor = new AgentProcessor($toolbox);
        $this->agent = new Agent(
            $this->platform->getPlatform(),
            $this->platform->getModel(),
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