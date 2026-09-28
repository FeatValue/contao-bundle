<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Api;

use Featvalue\ContaoBundle\Store\OptionStore;

/**
 * Cached bootstrap payload from integrations (policy, i18n, host.js URL).
 */
final class Bootstrap
{
    private const DEFAULT_TTL = 3600;

    /** @var array<string, mixed>|null */
    private array|null $payload = null;

    public function __construct(
        private readonly OptionStore $options,
        private readonly SignedClient $client,
        private readonly Endpoints $endpoints,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(bool $force = false): array
    {
        if (!$force && $this->payload !== null) {
            return $this->payload;
        }
        $lang = $this->endpoints->langRaw() !== '' ? $this->endpoints->langRaw() : 'en';
        if (!$force) {
            $cached = json_decode($this->options->get(OptionStore::BOOTSTRAP_CACHE), true);
            if (\is_array($cached) && ($cached['locale'] ?? '') === $lang && (int) ($cached['expires'] ?? 0) > time() && \is_array($cached['payload'] ?? null)) {
                return $this->payload = $cached['payload'];
            }
        }
        $fresh = $this->fetchRemote($lang);
        if (\is_array($fresh)) {
            $ttl = isset($fresh['cache_ttl']) ? max(60, (int) $fresh['cache_ttl']) : self::DEFAULT_TTL;
            $this->options->set(OptionStore::BOOTSTRAP_CACHE, (string) json_encode(['locale' => $lang, 'expires' => time() + $ttl, 'payload' => $fresh]));
            $this->options->set(OptionStore::BOOTSTRAP_LAST_GOOD, (string) json_encode($fresh));

            return $this->payload = $fresh;
        }
        $last = json_decode($this->options->get(OptionStore::BOOTSTRAP_LAST_GOOD), true);

        return $this->payload = (\is_array($last) && $last !== []) ? $last : $this->localDefaults();
    }

    public function clear(): void
    {
        $this->payload = null;
        $this->options->delete(OptionStore::BOOTSTRAP_CACHE);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        $payload = $this->payload();

        return \is_array($payload['config'] ?? null) ? $payload['config'] : [];
    }

    public function i18n(string $key, string $fallback = ''): string
    {
        $payload = $this->payload();
        $i18n = \is_array($payload['i18n'] ?? null) ? $payload['i18n'] : [];

        return isset($i18n[$key]) && \is_string($i18n[$key]) && $i18n[$key] !== '' ? $i18n[$key] : $fallback;
    }

    public function scriptUrl(): string
    {
        if ($this->endpoints->forceLocalEmbedHost()) {
            return '';
        }
        $payload = $this->payload();
        $url = isset($payload['script_url']) && \is_string($payload['script_url']) ? $payload['script_url'] : '';

        return (!empty($payload['features']['remote_host_js']) && $url !== '') ? $url : '';
    }

    public function registerUrl(): string
    {
        $payload = $this->payload();

        return isset($payload['register_url']) && \is_string($payload['register_url']) ? $payload['register_url'] : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRemote(string $locale): array|null
    {
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($siteId < 1 || $this->options->get(OptionStore::CONNECTION_TOKEN) === '') {
            return null;
        }
        $signPath = '/api/self-hosted/'.$siteId.'/bootstrap';
        try {
            $result = $this->client->signedGet($signPath, $signPath.'?locale='.rawurlencode($locale));
        } catch (\Throwable) {
            return null;
        }

        return ($result['status'] === 200 && \is_array($result['body'])) ? $result['body'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function localDefaults(): array
    {
        $hosts = ['featvalue.io'];
        foreach ([$this->endpoints->dashboardUrl(), $this->endpoints->integrationsUrlForBrowser()] as $base) {
            $h = parse_url($base, PHP_URL_HOST);
            if (\is_string($h) && $h !== '') {
                $hosts[] = strtolower($h);
            }
        }

        return [
            'config' => [
                'allowed_embed_hosts' => array_values(array_unique($hosts)),
                'allowed_auth_host_suffixes' => ['featvalue.io'],
                'dashboard_origin' => $this->endpoints->localDashboardOrigin(),
                'supported_locales' => ['de', 'en', 'es', 'fa', 'fr', 'hi', 'id', 'it', 'nl', 'pl', 'pt', 'ru', 'sv', 'th'],
            ],
            'i18n' => [],
            'features' => ['remote_host_js' => false, 'state_driven_gates' => false],
            'script_url' => '',
            'cache_ttl' => self::DEFAULT_TTL,
        ];
    }
}
