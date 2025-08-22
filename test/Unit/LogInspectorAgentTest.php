<?php

namespace Hakam\AiLogInspector\Test\Unit;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Test\Unit\Tool\MockTools\MockAnalyticsTool;
use Hakam\AiLogInspector\Test\Unit\Tool\MockTools\MockReportingTool;
use Hakam\AiLogInspector\Test\Unit\Tool\MockTools\MockSearchTool;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;

class LogInspectorAgentTest extends TestCase
{
    private LogDocumentPlatformInterface $platform;
    private PlatformInterface $mockPlatform;
    private Model $mockModel;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(LogDocumentPlatformInterface::class);
        $this->mockPlatform = $this->createMock(PlatformInterface::class);
        $this->mockModel = $this->createMock(Model::class);
        $this->mockModel->method('supports')->willReturn(true);

        $this->platform
            ->method('getPlatform')
            ->willReturn($this->mockPlatform);

        $this->platform
            ->method('getModel')
            ->willReturn($this->mockModel);
    }

    public function testConstructWithSingleTool(): void
    {
        $tool = new MockSearchTool();

        $agent = new LogInspectorAgent(
            $this->platform,
            [$tool]
        );

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testConstructWithMultipleTools(): void
    {
        $tools = [
          new MockSearchTool(),
            new MockAnalyticsTool(),
            new MockReportingTool(),
        ];

        $agent = new LogInspectorAgent(
            $this->platform,
            $tools
        );

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testConstructWithIteratorTools(): void
    {
        $tools = new \ArrayIterator([
            new MockAnalyticsTool(),
            new MockReportingTool(),
        ]);

        $agent = new LogInspectorAgent(
            $this->platform,
            $tools
        );

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testConstructWithCustomSystemPrompt(): void
    {
        $customPrompt = 'Custom AI Log Inspector with special instructions';
        $tool = new MockSearchTool();

        $agent = new LogInspectorAgent(
            $this->platform,
            [$tool],
            $customPrompt
        );

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testAskWithDefaultSystemPrompt(): void
    {
        $tool = new MockSearchTool();
        $mockResult = $this->createMock(ResultPromise::class);
        $question = 'Analyze the recent errors';

        $this->mockPlatform
            ->expects($this->once())
            ->method('invoke')
            ->willReturn($mockResult);

        $agent = new LogInspectorAgent(
            $this->platform,
            [$tool]
        );

        $agent->ask($question);
    }

    public function testAskWithCustomSystemPrompt(): void
    {
        $customPrompt = 'Custom AI Log Inspector with advanced analytics capabilities';
        $tool = new MockSearchTool();
        $mockResult = $this->createMock(ResultPromise::class);
        $question = 'What happened yesterday?';

        $this->mockPlatform
            ->expects($this->once())
            ->method('invoke')
            ->willReturn($mockResult);

        $agent = new LogInspectorAgent(
            $this->platform,
            [$tool],
            $customPrompt
        );

        $agent->ask($question);
    }

    public function testAskWithEmptyQuestion(): void
    {
        $tool = new MockSearchTool();
        $mockResult = $this->createMock(ResultPromise::class);

        $this->mockPlatform
            ->expects($this->once())
            ->method('invoke')
            ->willReturn($mockResult);

        $agent = new LogInspectorAgent(
            $this->platform,
            [$tool]
        );

        $result = $agent->ask('');
        $this->assertInstanceOf(ResultInterface::class, $result);
    }

    public function testAskWithComplexQuestion(): void
    {
        $tools = [
            new MockSearchTool(),
            new MockAnalyticsTool(),
        ];
        $mockResult = $this->createMock(ResultPromise::class);
        $complexQuestion = 'Can you analyze the logs between 2024-01-01 and 2024-01-02 for any database connection errors and provide a summary of the root causes with recommendations?';

        $this->mockPlatform
            ->expects($this->once())
            ->method('invoke')
            ->willReturn($mockResult);

        $agent = new LogInspectorAgent(
            $this->platform,
            $tools
        );

        $result = $agent->ask($complexQuestion);
        $this->assertInstanceOf(ResultInterface::class, $result);
    }

    public function testPlatformExceptionPropagation(): void
    {
        $tool = new MockSearchTool();
        $platformException = new \RuntimeException('Platform connection failed');

        $this->mockPlatform
            ->expects($this->once())
            ->method('invoke')
            ->willThrowException($platformException);

        $agent = new LogInspectorAgent(
            $this->platform,
            [$tool]
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Platform connection failed');
        $agent->ask('Test question');
    }

    public function testToolboxCreationWithDifferentIterableTypes(): void
    {
        $testCases = [
            'array' => [
               new MockSearchTool(),
                new MockAnalyticsTool(),
                new MockReportingTool(),
            ],
            'ArrayIterator' => new \ArrayIterator([
                new MockSearchTool(),
                new MockAnalyticsTool(),
                new MockReportingTool(),
            ]),
            'generator' => $this->createToolGenerator(),
        ];

        foreach ($testCases as $type => $tools) {
            $agent = new LogInspectorAgent(
                $this->platform,
                $tools
            );

            $this->assertInstanceOf(LogInspectorAgent::class, $agent, "Failed with $type");
        }
    }

    /**
     * Helper method to create a mock tool with AsTool attribute simulation
     */
    private function createMockTool(string $name = 'mock_tool'): object
    {
        $tool = new class($name) {
            public function __construct(private string $name) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function __invoke(string $input): array
            {
                return ['result' => "Mock response from {$this->name}"];
            }
        };

        return $tool;
    }

    /**
     * Helper method to create a generator of tools
     */
    private function createToolGenerator(): \Generator
    {
        yield $this->createMockTool('generator_tool1');
        yield $this->createMockTool('generator_tool2');
        yield $this->createMockTool('generator_tool3');
    }
}