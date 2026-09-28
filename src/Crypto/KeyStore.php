<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Crypto;

use Featvalue\ContaoBundle\Store\OptionStore;

/**
 * Ed25519 keypair; the secret key is encrypted with the Symfony kernel secret.
 */
final class KeyStore
{
    public function __construct(
        private readonly OptionStore $options,
        private readonly string $appSecret,
    ) {
    }

    public function ensureKeypair(): void
    {
        if ($this->options->get(OptionStore::SITE_UUID) !== '') {
            return;
        }
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            throw new \RuntimeException('FeatValue requires the PHP sodium extension.');
        }
        $kp = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($kp);
        $public = sodium_crypto_sign_publickey($kp);
        $encKey = $this->encryptionKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($secret, $nonce, $encKey);
        $this->options->set(OptionStore::SITE_UUID, self::uuid4());
        $this->options->set(OptionStore::PUBLIC_KEY, base64_encode($public));
        $this->options->set(OptionStore::SECRET_KEY, base64_encode($nonce.$cipher));
        sodium_memzero($secret);
        sodium_memzero($encKey);
    }

    public function decryptSecretKey(): string|false
    {
        $enc = $this->options->get(OptionStore::SECRET_KEY);
        if ($enc === '') {
            return false;
        }
        $raw = base64_decode($enc, true);
        if ($raw === false || \strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return false;
        }
        $encKey = $this->encryptionKey();
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $secret = sodium_crypto_secretbox_open($cipher, $nonce, $encKey);
        sodium_memzero($encKey);

        return $secret === false ? false : $secret;
    }

    private function encryptionKey(): string
    {
        if ($this->appSecret === '') {
            throw new \RuntimeException('APP_SECRET is empty.');
        }

        return substr(hash('sha256', 'featvalue|'.$this->appSecret, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = \chr((\ord($data[6]) & 0x0F) | 0x40);
        $data[8] = \chr((\ord($data[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
