<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Connect;

use Featvalue\ContaoBundle\Api\EmbedPolicy;
use Featvalue\ContaoBundle\Api\Endpoints;
use Featvalue\ContaoBundle\Api\SignedClient;
use Featvalue\ContaoBundle\Api\SiteApi;
use Featvalue\ContaoBundle\Crypto\KeyStore;
use Featvalue\ContaoBundle\Store\OptionStore;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Pairing flow: begin-pairing (server to server) and the signed callback.
 */
final class ConnectService
{
    public function __construct(
        private readonly OptionStore $options,
        private readonly KeyStore $keyStore,
        private readonly SignedClient $client,
        private readonly SiteApi $siteApi,
        private readonly Endpoints $endpoints,
        private readonly EmbedPolicy $policy,
        private readonly RouterInterface $router,
    ) {
    }

    public function startConnect(string $email = ''): string
    {
        $this->keyStore->ensureKeypair();
        $nonce = random_bytes(32);
        $this->options->set(OptionStore::PAIRING_NONCE, base64_encode($nonce));
        $state = bin2hex(random_bytes(16));
        $this->options->set(OptionStore::PAIRING_STATE, $state);
        $lang = $this->policy->featvalueLang();
        $params = [
            'site_url' => $this->endpoints->siteUrl(),
            'site_name' => $this->endpoints->siteName(),
            'public_key' => $this->options->get(OptionStore::PUBLIC_KEY),
            'site_uuid' => $this->options->get(OptionStore::SITE_UUID),
            'pairing_nonce_hash' => hash('sha256', $nonce),
            'return_url' => $this->router->generate('featvalue_backend_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'state' => $state,
            'locale' => $lang !== '' ? $lang : 'en',
        ];
        if ($email !== '') {
            $params['suggested_email'] = $email;
        }
        $result = $this->client->postForm(
            $this->endpoints->integrationsUrlForServer().'/api/self-hosted/begin-pairing/contao',
            $params
        );
        $body = $result['body'];
        if ($result['status'] < 200 || $result['status'] >= 300 || !\is_array($body) || empty($body['redirect'])) {
            $message = \is_array($body) && !empty($body['error']) ? (string) $body['error'] : 'Could not start FeatValue connection.';
            throw new \RuntimeException($message);
        }

        return (string) $body['redirect'];
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleCallback(array $query): void
    {
        $token = (string) ($query['connection_token'] ?? '');
        $sig = (string) ($query['sig'] ?? '');
        $state = (string) ($query['state'] ?? '');
        $siteId = (int) ($query['site_id'] ?? 0);
        if ($token === '' || $sig === '') {
            throw new \RuntimeException('Token or signature missing.');
        }
        if (!hash_equals($this->options->get(OptionStore::PAIRING_STATE), $state)) {
            throw new \RuntimeException('Invalid CSRF state.');
        }
        $nonce = base64_decode($this->options->get(OptionStore::PAIRING_NONCE), true);
        if ($nonce === false) {
            throw new \RuntimeException('Pairing nonce not found.');
        }
        // integrations signs HMAC-SHA256(key=pairing_nonce_hash, data=connection_token).
        $expected = hash_hmac('sha256', $token, hash('sha256', $nonce));
        if (!hash_equals($expected, $sig)) {
            throw new \RuntimeException('Invalid connection token signature.');
        }
        $this->options->delete(OptionStore::PAIRING_NONCE);
        $this->options->delete(OptionStore::PAIRING_STATE);
        $this->options->set(OptionStore::CONNECTION_TOKEN, $token);
        if ($siteId > 0) {
            $this->options->set(OptionStore::SITE_ID, $siteId);
        }
        $this->siteApi->syncSetup();
    }
}
