<?php

namespace Hakam\AiLogInspector\Test\Unit\Tool\MockTools;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(name: 'mock_search_tool', description: 'Mock search tool for testing')]
class MockSearchTool
{
    public function __invoke(string $query): array
    {
        return [
            'success' => true,
            'reason' => "Mock analysis for: $query",
            'evidence_logs' => [
                [
                    'id' => 'mock_log_1',
                    'content' => "Mock log content for query: $query",
                    'level' => 'info',
                    'source' => 'test'
                ]
            ]
        ];
    }
}