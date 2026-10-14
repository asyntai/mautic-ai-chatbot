<?php

declare(strict_types=1);

namespace MauticPlugin\AsyntaiBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class AsyntaiIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const NAME = 'asyntai';

    public const DISPLAY_NAME = 'Asyntai AI Chatbot';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/AsyntaiBundle/Assets/img/asyntai.png';
    }
}
