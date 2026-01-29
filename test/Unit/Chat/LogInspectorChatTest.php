<?php

namespace Hakam\AiLogInspector\Test\Unit\Chat;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Chat\LogInspectorChat;
use Hakam\AiLogInspector\Chat\SessionMessageStore;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\TextResult;

class LogInspectorChatTest extends TestCase
{
    private LogInspectorAgent $agent;
    private SessionMessageStore $store;
    private LogInspectorChat $chat;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(LogInspectorAgent::class);
        $this->store = $this->createMock(SessionMessageStore::class);
        
        $this->chat = new LogInspectorChat($this->agent, $this->store);
    }

    public function testConstructorCreatesInstance(): void
    {
        $this->assertInstanceOf(LogInspectorChat::class, $this->chat);
    }

    public function testStartInvestigationWithContext(): void
    {
        $context = 'Payment incident investigation - Aug 18, 2024';
        
        $this->chat->startInvestigation($context);
        
        $this->assertTrue($this->chat->isActive());
    }

    public function testStartInvestigationWithoutContext(): void
    {
        $this->chat->startInvestigation();
        
        $this->assertTrue($this->chat->isActive());
    }

    public function testStartInvestigationWithCustomPrompt(): void
    {
        $context = 'Database performance investigation';
        $customPrompt = 'You are a specialized database performance analyzer.';
        
        $this->chat->startInvestigation($context, $customPrompt);
        
        $this->assertTrue($this->chat->isActive());
    }

    public function testAskAutoInitializesIfNotStarted(): void
    {
        $question = 'What errors occurred?';
        $mockResponse = Message::ofAssistant('Found 3 payment errors');
        
        $mockResult = $this->createMock(TextResult::class);
        $mockResult->method('getContent')->willReturn('Found 3 payment errors');
        
        $this->agent
            ->expects($this->once())
            ->method('call')
            ->willReturn($mockResult);

        // Should auto-initialize
        $this->assertFalse($this->chat->isActive());
        
        $response = $this->chat->ask($question);
        
        $this->assertTrue($this->chat->isActive());
        $this->assertInstanceOf(AssistantMessage::class, $response);
    }

    public function testAskWithInitializedChat(): void
    {
        $question = 'What payment errors occurred?';
        $mockResponse = 'Found 3 payment failures';
        
        $mockResult = $this->createMock(TextResult::class);
        $mockResult->method('getContent')->willReturn($mockResponse);
        
        $this->agent
            ->expects($this->once())
            ->method('call')
            ->willReturn($mockResult);
        
        $this->chat->startInvestigation('Payment investigation');
        
        $response = $this->chat->ask($question);
        
        $this->assertInstanceOf(AssistantMessage::class, $response);
    }

    public function testFollowUpQuestionMaintainsContext(): void
    {
        $mockResult1 = $this->createMock(TextResult::class);
        $mockResult1->method('getContent')->willReturn('Found 3 payment errors');
        
        $mockResult2 = $this->createMock(TextResult::class);
        $mockResult2->method('getContent')->willReturn('These errors were caused by database timeouts');
        
        $this->agent
            ->expects($this->exactly(2))
            ->method('call')
            ->willReturnOnConsecutiveCalls($mockResult1, $mockResult2);
        
        $this->chat->startInvestigation('Payment investigation');
        
        $response1 = $this->chat->ask('What payment errors occurred?');
        $this->assertInstanceOf(AssistantMessage::class, $response1);
        
        $response2 = $this->chat->followUp('What caused those errors?');
        $this->assertInstanceOf(AssistantMessage::class, $response2);
    }

    public function testQuickAnalysisAutoInitializes(): void
    {
        $question = 'Show me recent errors';
        $mockResponse = 'Found 5 errors in the last hour';
        
        $mockResult = $this->createMock(TextResult::class);
        $mockResult->method('getContent')->willReturn($mockResponse);
        
        $this->agent
            ->expects($this->once())
            ->method('call')
            ->willReturn($mockResult);
        
        $response = $this->chat->quickAnalysis($question);
        
        $this->assertTrue($this->chat->isActive());
        $this->assertInstanceOf(AssistantMessage::class, $response);
    }

    public function testSummarizeRequestsInvestigationSummary(): void
    {
        $expectedSummary = 'Summary of findings: Root cause was database connection pool exhaustion';
        
        $mockResult = $this->createMock(TextResult::class);
        $mockResult->method('getContent')->willReturn($expectedSummary);
        
        $this->agent
            ->expects($this->once())
            ->method('call')
            ->willReturn($mockResult);
        
        $this->chat->startInvestigation();
        
        $response = $this->chat->summarize();
        
        $this->assertInstanceOf(AssistantMessage::class, $response);
    }

    public function testGetTimelineRequestsChronologicalEvents(): void
    {
        $expectedTimeline = 'Timeline: 14:00 - First error, 14:05 - Cascade failures began';
        
        $mockResult = $this->createMock(TextResult::class);
        $mockResult->method('getContent')->willReturn($expectedTimeline);
        
        $this->agent
            ->expects($this->once())
            ->method('call')
            ->willReturn($mockResult);
        
        $this->chat->startInvestigation();
        
        $response = $this->chat->getTimeline();
        
        $this->assertInstanceOf(AssistantMessage::class, $response);
    }

    public function testGetRemediationRequestsPreventionSteps(): void
    {
        $expectedRemediation = 'Recommended: Increase connection pool size, add circuit breaker';
        
        $mockResult = $this->createMock(TextResult::class);
        $mockResult->method('getContent')->willReturn($expectedRemediation);
        
        $this->agent
            ->expects($this->once())
            ->method('call')
            ->willReturn($mockResult);
        
        $this->chat->startInvestigation();
        
        $response = $this->chat->getRemediation();
        
        $this->assertInstanceOf(AssistantMessage::class, $response);
    }

    public function testInitiateWithMessageBag(): void
    {
        $messages = new MessageBag(
            Message::ofUser('Previous question'),
            Message::ofAssistant('Previous answer')
        );
        
        $this->chat->initiate($messages);
        
        $this->assertTrue($this->chat->isActive());
    }

    public function testSubmitUserMessage(): void
    {
        $message = Message::ofUser('What happened?');
        $mockResponse = 'Payment gateway timeout occurred';
        
        $mockResult = $this->createMock(TextResult::class);
        $mockResult->method('getContent')->willReturn($mockResponse);
        
        $this->agent
            ->expects($this->once())
            ->method('call')
            ->willReturn($mockResult);
        
        $response = $this->chat->submit($message);
        
        $this->assertInstanceOf(AssistantMessage::class, $response);
        $this->assertTrue($this->chat->isActive());
    }

    public function testIsActiveReturnsFalseInitially(): void
    {
        $this->assertFalse($this->chat->isActive());
    }

    public function testIsActiveReturnsTrueAfterStart(): void
    {
        $this->chat->startInvestigation();
        
        $this->assertTrue($this->chat->isActive());
    }

    public function testMultipleTurnConversation(): void
    {
        $mockResult1 = $this->createMock(TextResult::class);
        $mockResult1->method('getContent')->willReturn('Found 3 payment errors');
        
        $mockResult2 = $this->createMock(TextResult::class);
        $mockResult2->method('getContent')->willReturn('Root cause: Database connection timeout');
        
        $mockResult3 = $this->createMock(TextResult::class);
        $mockResult3->method('getContent')->willReturn('Remediation: Increase connection pool');
        
        $this->agent
            ->expects($this->exactly(3))
            ->method('call')
            ->willReturnOnConsecutiveCalls($mockResult1, $mockResult2, $mockResult3);
        
        $this->chat->startInvestigation('Payment incident');
        
        $response1 = $this->chat->ask('What errors occurred?');
        $this->assertInstanceOf(AssistantMessage::class, $response1);
        
        $response2 = $this->chat->ask('What was the root cause?');
        $this->assertInstanceOf(AssistantMessage::class, $response2);
        
        $response3 = $this->chat->ask('How do we fix this?');
        $this->assertInstanceOf(AssistantMessage::class, $response3);
    }
}
