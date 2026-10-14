<?php

declare(strict_types=1);

namespace MauticPlugin\AsyntaiBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\AsyntaiBundle\Service\LeadImporter;
use MauticPlugin\AsyntaiBundle\Service\Settings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Settings > Asyntai AI Chatbot.
 *
 * Every change is a normal POST form with its own CSRF token. The connect
 * window only returns the site ID; the page script puts it into a hidden
 * form and submits it.
 */
class SettingsController extends CommonController
{
    private const CSRF_ID = 'asyntai_settings';

    public function indexAction(Settings $settings, CsrfTokenManagerInterface $csrf): Response
    {
        if (!$this->security->isGranted('plugin:plugins:manage')) {
            $this->throwAccessDenied();
        }

        $lastResult = $settings->get('last_result', []);

        return $this->delegateView([
            'viewParameters' => [
                'origin'        => $settings->origin(),
                'connected'     => $settings->isConnected(),
                'accountEmail'  => (string) $settings->get('account_email', ''),
                'published'     => $settings->isPublished(),
                'mode'          => $settings->mode(),
                'token'         => Settings::TOKEN,
                'importEnabled' => $settings->importEnabled(),
                'hasApiKey'     => '' !== $settings->apiKey(),
                'tag'           => $settings->tag(),
                'lastImport'    => (string) $settings->get('last_import', ''),
                'lastResult'    => is_array($lastResult) ? $lastResult : [],
                'csrfToken'     => $csrf->getToken(self::CSRF_ID)->getValue(),
            ],
            'contentTemplate' => '@Asyntai/Settings/index.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_asyntai_root',
                'mauticContent' => 'asyntai',
                'route'         => $this->generateUrl('mautic_asyntai_settings'),
            ],
        ]);
    }

    public function postAction(
        Request $request,
        string $action,
        Settings $settings,
        LeadImporter $importer,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        if (!$this->security->isGranted('plugin:plugins:manage')) {
            $this->throwAccessDenied();
        }
        if (!$csrf->isTokenValid(new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token')))) {
            $this->addFlashMessage('mautic.asyntai.flash.expired', [], 'error');

            return $this->back();
        }

        switch ($action) {
            case 'connect':
                $siteId = trim((string) $request->request->get('site_id'));
                if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $siteId)) {
                    $this->addFlashMessage('mautic.asyntai.flash.connect_failed', [], 'error');
                    break;
                }
                $email = trim((string) $request->request->get('account_email'));
                if ('' !== $email && false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = '';
                }
                $settings->save([
                    'site_id'       => $siteId,
                    'script_url'    => trim((string) $request->request->get('script_url')),
                    'account_email' => $email,
                ], null, true);
                $this->addFlashMessage('mautic.asyntai.flash.connected');
                break;

            case 'reset':
                $settings->save(['site_id' => '', 'script_url' => '', 'account_email' => '']);
                $this->addFlashMessage('mautic.asyntai.flash.reset');
                break;

            case 'display':
                $mode = (string) $request->request->get('mode');
                if (!in_array($mode, [Settings::MODE_ALL, Settings::MODE_TOKEN, Settings::MODE_OFF], true)) {
                    $mode = Settings::MODE_ALL;
                }
                $settings->save(['mode' => $mode], null, (bool) $request->request->get('published'));
                $this->addFlashMessage('mautic.asyntai.flash.saved');
                break;

            case 'leads':
                $tag = trim(strip_tags((string) $request->request->get('tag')));
                $tag = '' !== $tag ? mb_substr($tag, 0, 100) : Settings::DEFAULT_TAG;
                $apiKey = null;
                if ($request->request->get('remove_api_key')) {
                    $apiKey = '';
                } elseif ('' !== trim((string) $request->request->get('api_key'))) {
                    $apiKey = trim((string) $request->request->get('api_key'));
                }
                $settings->save([
                    'import_enabled' => (bool) $request->request->get('import_enabled'),
                    'tag'            => $tag,
                ], $apiKey);
                $this->addFlashMessage('mautic.asyntai.flash.saved');
                break;

            case 'leadsnow':
                try {
                    $result = $importer->run(10);
                    $this->addFlashMessage('mautic.asyntai.flash.imported', [
                        '%created%' => $result['created'],
                        '%updated%' => $result['updated'],
                        '%failed%'  => $result['failed'],
                    ]);
                } catch (\RuntimeException $e) {
                    $this->addFlashMessage('mautic.asyntai.flash.import_failed', ['%error%' => $e->getMessage()], 'error');
                }
                break;
        }

        return $this->back();
    }

    private function back(): RedirectResponse
    {
        return $this->redirectToRoute('mautic_asyntai_settings');
    }
}
