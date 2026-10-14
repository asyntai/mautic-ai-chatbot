<?php

declare(strict_types=1);

namespace MauticPlugin\AsyntaiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\AsyntaiBundle\Integration\AsyntaiIntegration;

/**
 * The plugin's settings. They live on Mautic's own integration record for
 * "asyntai": plain values in the feature settings, the API key in the
 * encrypted API keys.
 */
class Settings
{
    public const DEFAULT_ORIGIN = 'https://asyntai.com';

    public const DEFAULT_TAG = 'asyntai-chat';

    public const MODE_ALL = 'all';

    public const MODE_TOKEN = 'token';

    public const MODE_OFF = 'off';

    /** The landing page token that puts the chat on one page. */
    public const TOKEN = '{asyntaichat}';

    private const SEEN_LIMIT = 5000;

    private ?Integration $entity = null;

    public function __construct(
        private readonly IntegrationsHelper $integrationsHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly CoreParametersHelper $coreParametersHelper,
    ) {
    }

    /**
     * Where the Asyntai server lives, without a trailing slash.
     */
    public function origin(): string
    {
        $origin = rtrim(trim((string) $this->coreParametersHelper->get('asyntai_origin', self::DEFAULT_ORIGIN)), '/');
        if (!preg_match('#^https?://[^\s/"\'<>]+$#i', $origin)) {
            $origin = self::DEFAULT_ORIGIN;
        }

        return $origin;
    }

    /**
     * The widget script URL. Only an https asyntai.com address or the
     * configured origin is accepted, so a changed setting cannot load a
     * script from anywhere else.
     */
    public function scriptUrl(): string
    {
        $stored = trim((string) $this->get('script_url', ''));
        if ('' !== $stored && (preg_match('#^https://([a-z0-9-]+\.)*asyntai\.com/[^\s"\'<>]*$#i', $stored)
                || (str_starts_with($stored, $this->origin().'/') && !preg_match('#[\s"\'<>]#', $stored)))) {
            return $stored;
        }

        return $this->origin().'/static/js/chat-widget.js';
    }

    public function siteId(): string
    {
        return (string) $this->get('site_id', '');
    }

    public function isConnected(): bool
    {
        return '' !== $this->siteId();
    }

    /**
     * The "Published" switch on the Plugins page. The chat and the import
     * run only while it is on.
     */
    public function isPublished(): bool
    {
        $entity = $this->entity(false);

        return null !== $entity && (bool) $entity->getIsPublished();
    }

    public function mode(): string
    {
        $mode = (string) $this->get('mode', self::MODE_ALL);

        return in_array($mode, [self::MODE_ALL, self::MODE_TOKEN, self::MODE_OFF], true) ? $mode : self::MODE_ALL;
    }

    public function importEnabled(): bool
    {
        return (bool) $this->get('import_enabled', false);
    }

    public function tag(): string
    {
        $tag = trim((string) $this->get('tag', self::DEFAULT_TAG));

        return '' !== $tag ? $tag : self::DEFAULT_TAG;
    }

    public function apiKey(): string
    {
        $entity = $this->entity(false);
        $keys   = $entity ? $entity->getApiKeys() : [];

        return is_array($keys) ? trim((string) ($keys['api_key'] ?? '')) : '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entity = $this->entity(false);
        if (null === $entity) {
            return $default;
        }
        $settings = $entity->getFeatureSettings() ?: [];

        return $settings[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function save(array $values, ?string $apiKey = null, ?bool $published = null): void
    {
        $entity   = $this->entity(true);
        $settings = array_merge($entity->getFeatureSettings() ?: [], $values);
        $entity->setFeatureSettings($settings);
        if (null !== $apiKey) {
            $keys = $entity->getApiKeys();
            $keys = is_array($keys) ? $keys : [];
            if ('' === $apiKey) {
                unset($keys['api_key']);
            } else {
                $keys['api_key'] = $apiKey;
            }
            $entity->setApiKeys($keys);
        }
        if (null !== $published) {
            $entity->setIsPublished($published);
        }
        $this->integrationsHelper->saveIntegrationConfiguration($entity);
    }

    /**
     * Session IDs of chats that are already imported, newest last.
     *
     * @return string[]
     */
    public function seen(): array
    {
        $seen = $this->get('seen', []);

        return is_array($seen) ? array_values(array_map('strval', $seen)) : [];
    }

    /**
     * @param string[] $seen
     *
     * @return string[]
     */
    public function trimSeen(array $seen): array
    {
        return array_slice(array_values(array_unique($seen)), -self::SEEN_LIMIT);
    }

    /**
     * The integration record. Mautic makes it when the plugin is installed;
     * if it is missing, it is made here so the settings page still works.
     */
    private function entity(bool $create): ?Integration
    {
        if (null !== $this->entity) {
            return $this->entity;
        }
        try {
            $this->entity = $this->integrationsHelper->getIntegration(AsyntaiIntegration::NAME)->getIntegrationConfiguration();
        } catch (IntegrationNotFoundException) {
            if (!$create) {
                return null;
            }
            $entity = new Integration();
            $entity->setName(AsyntaiIntegration::NAME);
            $entity->setIsPublished(false);
            $entity->setFeatureSettings([]);
            $entity->setApiKeys([]);
            $entity->setSupportedFeatures([]);
            $plugin = $this->entityManager->getRepository(Plugin::class)->findOneBy(['bundle' => 'AsyntaiBundle']);
            if ($plugin instanceof Plugin) {
                $entity->setPlugin($plugin);
            }
            $this->entity = $entity;
        }

        return $this->entity;
    }
}
