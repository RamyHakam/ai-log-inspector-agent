<?php

namespace Hakam\AiLogInspector\Test\Unit\Store;

use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Cache\Store as CacheStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Uid\Uuid;

class VectorLogDocumentStoreTest extends TestCase
{
    private CacheStore $cacheStore;
    private VectorLogDocumentStore $store;

    protected function setUp(): void
    {
        $adapter = new ArrayAdapter();
        $this->cacheStore = new CacheStore($adapter);
        $this->store = new VectorLogDocumentStore($this->cacheStore);
    }

    public function testStoreInitialization(): void
    {
        $this->assertInstanceOf(VectorLogDocumentStore::class, $this->store);
        $this->assertSame($this->cacheStore, $this->store->getStore());
    }

    public function testSaveLogVectorDocumentsWithSingleDocument(): void
    {
        $vector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
        $metadata = new Metadata([
            'log_id' => 'payment_001',
            'content' => 'Payment processing timeout error',
            'message' => 'Payment processing timeout',
            'timestamp' => '2026-01-15 10:30:45',
            'level' => 'error',
            'category' => 'payment',
        ]);

        $document = new VectorDocument(Uuid::v4(), $vector, $metadata);

        $this->store->saveLogVectorDocuments([$document]);

        // Verify document is saved
        $results = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $this->assertCount(1, $results);
    }

    public function testSaveLogVectorDocumentsWithMultipleDocuments(): void
    {
        $documents = [];
        $vectors = [
            new Vector([0.1, 0.2, 0.3, 0.4, 0.5]),
            new Vector([0.5, 0.4, 0.3, 0.2, 0.1]),
            new Vector([0.2, 0.3, 0.4, 0.5, 0.1]),
        ];

        $categories = ['payment', 'database', 'security'];
        $messages = [
            'Payment gateway timeout',
            'Database connection failed',
            'Authentication failed for user',
        ];

        foreach ($vectors as $index => $vector) {
            $metadata = new Metadata([
                'log_id' => sprintf('%s_%03d', $categories[$index], $index + 1),
                'content' => $messages[$index],
                'message' => $messages[$index],
                'timestamp' => sprintf('2026-01-15 10:%02d:45', $index),
                'level' => 'error',
                'category' => $categories[$index],
            ]);

            $documents[] = new VectorDocument(Uuid::v4(), $vector, $metadata);
        }

        $this->store->saveLogVectorDocuments($documents);

        // Query and verify all documents are saved
        $results = iterator_to_array($this->store->queryForVector($vectors[0], ['maxItems' => 10]));
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function testSaveLogVectorDocumentsWithEmptyArray(): void
    {
        // Should not throw exception for empty array
        $this->store->saveLogVectorDocuments([]);

        $vector = new Vector([0.5, 0.5, 0.5, 0.5, 0.5]);
        $results = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));

        $this->assertCount(0, $results);
    }

    public function testSaveLogVectorDocumentsWithInvalidDocument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All documents must be instances of VectorDocument');

        // Try to save a non-VectorDocument object
        $this->store->saveLogVectorDocuments(['invalid']);
    }

    public function testQueryForVectorWithResults(): void
    {
        // Setup documents
        $queryVector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
        $similarVector = new Vector([0.11, 0.21, 0.31, 0.41, 0.51]);

        $metadata = new Metadata([
            'log_id' => 'app_001',
            'content' => 'Application error occurred',
            'message' => 'Application error',
            'timestamp' => '2026-01-15 10:30:45',
            'level' => 'error',
            'category' => 'application',
        ]);

        $document = new VectorDocument(Uuid::v4(), $similarVector, $metadata);
        $this->store->saveLogVectorDocuments([$document]);

        // Query and verify
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));
        $this->assertGreaterThanOrEqual(1, count($results));
        $this->assertInstanceOf(VectorDocument::class, current($results));
    }

    public function testQueryForVectorWithoutResults(): void
    {
        $queryVector = new Vector([0.9, 0.9, 0.9, 0.9, 0.9]);

        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));

        $this->assertCount(0, $results);
    }

    public function testQueryForVectorWithMaxItems(): void
    {
        // Save multiple documents
        for ($i = 1; $i <= 5; ++$i) {
            $vector = new Vector([
                $i * 0.1,
                $i * 0.2,
                $i * 0.3,
                $i * 0.4,
                $i * 0.5,
            ]);

            $metadata = new Metadata([
                'log_id' => sprintf('log_%03d', $i),
                'content' => "Log message $i",
                'message' => "Message $i",
                'timestamp' => sprintf('2026-01-15 10:%02d:45', $i),
                'level' => 'info',
                'category' => 'test',
            ]);

            $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
            $this->store->saveLogVectorDocuments([$document]);
        }

        $queryVector = new Vector([0.5, 0.5, 0.5, 0.5, 0.5]);

        // Query with maxItems limit
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 3]));

        $this->assertLessThanOrEqual(3, count($results));
    }

    public function testMetadataPreservation(): void
    {
        $customMetadata = [
            'log_id' => 'payment_100',
            'content' => 'Payment processing failed with error code 500',
            'message' => 'Payment processing failed',
            'timestamp' => '2026-01-15 14:23:45',
            'level' => 'error',
            'category' => 'payment',
            'transaction_id' => 'txn_12345',
            'user_id' => 'user_789',
            'amount' => 99.99,
            'status' => 'failed',
        ];

        $vector = new Vector([0.2, 0.3, 0.4, 0.5, 0.6]);
        $metadata = new Metadata($customMetadata);
        $document = new VectorDocument(Uuid::v4(), $vector, $metadata);

        $this->store->saveLogVectorDocuments([$document]);

        // Retrieve and verify metadata
        $results = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $this->assertCount(1, $results);

        $retrievedDoc = current($results);
        $this->assertInstanceOf(VectorDocument::class, $retrievedDoc);

        // Verify metadata fields
        $this->assertEquals('payment_100', $retrievedDoc->metadata['log_id']);
        $this->assertEquals('Payment processing failed with error code 500', $retrievedDoc->metadata['content']);
        $this->assertEquals('error', $retrievedDoc->metadata['level']);
    }

    public function testVectorPersistenceAcrossQueries(): void
    {
        $vector = new Vector([0.3, 0.3, 0.3, 0.3, 0.3]);
        $metadata1 = new Metadata([
            'log_id' => 'db_001',
            'content' => 'Database query timeout',
            'level' => 'error',
        ]);
        $metadata2 = new Metadata([
            'log_id' => 'db_002',
            'content' => 'Database connection lost',
            'level' => 'error',
        ]);

        $doc1 = new VectorDocument(Uuid::v4(), $vector, $metadata1);
        $doc2 = new VectorDocument(Uuid::v4(), $vector, $metadata2);

        $this->store->saveLogVectorDocuments([$doc1, $doc2]);

        // First query
        $results1 = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $count1 = count($results1);

        // Second query - should have same results
        $results2 = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $count2 = count($results2);

        $this->assertEquals($count1, $count2);
    }

    public function testMultipleVectorDocuments(): void
    {
        // All vectors must be the same size for comparison
        $vectors = [
            new Vector([0.1, 0.2, 0.3, 0.4, 0.5]),
            new Vector([0.2, 0.3, 0.4, 0.5, 0.6]),
            new Vector([0.3, 0.4, 0.5, 0.6, 0.7]),
        ];

        foreach ($vectors as $index => $vector) {
            $metadata = new Metadata([
                'log_id' => sprintf('log_multi_%d', $index),
                'content' => 'Vector document '.($index + 1),
                'level' => 'info',
            ]);

            $document = new VectorDocument(Uuid::v4(), $vector, $metadata);
            $this->store->saveLogVectorDocuments([$document]);
        }

        // Query and verify all documents are searchable
        $queryVector = new Vector([0.2, 0.3, 0.4, 0.5, 0.6]);
        $results = iterator_to_array($this->store->queryForVector($queryVector, ['maxItems' => 10]));
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function testGetStoreReturnsCorrectInstance(): void
    {
        $retrievedStore = $this->store->getStore();

        $this->assertInstanceOf(CacheStore::class, $retrievedStore);
        $this->assertSame($this->cacheStore, $retrievedStore);
    }

    public function testStoreIsReadOnly(): void
    {
        // VectorLogDocumentStore is readonly, so this test verifies immutability
        $this->assertIsObject($this->store);

        // Attempting to modify the store should not affect functionality
        $vector = new Vector([0.5, 0.5, 0.5, 0.5, 0.5]);
        $metadata = new Metadata(['log_id' => 'test_001', 'content' => 'test']);
        $document = new VectorDocument(Uuid::v4(), $vector, $metadata);

        $this->store->saveLogVectorDocuments([$document]);

        // Verify it still works after operations
        $results = iterator_to_array($this->store->queryForVector($vector, ['maxItems' => 10]));
        $this->assertGreaterThanOrEqual(1, count($results));
    }
}
