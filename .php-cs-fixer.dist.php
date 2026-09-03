<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    // Applications jetables du smoke test (.docker/smoke-app.sh) : ce n'est pas notre code.
    ->exclude('projects')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'class_attributes_separation' => [
            'elements' => [
                'const'    => 'one', // Adds an empty line between constants
                'property' => 'one', // Adds an empty line between properties
                'method'   => 'one', // Adds an empty line between methods
            ]
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline', // Ensures arguments are properly aligned
        ],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'], // Adds a trailing comma to multiline arrays, method arguments, and parameters
        ],
        'phpdoc_align' => true, // Helps align comments and annotations
    ])
    ->setFinder($finder)
    ;
