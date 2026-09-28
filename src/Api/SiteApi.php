<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Api;

use Featvalue\ContaoBundle\Store\OptionStore;

/**
 * Connection state and the integrations site API.
 */
final class SiteApi
{
    private bool $synced = false;

    public function __construct(
        private readonly OptionStore $options,
        private readonly SignedClient $client,
        private readonly Bootstrap $bootstrap,
    ) {
    }

    public function getSiteType(): string
    {
        $type = $this->options->get(OptionStore::SITE_TYPE);

        return \in_array($type, ['client', 'agency'], true) ? $type : '';
    }

    public function getClientId(): int
    {
        return $this->options->getInt(OptionStore::CLIENT_ID);
    }

    public function needsClientSelection(): bool
    {
        return $this->getSiteType() === 'client' && $this->getClientId() < 1;
    }

    public function isSetupComplete(): bool
    {
        $type = $this->getSiteType();
        if ($type === '') {
            return false;
        }

        return $type !== 'client' || $this->getClientId() > 0;
    }

    public function isConnected(): bool
    {
        return $this->options->get(OptionStore::CONNECTION_TOKEN) !== '';
    }

    public function disconnect(): void
    {
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($siteId > 0 && $this->isConnected()) {
            try {
                $this->client->request('POST', '/self-hosted/sites/'.$siteId.'/revoke');
            } catch (\Throwable) {
                // Local cleanup must run anyway.
            }
        }
        $this->clearConnection();
    }

    public function clearConnection(): void
    {
        foreach ([
            OptionStore::CONNECTION_TOKEN, OptionStore::SITE_ID, OptionStore::SITE_TYPE, OptionStore::CLIENT_ID,
            OptionStore::DASHBOARD_ID, OptionStore::REGISTERED_EMBED_URLS, OptionStore::PAIRING_NONCE,
            OptionStore::PAIRING_STATE, OptionStore::MAIL_ERROR, OptionStore::SSO_AVAILABLE,
            OptionStore::BOOTSTRAP_LAST_GOOD, OptionStore::BOOTSTRAP_CACHE,
        ] as $key) {
            $this->options->delete($key);
        }
        $this->bootstrap->clear();
    }

    /**
     * Pulls site type, client, dashboard and SSO availability from integrations.
     * A 404 means integrations no longer knows the site (e.g. after a reset).
     */
    public function syncSetup(): void
    {
        if ($this->synced) {
            return;
        }
        $this->synced = true;
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($siteId < 1 || !$this->isConnected()) {
            return;
        }
        try {
            $result = $this->client->request('GET', '/api/self-hosted/'.$siteId.'/site-type');
        } catch (\Throwable) {
            return;
        }
        if ($result['status'] === 404) {
            $this->clearConnection();

            return;
        }
        if ($result['status'] !== 200 || !\is_array($result['body'])) {
            return;
        }
        $body = $result['body'];
        if ($this->getSiteType() === '' && !empty($body['site_type'])) {
            $type = $body['site_type'] === 'customer' ? 'client' : $body['site_type'];
            if (\in_array($type, ['client', 'agency'], true)) {
                $this->options->set(OptionStore::SITE_TYPE, $type);
            }
        }
        if ($this->getClientId() < 1 && !empty($body['featvalue_client_id'])) {
            $this->options->set(OptionStore::CLIENT_ID, (int) $body['featvalue_client_id']);
        }
        if (!empty($body['dashboard_id'])) {
            $this->options->set(OptionStore::DASHBOARD_ID, (int) $body['dashboard_id']);
        }
        if (isset($body['sso_available']) && \is_bool($body['sso_available'])) {
            $this->options->set(OptionStore::SSO_AVAILABLE, $body['sso_available'] ? '1' : '0');
        }
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function fetchClients(): array
    {
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($siteId < 1 || !$this->isConnected()) {
            return [];
        }
        try {
            $result = $this->client->request('GET', '/api/self-hosted/'.$siteId.'/clients');
        } catch (\Throwable) {
            return [];
        }
        if ($result['status'] !== 200 || !\is_array($result['body']['clients'] ?? null)) {
            return [];
        }
        $clients = [];
        foreach ($result['body']['clients'] as $client) {
            if (!\is_array($client) || empty($client['id'])) {
                continue;
            }
            $clients[] = [
                'id' => (int) $client['id'],
                'name' => isset($client['name']) && \is_string($client['name']) ? $client['name'] : (string) $client['id'],
            ];
        }
        usort($clients, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $clients;
    }

    /**
     * @param array{status: int, body: mixed} $result
     */
    public static function formatApiError(array $result, string $fallback): string
    {
        $body = $result['body'];
        if (\is_array($body)) {
            if (isset($body['message']) && \is_string($body['message'])) {
                return $body['message'];
            }
            if (isset($body['error']) && \is_string($body['error'])) {
                return $body['error'];
            }
        }
        if (\is_string($body) && $body !== '' && \strlen($body) < 200) {
            return $body;
        }

        return $fallback.' (HTTP '.$result['status'].')';
    }
}
