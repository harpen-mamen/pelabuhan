<?php

return [
    'theme' => null,
    'plugins' => [],
    
    'default_panel' => 'admin',
    
    'panels' => [
        'admin' => [
            'path' => 'admin',
            'domain' => null,
            'boot' => true,
            'discoverResources' => true,
            'discoverPages' => true,
            'discoverWidgets' => true,
            'resources' => [],
            'pages' => [],
            'widgets' => [],
            'middleware' => [
                'web',
                'auth:api',
            ],
            'navigation' => [
                'icon' => 'heroicon-o-cog-6-tooth',
                'label' => 'Admin Panel',
            ],
        ],
    ],
];
