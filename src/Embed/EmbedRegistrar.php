<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Embed;

use Featvalue\ContaoBundle\Api\SignedClient;
use Featvalue\ContaoBundle\Api\SiteApi;
use Featvalue\ContaoBundle\Store\OptionStore;
use Symfony\Component\HttpFoundation\Request;

/**
 * Registers the page that embeds the dashboard with integrations (once per URL).
 */
final class EmbedRegistrar
{
    public function __construct(
        private readonly OptionStore $options,
        private readonly SiteApi $siteApi,
        private readonly SignedClient $client,
    ) {
    }

    public function register(string $context, Request $request, string $title = 'FeatValue'): void
    {
        if (!$this->siteApi->isConnected() || !$this->siteApi->isSetupComplete()) {
            return;
        }
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($siteId < 1) {
            return;
        }
        $pageUrl = $request->getSchemeAndHttpHost().$request->getRequestUri();
        if (!self::isRegisterableUrl($pageUrl)) {
            return;
        }
        $hash = md5($pageUrl);
        $registered = json_decode($this->options->get(OptionStore::REGISTERED_EMBED_URLS, '{}'), true);
        if (!\is_array($registered)) {
            $registered = [];
        }
        if ($context === 'frontend' && isset($registered[$hash])) {
            return;
        }
        try {
            $result = $this->client->request('POST', '/api/self-hosted/'.$siteId.'/embed-page', [
                'page_url' => $pageUrl,
                'title' => $title !== '' ? $title : 'FeatValue',
                'context' => $context,
            ]);
            if (\in_array((int) $result['status'], [200, 201], true)) {
                $registered[$hash] = time();
                $this->options->set(OptionStore::REGISTERED_EMBED_URLS, (string) json_encode($registered));
            }
        } catch (\Throwable) {
            // Registration is best effort.
        }
    }

    public static function isRegisterableUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        foreach (['/featvalue/v1/', '/_contao/', '/contao/featvalue'] as $fragment) {
            if (str_contains($path, $fragment)) {
                return false;
            }
        }

        return true;
    }
}
