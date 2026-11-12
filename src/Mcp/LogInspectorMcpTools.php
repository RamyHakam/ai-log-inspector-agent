<?php

namespace Hakam\AiLogInspector\Mcp;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Document\LogDocument;
use Hakam\AiLogInspector\Indexer\LogDocumentIndexer;
use Hakam\AiLogInspector\Parser\LogLineParser;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Tool\RequestContextTool;

/**
 * MCP handlers that expose the log inspector to any MCP client
 * (Claude Code, Cursor, the MCP Inspector, ...).
 *
 * The low-level tools (log_search, request_context) hand the client the raw
 * evidence so its own model can reason about it. ask_logs runs the full Symfony AI
 * agent server-side, so even a small client gets a complete investigation.
 */
final class LogInspectorMcpTools
{
    /** @var array<string, int> indexed source => number of log lines */
    private array $sources = [];

    private int $indexedLines = 0;

    public function __construct(
        private readonly LogSearchTool $logSearchTool,
        private readonly RequestContextTool $requestContextTool,
        private readonly LogInspectorAgent $agent,
        private readonly LogDocumentIndexer $indexer,
        private readonly int $maxLinesPerFile = 5000,
        private readonly LogLineParser $parser = new LogLineParser(),
    ) {
    }

    /**
     * Semantic search over the indexed logs. Returns the matching log entries as evidence with an AI explanation.
     *
     * @param string $query What to look for, in plain language (e.g. "payment gateway timeouts", "failed logins")
     *
     * @return array<string, mixed>
     */
    public function logSearch(string $query): array
    {
        return ($this->logSearchTool)($query);
    }

    /**
     * Reconstruct the full lifecycle of one request: every log line that mentions the identifier, in chronological order.
     *
     * @param string $identifier A request_id, trace_id or session_id (e.g. "req_12345", "trace-abc-123")
     *
     * @return array<string, mixed>
     */
    public function requestContext(string $identifier): array
    {
        return ($this->requestContextTool)($identifier);
    }

    /**
     * Ask the log inspector agent a question. It searches and traces the logs itself and answers with summary, root cause, evidence and fixes.
     *
     * @param string $question A question about the logs (e.g. "Why did checkout fail at 14:23?")
     *
     * @return array{answer: string}
     */
    public function askLogs(string $question): array
    {
        $result = $this->agent->ask($question);
        $content = $result->getContent();

        return ['answer' => \is_string($content) ? $content : json_encode($content, \JSON_PRETTY_PRINT)];
    }

    /**
     * Index a log file, or every *.log file in a directory, so it can be searched.
     *
     * @param string $path Absolute path to a log file or a directory of *.log files
     *
     * @return array<string, mixed>
     */
    public function indexLogs(string $path): array
    {
        $files = $this->resolveLogFiles($path);
        if ([] === $files) {
            return ['success' => false, 'message' => \sprintf('No readable log files found at "%s".', $path)];
        }

        $indexed = [];
        foreach ($files as $file) {
            $indexed[$file] = $this->indexFile($file);
        }

        return [
            'success' => true,
            'indexed_files' => $indexed,
            'total_indexed_lines' => $this->indexedLines,
        ];
    }

    /**
     * Which log sources are indexed and how many lines each contributed.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [
            'sources' => $this->sources,
            'total_indexed_lines' => $this->indexedLines,
        ];
    }

    /**
     * Guide the client model through a structured incident investigation.
     *
     * @param string $symptom What users or alerts are reporting (e.g. "checkout returns 500 since 14:20")
     *
     * @return list<array{role: string, content: string}>
     */
    public function investigateIncident(string $symptom): array
    {
        return [[
            'role' => 'user',
            'content' => <<<PROMPT
                Investigate this production incident using the log inspector tools: {$symptom}

                1. Call `log_search` with the symptom to find the first errors and their timestamps.
                2. For every request_id, trace_id or session_id in the evidence, call `request_context` to rebuild what happened.
                3. Search for upstream causes: database, cache, external APIs, deployments around the same time.
                4. Answer with: Summary, Root cause (cite log ids), Timeline, Impact, Immediate fix, Prevention, Confidence.
                Base every claim on log evidence. Say so when the logs are not enough.
                PROMPT,
        ]];
    }

    /**
     * Index one file line by line. Each non-empty line becomes one log document.
     */
    public function indexFile(string $file): int
    {
        $documents = [];
        $handle = fopen($file, 'r');
        if (false === $handle) {
            return 0;
        }

        $lineNumber = 0;
        while (false !== ($line = fgets($handle)) && \count($documents) < $this->maxLinesPerFile) {
            ++$lineNumber;
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $metadata = $this->parser->parse($line, basename($file), $lineNumber);
            $documents[] = new LogDocument($line, $metadata);
        }
        fclose($handle);

        if ([] !== $documents) {
            $this->indexer->indexLogDocuments($documents);
        }

        $count = \count($documents);
        $this->sources[$file] = ($this->sources[$file] ?? 0) + $count;
        $this->indexedLines += $count;

        return $count;
    }

    /**
     * @return list<string>
     */
    private function resolveLogFiles(string $path): array
    {
        if (is_file($path) && is_readable($path)) {
            return [$path];
        }

        if (is_dir($path)) {
            $files = glob(rtrim($path, '/').'/*.log') ?: [];

            return array_values(array_filter($files, 'is_readable'));
        }

        return [];
    }
}
