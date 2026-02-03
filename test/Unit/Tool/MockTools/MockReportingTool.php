<?php

namespace Hakam\AiLogInspector\Test\Unit\Tool\MockTools;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(name: 'mock_reporting_tool', description: 'Mock reporting tool for testing')]
class MockReportingTool
{
    public function __invoke(string $params): array
    {
        return ['report' => "Mock report for: $params"];
    }
}
