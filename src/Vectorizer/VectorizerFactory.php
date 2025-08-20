<?php

namespace Hakam\AiLogInspector\Vectorizer;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\Vectorizer;

final class VectorizerFactory
{
    public static function create(
        PlatformInterface $platform,
         Model $model
    ): Vectorizer {
        return new Vectorizer($platform, $model);
    }
}
