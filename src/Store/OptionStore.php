<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Store;

use Doctrine\DBAL\Connection;

/**
 * Connection state in tl_featvalue_option (created by contao:migrate from the DCA).
 */
final class OptionStore
{
    public const SITE_UUID = 'site_uuid';
    public const PUBLIC_KEY = 'public_key';
    public const SECRET_KEY = 'secret_key_enc';
    public const CONNECTION_TOKEN = 'connection_token';
    public const PAIRING_NONCE = 'pairing_nonce';
    public const PAIRING_STATE = 'pairing_state';
    public const SITE_ID = 'site_id';
    public const SITE_TYPE = 'site_type';
    public const CLIENT_ID = 'client_id';
    public const DASHBOARD_ID = 'dashboard_id';
    public const REGISTERED_EMBED_URLS = 'registered_embed_urls';
    public const MAIL_ERROR = 'mail_error';
    public const SSO_ENABLED = 'sso_enabled';
    public const SSO_AVAILABLE = 'sso_available';
    public const BOOTSTRAP_LAST_GOOD = 'bootstrap_last_good';
    public const BOOTSTRAP_CACHE = 'bootstrap_cache';
    public const WEBHOOK_SEEN = 'webhook_seen';

    private const TABLE = 'tl_featvalue_option';

    /** @var array<string, string|null>|null */
    private array|null $cache = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        $all = $this->all();

        return isset($all[$key]) && $all[$key] !== null ? $all[$key] : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, '');

        return $value === '' ? $default : (int) $value;
    }

    public function set(string $key, string|int $value): void
    {
        $value = (string) $value;
        $this->all();
        if (\array_key_exists($key, $this->cache ?? [])) {
            $this->connection->update(self::TABLE, ['value' => $value, 'tstamp' => time()], ['name' => $key]);
        } else {
            $this->connection->insert(self::TABLE, ['name' => $key, 'value' => $value, 'tstamp' => time()]);
        }
        $this->cache[$key] = $value;
    }

    public function delete(string $key): void
    {
        $this->connection->delete(self::TABLE, ['name' => $key]);
        if ($this->cache !== null) {
            unset($this->cache[$key]);
        }
    }

    public function isSsoEnabled(): bool
    {
        return $this->get(self::SSO_ENABLED, '0') === '1';
    }

    public function isSsoAvailable(): bool
    {
        return $this->get(self::SSO_AVAILABLE, '0') === '1';
    }

    public function tableExists(): bool
    {
        try {
            return $this->connection->createSchemaManager()->tablesExist([self::TABLE]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function all(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            try {
                $rows = $this->connection->fetchAllAssociative('SELECT name, value FROM '.self::TABLE);
                foreach ($rows as $row) {
                    $this->cache[(string) $row['name']] = $row['value'] === null ? null : (string) $row['value'];
                }
            } catch (\Throwable) {
                // Table missing until contao:migrate ran.
            }
        }

        return $this->cache;
    }
}
