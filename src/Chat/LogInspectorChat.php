<?php

namespace Hakam\AiLogInspector\Chat;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\ChatInterface;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Conversational Log Inspector Chat.
 *
 * Wraps the LogInspectorAgent with chat capabilities for multi-turn
 * conversations that maintain context across questions.
 *
 * @example
 * ```php
 * $chat = new LogInspectorChat($agent);
 * $chat->startInvestigation('Payment incident - Aug 18, 2024');
 *
 * $response1 = $chat->ask('What payment errors occurred?');
 * $response2 = $chat->ask('Show me the related database errors'); // Has context!
 * $response3 = $chat->ask('What was the root cause?'); // Knows full history!
 * ```
 */
class LogInspectorChat implements ChatInterface
{
    private Chat $chat;
    private bool $initialized = false;

    public function __construct(
        private readonly LogInspectorAgent $agent,
        MessageStoreInterface&ManagedStoreInterface $store,
    ) {
        $store = $store ?? new InMemoryStore();
        $store->setup();

        $this->chat = new Chat($agent, $store);
    }

    /**
     * Start a new investigation session with optional context.
     *
     * @param string      $context      Investigation context (e.g., "Payment incident investigation")
     * @param string|null $systemPrompt Custom system prompt (uses agent default if null)
     */
    public function startInvestigation(string $context = '', ?string $systemPrompt = null): void
    {
        $prompt = $systemPrompt ?? $this->buildInvestigationPrompt($context);

        $this->chat->initiate(new MessageBag(
            Message::forSystem($prompt)
        ));

        $this->initialized = true;
    }

    /**
     * Ask a question in the current investigation.
     *
     * Maintains full conversation history for context-aware responses.
     *
     * @param string $question The question to ask
     *
     * @return AssistantMessage The AI response
     *
     * @throws \RuntimeException If investigation not started
     */
    public function ask(string $question): AssistantMessage
    {
        if (!$this->initialized) {
            $this->startInvestigation();
        }

        return $this->chat->submit(Message::ofUser($question));
    }

    /**
     * Get a quick analysis without starting a full investigation.
     *
     * @param string $question Single question for quick analysis
     *
     * @return AssistantMessage The AI response
     */
    public function quickAnalysis(string $question): AssistantMessage
    {
        $this->startInvestigation('Quick log analysis session');

        return $this->ask($question);
    }

    /**
     * Continue investigation with a follow-up question.
     *
     * Alias for ask() that makes intent clearer in code.
     *
     * @param string $followUp The follow-up question
     *
     * @return AssistantMessage The AI response
     */
    public function followUp(string $followUp): AssistantMessage
    {
        return $this->ask($followUp);
    }

    /**
     * Request a summary of the current investigation.
     *
     * @return AssistantMessage Summary of findings so far
     */
    public function summarize(): AssistantMessage
    {
        return $this->ask(
            'Please provide a summary of our investigation so far, including:
            1. Key findings
            2. Evidence reviewed
            3. Root cause (if identified)
            4. Recommended next steps'
        );
    }

    /**
     * Request a timeline of events from the investigation.
     *
     * @return AssistantMessage Timeline of events
     */
    public function getTimeline(): AssistantMessage
    {
        return $this->ask(
            'Based on our investigation, create a chronological timeline of events
            showing when each issue occurred and how they relate to each other.'
        );
    }

    /**
     * Request remediation recommendations.
     *
     * @return AssistantMessage Remediation steps
     */
    public function getRemediation(): AssistantMessage
    {
        return $this->ask(
            'Based on the root cause identified in our investigation, what are the
            recommended remediation steps to prevent this issue from recurring?'
        );
    }

    /**
     * Initialize chat with existing messages (for session restoration).
     *
     * @param MessageBag $messages Previous conversation messages
     */
    public function initiate(MessageBag $messages): void
    {
        $this->chat->initiate($messages);
        $this->initialized = true;
    }

    /**
     * Submit a user message directly.
     *
     * @param UserMessage $message The user message
     *
     * @return AssistantMessage The AI response
     */
    public function submit(UserMessage $message): AssistantMessage
    {
        if (!$this->initialized) {
            $this->startInvestigation();
        }

        return $this->chat->submit($message);
    }

    /**
     * Check if an investigation has been started.
     *
     * @return bool True if investigation is active
     */
    public function isActive(): bool
    {
        return $this->initialized;
    }

    /**
     * Build the investigation system prompt.
     */
    private function buildInvestigationPrompt(string $context): string
    {
        $basePrompt = <<<PROMPT
You are an expert log analysis assistant conducting an investigation. You have access to tools
for searching logs and tracing requests across services.

INVESTIGATION GUIDELINES:
1. Maintain context across all questions in this conversation
2. Reference previous findings when relevant
3. Build a coherent picture of what happened
4. Cite specific log IDs as evidence for your conclusions
5. Track the timeline of events as you discover them

RESPONSE FORMAT:
- Be concise but thorough
- Use bullet points for clarity
- Include log IDs when referencing specific entries
- Indicate confidence level in your conclusions
PROMPT;

        if (!empty($context)) {
            $basePrompt .= "\n\nINVESTIGATION CONTEXT:\n".$context;
        }

        return $basePrompt;
    }
}
