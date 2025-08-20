<?php

namespace Hakam\AiLogInspector\Agent\Test\Unit;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\StoreInterface;

class LogInspectorAgentTest extends TestCase
{
    private PlatformInterface $platform;
    private Model $model;
    private StoreInterface $store;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(PlatformInterface::class);
        $this->model = $this->createMock(Model::class);
        $this->store = $this->createMock(StoreInterface::class);
    }

    public function testConstruct(): void
    {
        $agent = new LogInspectorAgent(
            $this->platform,
            $this->model,
            $this->store
        );

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testConstructWithCustomSystemPrompt(): void
    {
        $customPrompt = 'Custom system prompt for testing';
        
        $agent = new LogInspectorAgent(
            $this->platform,
            $this->model,
            $this->store,
            $customPrompt
        );

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testAskThrowsExceptionWhenModelDoesNotSupportToolCalling(): void
    {
        $agent = new LogInspectorAgent(
            $this->platform,
            $this->model,
            $this->store
        );

        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage('does not support "tool calling"');
        $agent->ask('What caused the error in the logs?');
    }

    public function testAskWithEmptyQuestion(): void
    {
        $agent = new LogInspectorAgent(
            $this->platform,
            $this->model,
            $this->store
        );

        $this->expectException(MissingModelSupportException::class);
        $agent->ask('');
    }

    public function testAskWithComplexQuestion(): void
    {
        $agent = new LogInspectorAgent(
            $this->platform,
            $this->model,
            $this->store
        );

        $complexQuestion = 'Can you analyze the logs between 2024-01-01 and 2024-01-02 for any database connection errors and provide a summary of the root causes?';
        
        $this->expectException(MissingModelSupportException::class);
        $agent->ask($complexQuestion);
    }
}