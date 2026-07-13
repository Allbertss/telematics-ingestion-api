<?php

// Code-style rules: PSR-12 as the baseline, the Symfony conventions on top,
// plus enforced `declare(strict_types=1);`. Scoped to our own code (src + tests)
// so generated migrations, config, and entry scripts are left untouched.
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
