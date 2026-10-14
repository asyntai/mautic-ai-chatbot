<?php

declare(strict_types=1);

namespace MauticPlugin\AsyntaiBundle\EventListener;

use Mautic\PageBundle\Event\PageBuilderEvent;
use Mautic\PageBundle\Event\PageDisplayEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\AsyntaiBundle\Service\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds the Asyntai chat widget to Mautic landing pages.
 *
 * Mode "all" puts it on every landing page. Mode "token" puts it only on the
 * pages that contain {asyntaichat}. The token itself is always removed from
 * the page. Preference centre pages, pop-ups and emails viewed in the
 * browser never get the chat.
 */
final class PageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After Mautic's own tokens, before its last clean-up pass.
            PageEvents::PAGE_ON_DISPLAY => ['onPageDisplay', -100],
            PageEvents::PAGE_ON_BUILD   => ['onPageBuild', 0],
        ];
    }

    public function onPageBuild(PageBuilderEvent $event): void
    {
        if ($event->tokensRequested(Settings::TOKEN)) {
            $event->addTokens([Settings::TOKEN => 'Asyntai AI Chatbot']);
        }
    }

    public function onPageDisplay(PageDisplayEvent $event): void
    {
        $content  = $event->getContent();
        $hasToken = false !== stripos($content, Settings::TOKEN);
        if ($hasToken) {
            $content = str_ireplace(Settings::TOKEN, '', $content);
        }

        if ($this->shouldShow($event, $hasToken) && !str_contains($content, 'data-asyntai-id=')) {
            $content = $this->inject($content);
        }

        if ($content !== $event->getContent()) {
            $event->setContent($content);
        }
    }

    private function shouldShow(PageDisplayEvent $event, bool $hasToken): bool
    {
        $page = $event->getPage();
        if (null === $page->getId() || $page->getIsPreferenceCenter()) {
            return false;
        }
        if (!$this->settings->isConnected() || !$this->settings->isPublished()) {
            return false;
        }

        return match ($this->settings->mode()) {
            Settings::MODE_ALL   => true,
            Settings::MODE_TOKEN => $hasToken,
            default              => false,
        };
    }

    private function inject(string $content): string
    {
        $tag = sprintf(
            '<script async src="%s" data-asyntai-id="%s"></script>',
            htmlspecialchars($this->settings->scriptUrl(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->settings->siteId(), ENT_QUOTES, 'UTF-8')
        );

        $pos = strripos($content, '</body>');
        if (false === $pos) {
            return $content.$tag;
        }

        return substr($content, 0, $pos).$tag."\n".substr($content, $pos);
    }
}
