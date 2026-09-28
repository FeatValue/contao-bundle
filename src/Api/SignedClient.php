<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Api;

use Featvalue\ContaoBundle\Crypto\KeyStore;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ed25519-signed server-to-server requests to FeatValue integrations.
 */
final class SignedClient
{
    public const USER_AGENT = 'featvalue-contao/0.1';

    public function __construct(
        private readonly KeyStore $keyStore,
        private readonly Endpoints $endpoints,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: mixed}
     */
    public function request(string $method, string $path, array $body = []): array
    {
        $secret = $this->keyStore->decryptSecretKey();
        if ($secret === false) {
            throw new \RuntimeException('No FeatValue private key found.');
        }
        $method = strtoupper($method);
        $json = $body === [] ? '' : (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signPath = strtok($path, '?') ?: $path;
        $ts = (string) microtime(true);
        $payload = $ts.'|'.$method.'|'.$signPath.'|'.hash('sha256', $json);
        $sig = sodium_crypto_sign_detached($payload, $secret);
        sodium_memzero($secret);
        $headers = array_merge($this->endpoints->upstreamHeaders(), [
            'X-Fv-Timestamp' => $ts,
            'X-Fv-Signature' => base64_encode($sig),
            'Accept' => 'application/json',
            'User-Agent' => self::USER_AGENT,
        ]);
        $options = ['headers' => $headers, 'timeout' => 15];
        if ($method === 'POST') {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body'] = $json;
        }

        return $this->send($method, rtrim($this->endpoints->integrationsUrlForServer(), '/').$path, $options);
    }

    /**
     * Signed GET where the signature covers the path without query string.
     *
     * @return array{status: int, body: mixed}
     */
    public function signedGet(string $signPath, string $requestPath): array
    {
        $secret = $this->keyStore->decryptSecretKey();
        if ($secret === false) {
            throw new \RuntimeException('No FeatValue private key found.');
        }
        $ts = (string) microtime(true);
        $sig = sodium_crypto_sign_detached($ts.'|GET|'.$signPath.'|'.hash('sha256', ''), $secret);
        sodium_memzero($secret);

        return $this->send('GET', rtrim($this->endpoints->integrationsUrlForServer(), '/').$requestPath, [
            'headers' => array_merge($this->endpoints->upstreamHeaders(), [
                'X-Fv-Timestamp' => $ts,
                'X-Fv-Signature' => base64_encode($sig),
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ]),
            'timeout' => 15,
        ]);
    }

    /**
     * Unsigned form POST (pairing start).
     *
     * @param array<string, scalar> $params
     *
     * @return array{status: int, body: mixed}
     */
    public function postForm(string $url, array $params): array
    {
        return $this->send('POST', $url, [
            'headers' => array_merge($this->endpoints->upstreamHeaders(), [
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ]),
            'body' => $params,
            'timeout' => 45,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{status: int, body: mixed}
     */
    private function send(string $method, string $url, array $options): array
    {
        $response = $this->httpClient->request($method, $url, $options);
        $status = $response->getStatusCode();
        $raw = $response->getContent(false);
        $parsed = json_decode($raw, true);

        return ['status' => $status, 'body' => \is_array($parsed) ? $parsed : $raw];
    }
}
