<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Embed;

use Featvalue\ContaoBundle\Api\Bootstrap;
use Featvalue\ContaoBundle\Api\EmbedPolicy;
use Featvalue\ContaoBundle\Api\Endpoints;
use Featvalue\ContaoBundle\Api\SignedClient;
use Featvalue\ContaoBundle\Api\SiteApi;
use Featvalue\ContaoBundle\Store\OptionStore;
use Featvalue\ContaoBundle\User\CmsUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renders the dashboard iframe (content element, front end module, back end tasks).
 */
final class DashboardRenderer
{
    public function __construct(
        private readonly OptionStore $options,
        private readonly SiteApi $siteApi,
        private readonly SignedClient $client,
        private readonly Bootstrap $bootstrap,
        private readonly EmbedPolicy $policy,
        private readonly Endpoints $endpoints,
        private readonly CmsUser $cmsUser,
        private readonly EmbedRegistrar $registrar,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $context 'frontend' or 'backend'
     */
    public function html(string $height, string $context, Request $request, string $pageTitle = 'FeatValue'): string
    {
        $this->siteApi->syncSetup();
        if (!$this->siteApi->isConnected()) {
            return $this->notice($this->bootstrap->i18n('not_connected', $this->t('dashboardNotConnected')));
        }
        if (!$this->siteApi->isSetupComplete()) {
            return $this->notice($this->bootstrap->i18n('finish_setup', $this->t('finishSetup')));
        }
        $user = $this->cmsUser->current();
        if ($context === 'frontend' && $this->options->isSsoEnabled() && $this->options->isSsoAvailable() && $user === null) {
            return $this->notice($this->t('ssoLoginRequired'));
        }
        $embed = $this->fetchDashboardEmbed($user, false);
        if (!empty($embed['email_verification_required'])) {
            return $this->verificationGate($height, $embed);
        }

        return $this->renderIframe($embed, $height, $context, $request, $user === null, $pageTitle);
    }

    /**
     * @param array{cms_user_id: string, email: string, name: string}|null $user
     *
     * @return array<string, mixed>
     */
    public function fetchDashboardEmbed(array|null $user, bool $forceEmailVerification): array
    {
        if ($this->options->isSsoEnabled() && $user !== null && !$forceEmailVerification) {
            $sso = $this->fetchSsoEmbed($user);
            if ($sso['available']) {
                return ['embed_url' => $sso['embed_url'], 'dashboard_id' => $sso['dashboard_id'], 'error' => '', 'sso' => true];
            }
        }

        return $this->fetchTasksEmbed($user, $forceEmailVerification);
    }

    /**
     * @param array<string, mixed> $embed
     */
    private function verificationGate(string $height, array $embed): string
    {
        $height = EmbedPolicy::sanitizeIframeHeight($height);
        $gate = \is_array($embed['gate'] ?? null) ? $embed['gate'] : [];
        $message = isset($gate['message']) && \is_string($gate['message']) && $gate['message'] !== ''
            ? $gate['message']
            : $this->bootstrap->i18n('email_verification_required', $this->t('emailVerification'));
        $hint = isset($gate['hint']) && \is_string($gate['hint']) && $gate['hint'] !== ''
            ? $gate['hint']
            : $this->bootstrap->i18n('email_verification_hint', 'We have sent you a confirmation link. Afterwards you can open the dashboard again.');
        $config = [
            'restUrl' => $this->router->generate('featvalue_resend_email_verification'),
            'i18n' => [
                'sending' => 'Sending…',
                'sent' => 'Confirmation email sent again.',
                'throttled' => 'Please wait a moment before requesting another email.',
                'failed' => 'Could not send the email. Please try again later.',
                'label' => $this->t('resend'),
            ],
        ];
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return '<link rel="stylesheet" href="'.$e($this->endpoints->assetUrl('css/status-gate.css')).'">'
            .'<script>window.featvalueStatusGate = '.json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).';</script>'
            .'<div class="featvalue-dashboard-wrap" style="position:relative;width:100%;height:'.$e($height).';min-height:280px;">'
            .'<div class="fv-embed-status-gate" role="status" data-fv-status-gate><div class="fv-embed-status-gate-card">'
            .'<p>'.$e($message).'</p><p class="fv-embed-status-hint">'.$e($hint).'</p>'
            .'<button type="button" class="fv-embed-status-gate-button" data-fv-resend-verification>'.$e($config['i18n']['label']).'</button>'
            .'<p class="fv-embed-status-feedback" data-fv-resend-feedback hidden></p>'
            .'</div></div></div>'
            .'<script src="'.$e($this->endpoints->assetUrl('js/status-gate.js')).'" defer></script>';
    }

    /**
     * @param array<string, mixed> $embed
     */
    private function renderIframe(array $embed, string $height, string $context, Request $request, bool $anonymous, string $pageTitle): string
    {
        $height = EmbedPolicy::sanitizeIframeHeight($height);
        $url = (string) ($embed['embed_url'] ?? '');
        if ($url === '' || !$this->policy->isAllowedEmbedUrl($url)) {
            $gate = \is_array($embed['gate'] ?? null) ? $embed['gate'] : [];
            $error = isset($gate['message']) && \is_string($gate['message']) && $gate['message'] !== ''
                ? $gate['message']
                : (string) (($embed['error'] ?? '') ?: $this->bootstrap->i18n('dashboard_load_failed', 'The dashboard could not be loaded. Please try again later.'));

            return $this->notice($error);
        }
        $jwt = trim((string) $request->query->get('fv_jwt_token', ''));
        if ($jwt !== '' && preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $jwt)) {
            $url .= (str_contains($url, '?') ? '&' : '?').'jwt_token='.rawurlencode($jwt);
        }
        $lang = $this->policy->featvalueLang();
        if ($lang !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?').'lang='.rawurlencode($lang);
        }
        $this->registrar->register($context === 'backend' ? 'admin' : 'frontend', $request, $pageTitle);
        $guestEmbed = $anonymous || str_contains($url, 'cms_anonymous=1');
        $script = $this->scriptTags(empty($embed['sso']) && !$guestEmbed);
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        $dashboardId = (int) ($embed['dashboard_id'] ?? 0) ?: $this->options->getInt(OptionStore::DASHBOARD_ID);
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $safeUrl = $e($url);

        return $script
            .'<div class="featvalue-dashboard-wrap" style="width:100%;">'
            .'<iframe id="featvalue-dashboard-iframe" title="FeatValue" src="'.$safeUrl.'" data-fv-embed-base="'.$safeUrl.'"'
            .' data-fv-dashboard-origin="'.$e($this->policy->dashboardOrigin()).'" data-fv-embed-origin="'.$e(Endpoints::originOf($url)).'"'
            .' data-fv-dashboard-id="'.$dashboardId.'" data-fv-site-id="'.$siteId.'"'
            .' style="width:100%;height:'.$e($height).';border:0;display:block;"'
            .' referrerpolicy="strict-origin-when-cross-origin" allow="clipboard-read; clipboard-write"></iframe></div>';
    }

    private function scriptTags(bool $topLevelLogin): string
    {
        $remote = $this->bootstrap->scriptUrl();
        $local = $this->endpoints->assetUrl('js/embed-parent.js');
        $src = $remote !== '' ? $remote : $local;
        $config = [
            'authHostSuffixes' => $this->policy->allowedAuthHostSuffixes(),
            'topLevelLogin' => $topLevelLogin ? '1' : '0',
            'i18n' => [
                'loginRequired' => $this->bootstrap->i18n('sign_in_required', 'Sign in to show this dashboard.'),
                'loginButton' => $this->bootstrap->i18n('sign_in_button', 'Sign in'),
                'loginHint' => $this->bootstrap->i18n('sign_in_hint', 'A new window opens for sign-in. It closes automatically afterwards.'),
                'registerButton' => $this->bootstrap->i18n('register_button', 'Register'),
            ],
            'registerUrl' => $this->bootstrap->registerUrl(),
            'lang' => $this->policy->featvalueLang(),
        ];
        $html = '<script>window.featvalueEmbedParent = '.json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).';</script>';
        $html .= '<script src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" defer></script>';
        if ($remote !== '') {
            $html .= '<script>(function(){var s=document.querySelector('.json_encode('script[src="'.$remote.'"]', JSON_UNESCAPED_SLASHES).');'
                .'if(!s){return;}s.addEventListener("error",function(){if(window.__fvHostJsFallback){return;}window.__fvHostJsFallback=1;'
                .'var f=document.createElement("script");f.src='.json_encode($local, JSON_UNESCAPED_SLASHES).';document.head.appendChild(f);});})();</script>';
        }

        return $html;
    }

    /**
     * @param array{cms_user_id: string, email: string, name: string} $user
     *
     * @return array{embed_url: string, available: bool, dashboard_id: int}
     */
    private function fetchSsoEmbed(array $user): array
    {
        $empty = ['embed_url' => '', 'available' => false, 'dashboard_id' => 0];
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($siteId < 1 || !$this->siteApi->isSetupComplete()) {
            return $empty;
        }
        try {
            $result = $this->client->request('POST', '/api/self-hosted/'.$siteId.'/sso-embed', [
                'email' => $user['email'],
                'name' => $user['name'] !== '' ? $user['name'] : $user['email'],
                'cms_user_id' => $user['cms_user_id'],
            ]);
        } catch (\Throwable) {
            return $empty;
        }
        $body = \is_array($result['body'] ?? null) ? $result['body'] : [];
        // Non-premium (402) or incomplete setup (422): fall back silently.
        if ($result['status'] !== 200 || empty($body['embed_url']) || !$this->policy->isAllowedEmbedUrl((string) $body['embed_url'])) {
            return $empty;
        }
        if (!empty($body['dashboard_id'])) {
            $this->options->set(OptionStore::DASHBOARD_ID, (int) $body['dashboard_id']);
        }

        return ['embed_url' => (string) $body['embed_url'], 'available' => true, 'dashboard_id' => (int) ($body['dashboard_id'] ?? 0)];
    }

    /**
     * @param array{cms_user_id: string, email: string, name: string}|null $user
     *
     * @return array<string, mixed>
     */
    private function fetchTasksEmbed(array|null $user, bool $forceEmailVerification): array
    {
        $empty = ['embed_url' => '', 'dashboard_id' => 0, 'error' => '', 'email_verification_required' => false];
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($siteId < 1) {
            return array_merge($empty, ['error' => $this->bootstrap->i18n('not_connected', $this->t('dashboardNotConnected'))]);
        }
        $body = [];
        $method = 'GET';
        if ($user !== null) {
            $method = 'POST';
            $body = [
                'email' => $user['email'],
                'name' => $user['name'] !== '' ? $user['name'] : $user['email'],
                'cms_user_id' => $user['cms_user_id'],
            ];
            if ($forceEmailVerification) {
                $body['force_email_verification'] = true;
            }
        }
        try {
            $result = $this->client->request($method, '/api/self-hosted/'.$siteId.'/tasks-embed', $body);
        } catch (\Throwable $e) {
            return array_merge($empty, ['error' => $e->getMessage()]);
        }
        $response = \is_array($result['body'] ?? null) ? $result['body'] : [];
        $gate = \is_array($response['gate'] ?? null) ? $response['gate'] : [];
        if ($result['status'] === 404) {
            $this->siteApi->clearConnection();

            return array_merge($empty, ['error' => $this->bootstrap->i18n('connection_outdated', $this->t('connectionExpired')), 'gate' => $gate]);
        }
        if (!empty($response['email_verification_required'])) {
            return array_merge($empty, ['email_verification_required' => true, 'gate' => $gate]);
        }
        if ($result['status'] !== 200 || empty($response['embed_url'])) {
            return array_merge($empty, ['error' => (string) ($response['error'] ?? 'embed_unavailable'), 'gate' => $gate]);
        }
        $embedUrl = (string) $response['embed_url'];
        if ($user === null) {
            $embedUrl .= (str_contains($embedUrl, '?') ? '&' : '?').'cms_anonymous=1';
        }
        if (!empty($response['dashboard_id'])) {
            $this->options->set(OptionStore::DASHBOARD_ID, (int) $response['dashboard_id']);
        }

        return [
            'embed_url' => $embedUrl,
            'dashboard_id' => (int) ($response['dashboard_id'] ?? 0),
            'error' => '',
            'email_verification_required' => false,
            'gate' => $gate,
        ];
    }

    private function notice(string $message): string
    {
        return '<p class="featvalue-dashboard-notice">'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p>';
    }

    private function t(string $key): string
    {
        $translated = $this->translator->trans('MSC.featvalue.'.$key, [], 'contao_default');

        return $translated !== 'MSC.featvalue.'.$key ? $translated : $key;
    }
}
