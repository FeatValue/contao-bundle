<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Api;

/**
 * Which embed URLs may be framed, language mapping, height allow-list, UTM links.
 */
final class EmbedPolicy
{
    public function __construct(
        private readonly Bootstrap $bootstrap,
        private readonly Endpoints $endpoints,
    ) {
    }

    public function dashboardOrigin(): string
    {
        $config = $this->bootstrap->config();
        if (!empty($config['dashboard_origin']) && \is_string($config['dashboard_origin'])) {
            return $config['dashboard_origin'];
        }

        return $this->endpoints->localDashboardOrigin();
    }

    /**
     * FeatValue language code for the current request ('' if unsupported).
     */
    public function featvalueLang(): string
    {
        $primary = $this->endpoints->langRaw();
        $config = $this->bootstrap->config();
        $supported = isset($config['supported_locales']) && \is_array($config['supported_locales'])
            ? array_map('strval', $config['supported_locales'])
            : ['de', 'en', 'es', 'fa', 'fr', 'hi', 'id', 'it', 'nl', 'pl', 'pt', 'ru', 'sv', 'th'];

        return \in_array($primary, $supported, true) ? $primary : '';
    }

    public function isAllowedEmbedUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        $parts = parse_url($url);
        if (!\is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        if ($scheme !== 'https') {
            return false;
        }
        foreach ($this->allowedEmbedHosts() as $allowed) {
            $allowed = strtolower($allowed);
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function allowedEmbedHosts(): array
    {
        $config = $this->bootstrap->config();
        if (!empty($config['allowed_embed_hosts']) && \is_array($config['allowed_embed_hosts'])) {
            return array_values(array_map('strval', $config['allowed_embed_hosts']));
        }
        $hosts = ['featvalue.io'];
        foreach ([$this->endpoints->dashboardUrl(), $this->endpoints->integrationsUrlForBrowser()] as $base) {
            $h = parse_url($base, PHP_URL_HOST);
            if (\is_string($h) && $h !== '') {
                $hosts[] = strtolower($h);
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return list<string>
     */
    public function allowedAuthHostSuffixes(): array
    {
        $config = $this->bootstrap->config();
        if (!empty($config['allowed_auth_host_suffixes']) && \is_array($config['allowed_auth_host_suffixes'])) {
            return array_values(array_map('strval', $config['allowed_auth_host_suffixes']));
        }

        return ['featvalue.io'];
    }

    public static function sanitizeIframeHeight(string $height, string $default = 'min(800px, 80vh)'): string
    {
        $height = trim($height);
        if (preg_match('/^min\(\s*\d+(\.\d+)?px\s*,\s*\d+(\.\d+)?vh\s*\)$/', $height)) {
            return $height;
        }
        if (preg_match('/^calc\(\s*\d+(\.\d+)?vh\s*-\s*\d+(\.\d+)?px\s*\)$/', $height)) {
            return $height;
        }
        if (preg_match('/^\d+(\.\d+)?(px|vh|%|rem|em)$/', $height)) {
            return $height;
        }

        return $default;
    }

    public function withHostedSiteUtm(string $url, string $campaign, string|null $content = null): string
    {
        $params = ['utm_source' => 'hosted_site', 'utm_medium' => 'contao', 'utm_campaign' => $campaign];
        if ($content !== null && $content !== '') {
            $params['utm_content'] = $content;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($params);
    }
}
