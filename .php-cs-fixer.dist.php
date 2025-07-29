<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__.'/lib', __DIR__.'/tests'])
    ->exclude(['vendor', 'storage', 'runtime', 'public', 'node_modules']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'no_superfluous_phpdoc_tags' => true,
        'ordered_imports' => true,
        'single_quote' => true,
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        'blank_line_before_statement' => ['statements' => ['return', 'throw', 'try', 'if', 'foreach', 'for', 'while', 'switch', 'case']],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_no_empty_return' => false,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'declare_strict_types' => true,
    ]);
