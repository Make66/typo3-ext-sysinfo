<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Template, extension and security info',
    'description' => 'Provides a backend module for showing which templates included where, which extensions actually used as well as a couple of security and performance checks. This supports Typo3 maintenance, migration and security',
    'category' => 'module',
    'author' => 'Martin Keller',
    'author_email' => 'martin.keller@taketool.de',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'version' => '4.2.5',
    'constraints' => [
        'depends' => [
            'typo3' => '13.2.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
