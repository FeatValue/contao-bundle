<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Controller\Backend;

use Contao\BackendUser;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Featvalue\ContaoBundle\Api\EmbedPolicy;
use Featvalue\ContaoBundle\Api\Endpoints;
use Featvalue\ContaoBundle\Api\SignedClient;
use Featvalue\ContaoBundle\Api\SiteApi;
use Featvalue\ContaoBundle\Connect\ConnectService;
use Featvalue\ContaoBundle\Crypto\KeyStore;
use Featvalue\ContaoBundle\Store\OptionStore;
use Featvalue\ContaoBundle\User\CmsUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Back end module System → FeatValue.
 */
#[Route('%contao.backend.route_prefix%/featvalue', defaults: ['_scope' => 'backend', '_token_check' => true])]
final class SettingsController extends AbstractBackendController
{
    public function __construct(
        private readonly OptionStore $options,
        private readonly KeyStore $keyStore,
        private readonly SiteApi $siteApi,
        private readonly SignedClient $client,
        private readonly ConnectService $connect,
        private readonly CmsUser $cmsUser,
        private readonly EmbedPolicy $policy,
        private readonly Endpoints $endpoints,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'featvalue_backend', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $this->denyUnlessAllowed();
        $error = (string) $request->getSession()->getFlashBag()->get('featvalue_error', [''])[0];
        try {
            $this->keyStore->ensureKeypair();
            if ($request->isMethod('POST')) {
                $action = (string) $request->request->get('action', '');
                if ($action === 'connect') {
                    $user = $this->cmsUser->current();

                    return new RedirectResponse($this->connect->startConnect($user['email'] ?? ''));
                }
                $this->handlePost($action, $request);

                return $this->redirectToRoute('featvalue_backend');
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $this->siteApi->syncSetup();
        $connected = $this->siteApi->isConnected();
        $siteType = $this->siteApi->getSiteType();
        $clientId = $this->siteApi->getClientId();
        $ssoAvailable = $this->options->isSsoAvailable();

        return $this->render('@FeatvalueContao/backend/settings.html.twig', [
            'title' => 'FeatValue',
            'headline' => 'FeatValue',
            'error' => $error,
            'mailError' => $this->options->get(OptionStore::MAIL_ERROR),
            'connected' => $connected,
            'siteType' => $siteType,
            'clientId' => $clientId,
            'clients' => ($connected && $siteType === 'client' && $clientId < 1) ? $this->siteApi->fetchClients() : [],
            'ssoEnabled' => $this->options->isSsoEnabled() && $ssoAvailable,
            'ssoAvailable' => $ssoAvailable,
            'plansUrl' => $this->policy->withHostedSiteUtm($this->endpoints->appUrl().'/payment', 'premium_upgrade', 'settings_sso'),
            'whitelabelingUrl' => $this->policy->withHostedSiteUtm($this->endpoints->appUrl().'/?open=whitelabeling', 'whitelabeling', 'settings'),
            'requestToken' => $this->csrfTokenManager->getDefaultTokenValue(),
            'lang' => $this->labels(),
        ]);
    }

    #[Route('/callback', name: 'featvalue_backend_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $this->denyUnlessAllowed();
        $query = $request->query->all();
        if (isset($query['fv_error']) && !isset($query['connection_token'])) {
            $request->getSession()->getFlashBag()->add('featvalue_error', (string) $query['fv_error']);

            return $this->redirectToRoute('featvalue_backend');
        }
        try {
            $this->connect->handleCallback($query);
        } catch (\Throwable $e) {
            $request->getSession()->getFlashBag()->add('featvalue_error', $e->getMessage());
        }

        return $this->redirectToRoute('featvalue_backend');
    }

    private function handlePost(string $action, Request $request): void
    {
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($action === 'disconnect') {
            $this->siteApi->disconnect();

            return;
        }
        if ($action === 'saveSiteType') {
            $type = (string) $request->request->get('site_type', '');
            if (!\in_array($type, ['client', 'agency'], true) || $this->siteApi->getSiteType() !== '') {
                return;
            }
            $result = $this->client->request('POST', '/api/self-hosted/'.$siteId.'/site-type', ['site_type' => $type]);
            if ($result['status'] === 404) {
                $this->siteApi->clearConnection();
                throw new \RuntimeException($this->labels()['connectionExpired']);
            }
            if ($result['status'] === 409 && \is_array($result['body']) && !empty($result['body']['site_type'])) {
                $this->options->set(OptionStore::SITE_TYPE, (string) $result['body']['site_type']);

                return;
            }
            if ($result['status'] !== 200) {
                throw new \RuntimeException(SiteApi::formatApiError($result, 'Could not save site type.'));
            }
            $this->options->set(OptionStore::SITE_TYPE, $type);

            return;
        }
        if ($action === 'saveClient') {
            $clientId = (int) $request->request->get('featvalue_client_id', 0);
            if ($clientId < 1 || !$this->siteApi->needsClientSelection()) {
                return;
            }
            $result = $this->client->request('POST', '/api/self-hosted/'.$siteId.'/client', ['featvalue_client_id' => $clientId]);
            if ($result['status'] === 404) {
                $this->siteApi->clearConnection();
                throw new \RuntimeException($this->labels()['connectionExpired']);
            }
            if ($result['status'] === 409 && \is_array($result['body']) && !empty($result['body']['featvalue_client_id'])) {
                $this->options->set(OptionStore::CLIENT_ID, (int) $result['body']['featvalue_client_id']);

                return;
            }
            if ($result['status'] !== 200) {
                throw new \RuntimeException(SiteApi::formatApiError($result, 'Could not save client.'));
            }
            $this->options->set(OptionStore::CLIENT_ID, $clientId);

            return;
        }
        if ($action === 'saveSso') {
            $enabled = $request->request->getBoolean('sso_enabled');
            if ($enabled && !$this->options->isSsoAvailable()) {
                return;
            }
            $this->options->set(OptionStore::SSO_ENABLED, $enabled ? '1' : '0');
        }
    }

    private function denyUnlessAllowed(): void
    {
        $user = $this->getUser();
        if (!$user instanceof BackendUser || (!$user->isAdmin && !$user->hasAccess('featvalue', 'modules'))) {
            throw $this->createAccessDeniedException('FeatValue module not permitted.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function labels(): array
    {
        $keys = ['connect', 'disconnect', 'connected', 'notConnected', 'siteType', 'siteTypePermanent', 'siteTypeClient',
            'siteTypeClientHelp', 'siteTypeAgency', 'siteTypeAgencyHelp', 'save', 'client', 'sso', 'ssoHelp', 'openTasks',
            'reallyDisconnect', 'connectionExpired', 'openWhitelabeling', 'whitelabeling', 'siteTypeLabel', 'viewPlans'];
        $labels = [];
        foreach ($keys as $key) {
            $labels[$key] = $this->translator->trans('MSC.featvalue.'.$key, [], 'contao_default');
        }

        return $labels;
    }
}
