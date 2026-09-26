<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Page actions',
    'description' => 'Adds enable, disable and delete buttons for pages to the language comparison view of the page module',
    'category' => 'backend',
    'author' => 'Christoph Freund',
    'author_company' => 'Netzbewegung GmbH',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.9.99',
            'php' => '8.2.0-8.4.99',
        ],
    ],
];
