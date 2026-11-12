<?php

namespace Hakam\AiLogInspector\Test\Unit\Parser;

use Hakam\AiLogInspector\Parser\LogLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogLineParserTest extends TestCase
{
    public function testParsesMonologLineWithContextAndIdentifiers(): void
    {
        $line = '[2026-11-12 14:20:16] request.ERROR: Checkout failed {"status":500,"request_id":"req_c1042","session_id":"sess_4477"} []';

        $metadata = (new LogLineParser())->parse($line, 'checkout-api.log', 8);

        $this->assertSame('checkout_api_0008', $metadata['log_id']);
        $this->assertSame($line, $metadata['content']);
        $this->assertSame('Checkout failed', $metadata['message']);
        $this->assertSame('error', $metadata['level']);
        $this->assertSame('request', $metadata['channel']);
        $this->assertSame('2026-11-12T14:20:16+00:00', $metadata['timestamp']);
        $this->assertSame('req_c1042', $metadata['request_id']);
        $this->assertSame('sess_4477', $metadata['session_id']);
        $this->assertSame('checkout-api.log', $metadata['source']);
    }

    public function testParsesLevelOnlyLineAndNormalizesWarn(): void
    {
        $line = '[2025-01-15 14:15:23] WARN: Circuit breaker opened {"service":"user-service","trace_id":"trace_001"}';

        $metadata = (new LogLineParser())->parse($line, 'gateway.log', 1);

        $this->assertSame('warning', $metadata['level']);
        $this->assertSame('trace_001', $metadata['trace_id']);
        $this->assertContains('user-service', $metadata['tags']);
    }

    public function testParsesPhpErrorLogLine(): void
    {
        $line = '[06-Feb-2026 14:22:31 UTC] PHP Fatal error:  Uncaught Error: Call to undefined method Foo::bar()';

        $metadata = (new LogLineParser())->parse($line, 'error.log', 3);

        $this->assertSame('critical', $metadata['level']);
        $this->assertSame('php', $metadata['channel']);
        $this->assertSame('2026-02-06T14:22:31+00:00', $metadata['timestamp']);
    }

    #[DataProvider('unstructuredLines')]
    public function testKeepsUnstructuredLinesSearchable(string $line): void
    {
        $metadata = (new LogLineParser())->parse($line, 'raw.log', 1);

        $this->assertSame($line, $metadata['content']);
        $this->assertSame('info', $metadata['level']);
        $this->assertNull($metadata['timestamp']);
    }

    public static function unstructuredLines(): iterable
    {
        yield 'stack trace line' => ['#0 /var/www/src/Kernel.php(42): App\Kernel->boot()'];
        yield 'broken json context' => ['something happened {"unterminated": true'];
    }
}
