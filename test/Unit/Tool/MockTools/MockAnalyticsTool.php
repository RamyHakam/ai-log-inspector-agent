<?php

namespace Hakam\AiLogInspector\Test\Unit\Tool\MockTools;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(name: 'mock_analytics_tool', description: 'Mock analytics tool for testing')]
class MockAnalyticsTool
{
    public function __invoke(string $data): array
    {
        return ['analytics' => "Mock analytics for: $data"];
    }
}
