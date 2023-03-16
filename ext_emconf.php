<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'autofix',
    'description' => 'Perform automatic fixes on the command line',
    'category' => 'cli',
    'author' => 'Sybille Peters',
    'author_email' => 'sypets@gmx.de',
    'author_company' => '',
    'state' => 'stable',
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.24-11.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
