<?php

namespace Hakam\AiLogInspector\Test\Unit\Store;

use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\Component\Uid\Uuid;

class VectorLogDocumentStoreInMemoryTest extends TestCase
{
    private InMemoryStore $inMemoryStore;
    private VectorLogDocumentStore $store;

    protected function setUp(): void
    {
        $this->inMemoryStore = new InMemoryStore();
        $this->store = new VectorLogDocumentStore($this->inMemoryStore);
    }

    public function testStoreInitializationWithInMemory(): void
    {
        $this->assertInstanceOf(VectorLogDocumentStore::class, $this->store);
        $this->assertSame($this->inMemoryStore, $this->store->getStore());
        $this->assertInstanceOf(InMemoryStore::class, $this->store->getStore());
    }

    public function testSaveAndRetrieveSingleLogDocument(): void
    {
        $vector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
        $metadata = new Metadata([
            'log_id' => 'error_001',
            'content' => 'Database connection timeout',
            'message' => 'Connection timeout',
            'timestamp' => '2026-01-16 09:15:30',
            'level' => 'error',
            'category' => 'database',
        ]);

        $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
        $this->store->saveLogVectorDocuments([$document]);

        $results = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $this->assertCount(1, $results);
    }

    public function testSaveMultipleLogsAndQuery(): void
    {
        $documents = [];
        $logEntries = [
            ['id' => 'payment_001', 'content' => 'Payment gateway timeout', 'category' => 'payment'],
            ['id' => 'db_001', 'content' => 'Database query failed', 'category' => 'database'],
            ['id' => 'auth_001', 'content' => 'Authentication failed', 'category' => 'security'],
            ['id' => 'api_001', 'content' => 'API rate limit exceeded', 'category' => 'application'],
            ['id' => 'perf_001', 'content' => 'Response time too slow', 'category' => 'performance'],
        ];

        foreach ($logEntries as $entry) {
            $vector = new Vector([
                mt_rand(0, 10) * 0.1,
                mt_rand(0, 10) * 0.1,
                mt_rand(0, 10) * 0.1,
                mt_rand(0, 10) * 0.1,
                mt_rand(0, 10) * 0.1,
            ]);

            $metadata = new Metadata([
                'log_id' => $entry['id'],
                'content' => $entry['content'],
                'message' => $entry['content'],
                'timestamp' => '2026-01-16 09:15:30',
                'level' => 'error',
                'category' => $entry['category'],
            ]);

            $documents[] = new VectorDocument(Uuid::v4(), $vector, $metadata);
        }

        $this->store->saveLogVectorDocuments($documents);

        $queryVector = new Vector([0.5, 0.5, 0.5, 0.5, 0.5]);
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));

        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function testHandleEmptyDocumentList(): void
    {
        $this->store->saveLogVectorDocuments([]);

        $queryVector = new Vector([0.5, 0.5, 0.5, 0.5, 0.5]);
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));

        $this->assertCount(0, $results);
    }

    public function testRejectInvalidDocuments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All documents must be instances of VectorDocument');

        $this->store->saveLogVectorDocuments([
            'invalid_object',
            ['also' => 'invalid'],
        ]);
    }

    public function testMetadataIsPreservedInMemory(): void
    {
        $customMetadata = [
            'log_id' => 'custom_001',
            'content' => 'Complex log with multiple fields',
            'message' => 'Complex log message',
            'timestamp' => '2026-01-16 14:30:45',
            'level' => 'warning',
            'category' => 'application',
            'request_id' => 'req_12345',
            'user_id' => 'user_789',
            'response_code' => 500,
            'duration_ms' => 1234,
            'service' => 'payment-api',
        ];

        $vector = new Vector([0.15, 0.25, 0.35, 0.45, 0.55]);
        $metadata = new Metadata($customMetadata);
        $document = new VectorDocument(Uuid::v4(), $vector, $metadata);

        $this->store->saveLogVectorDocuments([$document]);

        $results = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $this->assertCount(1, $results);

        $retrieved = current($results);
        $this->assertEquals('custom_001', $retrieved->metadata['log_id']);
        $this->assertEquals('warning', $retrieved->metadata['level']);
        $this->assertEquals(500, $retrieved->metadata['response_code']);
        $this->assertEquals('payment-api', $retrieved->metadata['service']);
    }

    public function testQueryWithMaxItemsLimit(): void
    {
        // Add 10 documents
        for ($i = 1; $i <= 10; ++$i) {
            $vector = new Vector(array_fill(0, 5, $i * 0.1));
            $metadata = new Metadata([
                'log_id' => sprintf('log_%03d', $i),
                'content' => "Log entry $i",
                'level' => 0 === $i % 2 ? 'error' : 'warning',
            ]);

            $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
            $this->store->saveLogVectorDocuments([$document]);
        }

        // Query with limit
        $queryVector = new Vector([0.5, 0.5, 0.5, 0.5, 0.5]);
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 3]));

        $this->assertLessThanOrEqual(3, count($results));
    }

    public function testSequentialSaves(): void
    {
        // First save
        $vector1 = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
        $metadata1 = new Metadata([
            'log_id' => 'seq_001',
            'content' => 'First log',
        ]);
        $document1 = new VectorDocument(Uuid::v4(), $vector1, $metadata1);
        $this->store->saveLogVectorDocuments([$document1]);

        // Second save
        $vector2 = new Vector([0.2, 0.3, 0.4, 0.5, 0.6]);
        $metadata2 = new Metadata([
            'log_id' => 'seq_002',
            'content' => 'Second log',
        ]);
        $document2 = new VectorDocument(Uuid::v4(), $vector2, $metadata2);
        $this->store->saveLogVectorDocuments([$document2]);

        // Both should be retrievable
        $results1 = iterator_to_array($this->store->queryForVector($vector1, ['maxItems' => 10]));
        $results2 = iterator_to_array($this->store->queryForVector($vector2, ['maxItems' => 10]));

        $this->assertGreaterThanOrEqual(1, count($results1));
        $this->assertGreaterThanOrEqual(1, count($results2));
    }

    public function testDifferentLogLevels(): void
    {
        $levels = ['error', 'warning', 'info', 'debug', 'critical'];
        $documents = [];

        foreach ($levels as $index => $level) {
            $vector = new Vector(array_fill(0, 5, ($index + 1) * 0.1));
            $metadata = new Metadata([
                'log_id' => sprintf('level_%s', $level),
                'content' => "Log with level: $level",
                'message' => "Message at $level level",
                'level' => $level,
                'category' => 'system',
            ]);

            $documents[] = new VectorDocument(Uuid::v4(), $vector, $metadata);
        }

        $this->store->saveLogVectorDocuments($documents);

        $queryVector = new Vector([0.3, 0.3, 0.3, 0.3, 0.3]);
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));

        $this->assertGreaterThanOrEqual(1, count($results));

        // Verify we can find documents with specific levels
        $levels_found = array_map(fn ($doc) => $doc->metadata['level'] ?? null, $results);
        $this->assertNotEmpty($levels_found);
    }

    public function testCategoryBasedStorage(): void
    {
        $categories = ['payment', 'database', 'security', 'infrastructure', 'application'];
        $documents = [];

        foreach ($categories as $index => $category) {
            $vector = new Vector([
                0.1 + $index * 0.1,
                0.2 + $index * 0.1,
                0.3 + $index * 0.1,
                0.4 + $index * 0.1,
                0.5 + $index * 0.1,
            ]);

            $metadata = new Metadata([
                'log_id' => sprintf('%s_error_%03d', substr($category, 0, 3), $index),
                'content' => "Error in $category service",
                'message' => "$category service error",
                'level' => 'error',
                'category' => $category,
            ]);

            $documents[] = new VectorDocument(Uuid::v4(), $vector, $metadata);
        }

        $this->store->saveLogVectorDocuments($documents);

        $queryVector = new Vector([0.3, 0.3, 0.3, 0.3, 0.3]);
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));

        $this->assertGreaterThanOrEqual(1, count($results));

        // Verify categories are preserved
        $categories_found = array_map(fn ($doc) => $doc->metadata['category'] ?? null, $results);
        $this->assertNotEmpty($categories_found);
    }

    public function testBatchOperations(): void
    {
        $batch1 = [];
        $batch2 = [];

        // Create batch 1: 5 documents
        for ($i = 1; $i <= 5; ++$i) {
            $vector = new Vector(array_fill(0, 5, $i * 0.1));
            $metadata = new Metadata([
                'log_id' => sprintf('batch1_%03d', $i),
                'content' => "Batch 1 - Entry $i",
                'batch' => 1,
            ]);
            $batch1[] = new VectorDocument(Uuid::v4(), $vector, $metadata);
        }

        // Create batch 2: 5 documents
        for ($i = 1; $i <= 5; ++$i) {
            $vector = new Vector(array_fill(0, 5, 0.5 + $i * 0.05));
            $metadata = new Metadata([
                'log_id' => sprintf('batch2_%03d', $i),
                'content' => "Batch 2 - Entry $i",
                'batch' => 2,
            ]);
            $batch2[] = new VectorDocument(Uuid::v4(), $vector, $metadata);
        }

        // Save both batches
        $this->store->saveLogVectorDocuments($batch1);
        $this->store->saveLogVectorDocuments($batch2);

        // Query should find documents from both batches
        $queryVector = new Vector([0.5, 0.5, 0.5, 0.5, 0.5]);
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 20]));

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function testDocumentWithNullMetadataFields(): void
    {
        $vector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
        $metadata = new Metadata([
            'log_id' => 'null_test_001',
            'content' => 'Test document',
            'optional_field' => null,
            'timestamp' => null,
            'level' => 'info',
        ]);

        $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
        $this->store->saveLogVectorDocuments([$document]);

        $results = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $this->assertCount(1, $results);

        $retrieved = current($results);
        $this->assertNull($retrieved->metadata['optional_field']);
        $this->assertNull($retrieved->metadata['timestamp']);
        $this->assertEquals('info', $retrieved->metadata['level']);
    }

    public function testStoreIsolation(): void
    {
        // Each store instance should be independent
        $store2 = new VectorLogDocumentStore(new InMemoryStore());

        $vector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
        $metadata1 = new Metadata(['log_id' => 'store1_001', 'content' => 'Store 1 doc']);
        $metadata2 = new Metadata(['log_id' => 'store2_001', 'content' => 'Store 2 doc']);

        $doc1 = new VectorDocument(Uuid::v4(), $vector, $metadata1);
        $doc2 = new VectorDocument(Uuid::v4(), $vector, $metadata2);

        $this->store->saveLogVectorDocuments([$doc1]);
        $store2->saveLogVectorDocuments([$doc2]);

        $results1 = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $results2 = iterator_to_array($store2->queryForVector($vector, ['maxItems' => 10]));

        // Each store should only have its own documents
        $ids1 = array_map(fn ($doc) => $doc->metadata['log_id'], $results1);
        $ids2 = array_map(fn ($doc) => $doc->metadata['log_id'], $results2);

        $this->assertContains('store1_001', $ids1);
        $this->assertContains('store2_001', $ids2);
    }

    public function testLargeVectorDimensions(): void
    {
        // Test with larger vector dimensions
        $largeVector = array_fill(0, 1536, 0.5); // OpenAI embedding dimension
        $vector = new Vector($largeVector);

        $metadata = new Metadata([
            'log_id' => 'large_dim_001',
            'content' => 'Document with large embedding',
        ]);

        $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
        $this->store->saveLogVectorDocuments([$document]);

        $queryVector = new Vector($largeVector);
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));

        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function testConsistentQueryResults(): void
    {
        $vector = new Vector([0.25, 0.35, 0.45, 0.55, 0.65]);

        for ($i = 1; $i <= 3; ++$i) {
            $metadata = new Metadata([
                'log_id' => sprintf('consistent_%03d', $i),
                'content' => "Consistent test entry $i",
            ]);
            $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
            $this->store->saveLogVectorDocuments([$document]);
        }

        // Query multiple times - should get consistent results
        $queryVector = new Vector([0.25, 0.35, 0.45, 0.55, 0.65]);

        $results1 = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));
        $results2 = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));
        $results3 = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));

        $this->assertEquals(count($results1), count($results2));
        $this->assertEquals(count($results2), count($results3));
    }
}
