<?php

namespace Hakam\AiLogInspector\Loader;

use Symfony\AI\Store\Document\Loader\TextFileLoader;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;

/**
 * CachedLogsDocumentLoader - Loads log files from the cache directory
 * 
 * This loader wraps Symfony AI's TextFileLoader to load log files
 * from a specified cache directory. It can load either a single log
 * file or all log files from the cache.
 */
class CachedLogsDocumentLoader implements LoaderInterface
{
    private TextFileLoader $textFileLoader;
    private string $cacheDir;

    /**
     * @param string $cacheDir The directory where cached log files are stored
     */
    public function __construct(string $cacheDir)
    {
        $this->textFileLoader = new TextFileLoader();
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        
        if (!is_dir($this->cacheDir)) {
            throw new InvalidArgumentException(
                sprintf('Cache directory "%s" does not exist.', $this->cacheDir)
            );
        }
    }

    /**
     * Load log documents from cache
     * 
     * @param string|null $source Optional specific log file name. If null, loads all .log files from cache directory
     * @param array $options Additional options:
     *                       - 'pattern': glob pattern for filtering files (default: '*.log')
     *                       - 'recursive': whether to search subdirectories (default: false)
     * 
     * @return iterable<TextDocument> Iterator of loaded text documents
     * 
     * @throws InvalidArgumentException If source file doesn't exist
     * @throws RuntimeException If unable to read files
     */
    public function load(?string $source = null, array $options = []): iterable
    {
        if ($source !== null) {
            yield from $this->loadSingleFile($source);
        } else {
            yield from $this->loadAllFiles($options);
        }
    }

    /**
     * Load a single log file from cache
     * 
     * @param string $filename The log file name (relative to cache directory)
     * @return iterable<TextDocument>
     */
    private function loadSingleFile(string $filename): iterable
    {
        $filePath = $this->resolveCachePath($filename);
        
        if (!is_file($filePath)) {
            throw new InvalidArgumentException(
                sprintf('Log file "%s" does not exist in cache directory.', $filename)
            );
        }
        
        yield from $this->textFileLoader->load($filePath);
    }

    /**
     * Load all log files from the cache directory
     * 
     * @param array $options Loading options
     * @return iterable<TextDocument>
     */
    private function loadAllFiles(array $options): iterable
    {
        $pattern = $options['pattern'] ?? '*.log';
        $recursive = $options['recursive'] ?? false;
        
        $searchPattern = $recursive 
            ? $this->cacheDir . DIRECTORY_SEPARATOR . '**' . DIRECTORY_SEPARATOR . $pattern
            : $this->cacheDir . DIRECTORY_SEPARATOR . $pattern;
        
        $files = glob($searchPattern, $recursive ? GLOB_BRACE : 0);
        
        if ($files === false) {
            throw new RuntimeException(
                sprintf('Failed to scan cache directory "%s" for log files.', $this->cacheDir)
            );
        }
        
        if (empty($files)) {
            throw new RuntimeException(
                sprintf('No log files found in cache directory "%s" matching pattern "%s".', $this->cacheDir, $pattern)
            );
        }
        
        foreach ($files as $filePath) {
            if (is_file($filePath)) {
                yield from $this->textFileLoader->load($filePath);
            }
        }
    }

    /**
     * Resolve a filename to its full cache path
     * 
     * @param string $filename The log file name
     * @return string The full file path
     */
    private function resolveCachePath(string $filename): string
    {
        // If the filename is already an absolute path within cache dir, use it
        if (str_starts_with($filename, $this->cacheDir)) {
            return $filename;
        }
        
        // Otherwise, treat it as relative to cache directory
        return $this->cacheDir . DIRECTORY_SEPARATOR . ltrim($filename, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the cache directory path
     * 
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }
}
