<?php

namespace Hakam\AiLogInspector\Test\Support;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Deterministic embedding platform for tests, built on Symfony AI's MockPlatformFactory.
 *
 * Every input text gets a 5-dimensional vector derived from its length, so batches
 * return one vector per document like a real embedding model.
 */
final class FakeEmbeddingPlatform
{
    public static function create(): PlatformInterface
    {
        return MockPlatformFactory::createPlatform(
            static function (Model $model, array|string|object $input): VectorResult {
                $texts = \is_array($input) ? array_values($input) : [$input];

                return new VectorResult(array_map(
                    static fn ($text): Vector => self::embed(\is_string($text) ? $text : json_encode($text)),
                    $texts,
                ));
            },
        );
    }

    public static function embed(string $text): Vector
    {
        return new Vector([\strlen($text) % 100 / 100, 0.2, 0.3, 0.4, 0.5]);
    }
}
