<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Api;

use Featvalue\ContaoBundle\Crypto\KeyStore;
use Featvalue\ContaoBundle\Store\OptionStore;

/**
 * Health-check signature and webhook HMAC verification.
 */
final class HealthService
{
    /** Domain-separated prefix so health signatures cannot forge API auth. */
    public const HEALTH_PREFIX = 'featvalue-health-v1|';

    private const WEBHOOK_MAX_SKEW = 300;

    public function __construct(
        private readonly OptionStore $options,
        private readonly KeyStore $keyStore,
    ) {
    }

    public function signChallenge(string $challenge): string
    {
        $secret = $this->keyStore->decryptSecretKey();
        if ($secret === false) {
            throw new \RuntimeException('No private key found.');
        }
        $signature = sodium_crypto_sign_detached(self::HEALTH_PREFIX.$challenge, $secret);
        sodium_memzero($secret);

        return base64_encode($signature);
    }

    public static function isValidChallenge(string $challenge): bool
    {
        $len = \strlen($challenge);

        return $len >= 16 && $len <= 128 && (bool) preg_match('/^[A-Za-z0-9+\/=_-]+$/', $challenge);
    }

    public function verifyWebhook(string $raw, string $timestamp, string $signature): bool
    {
        $token = $this->options->get(OptionStore::CONNECTION_TOKEN);
        if ($token === '' || $signature === '' || !preg_match('/^\d+$/', $timestamp)) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > self::WEBHOOK_MAX_SKEW) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'|'.$raw, $token);
        if (!hash_equals($expected, $signature)) {
            return false;
        }
        // Replay protection: remember signatures seen within the time window.
        $seen = json_decode($this->options->get(OptionStore::WEBHOOK_SEEN, '{}'), true);
        $seen = \is_array($seen) ? array_filter($seen, static fn ($ts) => (int) $ts > time() - 2 * self::WEBHOOK_MAX_SKEW) : [];
        $hash = hash('sha256', $signature);
        if (isset($seen[$hash])) {
            return false;
        }
        $seen[$hash] = time();
        $this->options->set(OptionStore::WEBHOOK_SEEN, (string) json_encode($seen));

        return true;
    }
}
