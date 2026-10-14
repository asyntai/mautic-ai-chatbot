<?php

declare(strict_types=1);

namespace MauticPlugin\AsyntaiBundle\Integration\Support;

use Mautic\IntegrationsBundle\DTO\Note;
use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormNotesInterface;
use MauticPlugin\AsyntaiBundle\Integration\AsyntaiIntegration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The window that opens from the Plugins page. "Published" switches the chat
 * and the contact import on and off; everything else is on the plugin's own
 * settings page, which the note links to.
 */
final class ConfigSupport extends AsyntaiIntegration implements ConfigFormInterface, ConfigFormNotesInterface
{
    use DefaultConfigFormTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function getAuthorizationNote(): ?Note
    {
        $url = $this->router->generate('mautic_asyntai_settings');

        return new Note(
            '<span>Connect your Asyntai account, choose the landing pages for the chat and set up the contact import on the <a href="'.htmlspecialchars($url, ENT_QUOTES).'">Asyntai AI Chatbot settings page</a>. Switch Published off to stop the chat and the import.</span>',
            Note::TYPE_INFO
        );
    }

    public function getFeaturesNote(): ?Note
    {
        return null;
    }

    public function getFieldMappingNote(): ?Note
    {
        return null;
    }
}
