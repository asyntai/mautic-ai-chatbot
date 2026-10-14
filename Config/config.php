<?php

declare(strict_types=1);

return [
    'name'        => 'Asyntai AI Chatbot',
    'description' => 'An AI chat assistant on your landing pages. It answers visitors around the clock and turns the email addresses and phone numbers they share into Mautic contacts.',
    'version'     => '1.0.0',
    'author'      => 'Asyntai',
    'routes'      => [
        'main' => [
            'mautic_asyntai_settings' => [
                'path'       => '/asyntai',
                'controller' => 'MauticPlugin\AsyntaiBundle\Controller\SettingsController::indexAction',
                'method'     => 'GET',
            ],
            'mautic_asyntai_action' => [
                'path'         => '/asyntai/settings/{action}',
                'controller'   => 'MauticPlugin\AsyntaiBundle\Controller\SettingsController::postAction',
                'method'       => 'POST',
                'requirements' => ['action' => 'connect|reset|display|leads|leadsnow'],
            ],
        ],
        'public' => [],
        'api'    => [],
    ],
    'menu' => [
        'admin' => [
            'items' => [
                'mautic.asyntai.menu' => [
                    'id'        => 'mautic_asyntai_root',
                    'access'    => 'plugin:plugins:manage',
                    'route'     => 'mautic_asyntai_settings',
                    'parent'    => 'mautic.core.integrations',
                    'iconClass' => 'ri-chat-smile-3-line',
                ],
            ],
        ],
    ],
    'parameters' => [
        // Where the Asyntai server lives. A test install may point it at
        // another server with 'asyntai_origin' in config/local.php.
        'asyntai_origin' => 'https://asyntai.com',
    ],
];
