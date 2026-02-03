<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/../src')
    ->name('*.php')
    ->notPath('tests/Fixtures')  // Optional exclusions
    ->exclude(['vendor', 'var']);

return (new Config())
    ->setRiskyAllowed(true)
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'binary_operator_spaces' => [
            'default' => 'align_single_space_minimal',
        ],
        'class_attributes_separation' => [
            'elements' => ['method' => 'one'],
        ],
    ])
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/../var/.php-cs-fixer.cache');
