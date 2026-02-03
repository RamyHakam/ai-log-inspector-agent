<?php

namespace Hakam\AiLogInspector\Test\Unit\Document;

use Hakam\AiLogInspector\Loader\CachedLogsDocumentLoader;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;

class CachedLogsDocumentLoaderTest extends TestCase
{
    private string $testCacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary cache directory for tests
        $this->testCacheDir = sys_get_temp_dir() . '/cached-logs-loader-test-' . uniqid();
        mkdir($this->testCacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up test cache directory
        $this->cleanupTestDirectory();
    }

    public function testConstructorWithValidDirectory(): void
    {
        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        
        $this->assertInstanceOf(CachedLogsDocumentLoader::class, $loader);
        $this->assertEquals($this->testCacheDir, $loader->getCacheDir());
    }

    public function testConstructorThrowsExceptionForNonExistentDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache directory');
        $this->expectExceptionMessage('does not exist');

        new CachedLogsDocumentLoader('/non/existent/directory/path');
    }

    public function testConstructorNormalizesDirectoryPath(): void
    {
        // Create loader with trailing slash
        $loader = new CachedLogsDocumentLoader($this->testCacheDir . '/');
        
        // Should remove trailing slash
        $this->assertEquals($this->testCacheDir, $loader->getCacheDir());
    }

    public function testLoadSingleLogFile(): void
    {
        // Create a test log file
        $logFile = 'test-app.log';
        $logContent = "[2025-09-09 10:00:00] app.ERROR: Test error message\n";
        $logContent .= "[2025-09-09 10:01:00] app.WARNING: Test warning message\n";

        file_put_contents($this->testCacheDir . '/' . $logFile, $logContent);

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load($logFile), false);

        $this->assertCount(1, $documents);
        $this->assertInstanceOf(TextDocument::class, $documents[0]);
        $this->assertStringContainsString('Test error message', $documents[0]->getContent());
        $this->assertStringContainsString('Test warning message', $documents[0]->getContent());
    }

    public function testLoadNonExistentFileThrowsException(): void
    {
        $loader = new CachedLogsDocumentLoader($this->testCacheDir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        iterator_to_array($loader->load('non-existent-file.log'), false);
    }

    public function testLoadAllLogFiles(): void
    {
        // Create multiple log files
        file_put_contents($this->testCacheDir . '/app1.log', 'Log content 1');
        file_put_contents($this->testCacheDir . '/app2.log', 'Log content 2');
        file_put_contents($this->testCacheDir . '/app3.log', 'Log content 3');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load(), false);

        // Should load all 3 log files
        $this->assertCount(3, $documents);
        
        foreach ($documents as $document) {
            $this->assertInstanceOf(TextDocument::class, $document);
        }
    }

    public function testLoadWithNoLogFilesThrowsException(): void
    {
        $loader = new CachedLogsDocumentLoader($this->testCacheDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No log files found');

        iterator_to_array($loader->load(), false);
    }

    public function testLoadIgnoresNonLogFiles(): void
    {
        // Create log files and non-log files
        file_put_contents($this->testCacheDir . '/app.log', 'Log content');
        file_put_contents($this->testCacheDir . '/readme.txt', 'Not a log');
        file_put_contents($this->testCacheDir . '/config.json', '{}');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load(), false);

        // Should only load .log files
        $this->assertCount(1, $documents);
    }

    public function testLoadWithAbsolutePath(): void
    {
        $logFile = 'absolute-path-test.log';
        $absolutePath = $this->testCacheDir . '/' . $logFile;
        file_put_contents($absolutePath, 'Absolute path test content');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        
        // Should work with absolute path
        $documents = iterator_to_array($loader->load($absolutePath), false);

        $this->assertCount(1, $documents);
        $this->assertStringContainsString('Absolute path test content', $documents[0]->getContent());
    }

    public function testLoadWithRelativePath(): void
    {
        $logFile = 'relative-path-test.log';
        file_put_contents($this->testCacheDir . '/' . $logFile, 'Relative path test content');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        
        // Should work with relative path
        $documents = iterator_to_array($loader->load($logFile), false);

        $this->assertCount(1, $documents);
        $this->assertStringContainsString('Relative path test content', $documents[0]->getContent());
    }

    public function testDocumentMetadataContainsSource(): void
    {
        $logFile = 'metadata-test.log';
        file_put_contents($this->testCacheDir . '/' . $logFile, 'Test content');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load($logFile), false);

        $this->assertCount(1, $documents);

        $metadata = $documents[0]->getMetadata();
        // Metadata has a getSource() method for the source path
        $this->assertTrue($metadata->hasSource());
        $this->assertStringContainsString($logFile, $metadata->getSource());
    }

    public function testDocumentContentIsTrimmed(): void
    {
        $logFile = 'trim-test.log';
        $content = "  \n  Log content with whitespace  \n  \n";
        file_put_contents($this->testCacheDir . '/' . $logFile, $content);

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load($logFile), false);

        $this->assertCount(1, $documents);
        $this->assertEquals('Log content with whitespace', $documents[0]->getContent());
    }

    public function testMultipleLogFilesWithDifferentContent(): void
    {
        // Create log files with different content
        file_put_contents($this->testCacheDir . '/errors.log', 'Error log content');
        file_put_contents($this->testCacheDir . '/warnings.log', 'Warning log content');
        file_put_contents($this->testCacheDir . '/info.log', 'Info log content');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load(), false);

        $this->assertCount(3, $documents);

        $contents = array_map(fn($doc) => $doc->getContent(), $documents);
        
        $this->assertContains('Error log content', $contents);
        $this->assertContains('Warning log content', $contents);
        $this->assertContains('Info log content', $contents);
    }

    public function testGetCacheDirReturnsCorrectPath(): void
    {
        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        
        $this->assertEquals($this->testCacheDir, $loader->getCacheDir());
    }

    public function testLoadEmptyLogFileThrowsException(): void
    {
        $logFile = 'empty.log';
        file_put_contents($this->testCacheDir . '/' . $logFile, '');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('content shall not be an empty string');

        iterator_to_array($loader->load($logFile), false);
    }

    public function testLoadLargeLogFile(): void
    {
        $logFile = 'large.log';
        
        // Create a large log file (1000 lines)
        $content = '';
        for ($i = 1; $i <= 1000; $i++) {
            $content .= "[2025-09-09 10:00:00] app.INFO: Log entry number {$i}\n";
        }
        file_put_contents($this->testCacheDir . '/' . $logFile, $content);

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load($logFile), false);

        $this->assertCount(1, $documents);
        $this->assertGreaterThan(10000, strlen($documents[0]->getContent()));
        $this->assertStringContainsString('Log entry number 1', $documents[0]->getContent());
        $this->assertStringContainsString('Log entry number 1000', $documents[0]->getContent());
    }

    public function testLoadWithSpecialCharactersInFilename(): void
    {
        $logFile = 'app-2025-01-01_special.log';
        file_put_contents($this->testCacheDir . '/' . $logFile, 'Special filename content');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load($logFile), false);

        $this->assertCount(1, $documents);
        $this->assertEquals('Special filename content', $documents[0]->getContent());
    }

    public function testLoadGeneratesUniqueDocumentIds(): void
    {
        file_put_contents($this->testCacheDir . '/log1.log', 'Content 1');
        file_put_contents($this->testCacheDir . '/log2.log', 'Content 2');

        $loader = new CachedLogsDocumentLoader($this->testCacheDir);
        $documents = iterator_to_array($loader->load(), false);

        $this->assertCount(2, $documents);

        $ids = array_map(fn($doc) => $doc->getId()->toRfc4122(), $documents);
        
        // IDs should be unique
        $this->assertCount(2, array_unique($ids));
    }

    /**
     * Clean up test directory
     */
    private function cleanupTestDirectory(): void
    {
        if (!is_dir($this->testCacheDir)) {
            return;
        }

        $files = glob($this->testCacheDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        rmdir($this->testCacheDir);
    }
}
